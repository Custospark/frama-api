<?php

namespace App\Services;

use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Turns CustomField definitions into Laravel validation rules and persists the
 * submitted values (uploading files to the public disk). This is the single
 * place that understands how a custom field type maps to a request value.
 */
class DynamicFieldValidator
{
    public function __construct(
        protected int $maxFileKb = 8192,
        protected ?FormulaEvaluator $formula = null,
    ) {
        $this->formula ??= new FormulaEvaluator();
    }

    /**
     * Field definitions that apply to the given scope / business type / branch.
     *
     * @return \Illuminate\Support\Collection<int, CustomField>
     */
    public function definitions(CustomFieldScope $scope, ?int $businessTypeId, ?int $branchId)
    {
        return CustomField::query()
            ->applicable($scope, $businessTypeId, $branchId)
            ->get();
    }

    /**
     * Field definitions across every business type (plus org-wide/general) for a
     * scope and branch. Used when a single submission collects fields from all
     * business types at once (daily reports).
     *
     * @return \Illuminate\Support\Collection<int, CustomField>
     */
    public function definitionsForAllTypes(CustomFieldScope $scope, ?int $branchId)
    {
        return CustomField::query()
            ->where('scope', $scope->value)
            ->where('is_active', true)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId) {
                    $q->orWhere('branch_id', $branchId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Build `custom_values.*` validation rules keyed by field key.
     *
     * @param  \Illuminate\Support\Collection<int, CustomField>  $definitions
     * @return array<string, array<int, mixed>>
     */
    public function rules($definitions): array
    {
        $rules = [];

        foreach ($definitions as $field) {
            if ($field->type === CustomFieldType::Calculated) {
                // Derived fields are never supplied by the client; the server
                // recomputes and stores them.
                $rules["custom_values.{$field->key}"] = ['nullable'];
                continue;
            }

            $rules["custom_values.{$field->key}"] = $this->rulesForField($field, $field->is_required);
        }

        return $rules;
    }

    /**
     * @return array<int, mixed>
     */
    private function rulesForField(CustomField $field, bool $required): array
    {
        $rules = [$required ? 'required' : 'nullable'];
        $validation = $field->validation ?? [];

        switch ($field->type) {
            case CustomFieldType::Text:
            case CustomFieldType::Textarea:
                $rules[] = 'string';
                if (isset($validation['max_length'])) {
                    $rules[] = 'max:' . (int) $validation['max_length'];
                }
                break;

            case CustomFieldType::Number:
                $rules[] = 'numeric';
                if (isset($validation['min'])) {
                    $rules[] = 'min:' . $validation['min'];
                }
                if (isset($validation['max'])) {
                    $rules[] = 'max:' . $validation['max'];
                }
                break;

            case CustomFieldType::Date:
                $rules[] = 'date';
                break;

            case CustomFieldType::Select:
                $options = collect($field->options ?? [])->pluck('value')->all();
                $rules[] = 'string';
                $rules[] = 'in:' . implode(',', $options);
                break;

            case CustomFieldType::Checkbox:
                $rules[] = 'boolean';
                break;

            case CustomFieldType::File:
                $rules[] = 'file';
                $rules[] = 'max:' . (int) ($validation['max_file_kb'] ?? $this->maxFileKb);
                if (! empty($validation['accept'])) {
                    $rules[] = 'mimes:' . $validation['accept'];
                }
                break;

            case CustomFieldType::Calculated:
                $rules = ['nullable'];
                break;
        }

        return $rules;
    }

    /**
     * Normalise + persist submitted values for a set of definitions.
     * Files are stored on the public disk and replaced with their stored path.
     * Calculated fields are computed from the merged value map (base variables
     * plus other custom values) in dependency order.
     *
     * @param  \Illuminate\Support\Collection<int, CustomField>  $definitions
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $baseVariables  values available to formulas (e.g. revenue fields)
     * @return array<string, mixed>
     */
    public function extractValues($definitions, array $input, string $directory, array $baseVariables = []): array
    {
        $values = [];
        $calculated = [];

        foreach ($definitions as $field) {
            $key = $field->key;

            if ($field->type === CustomFieldType::Calculated) {
                $calculated[$key] = $field;
                continue;
            }

            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if ($field->type === CustomFieldType::File) {
                if ($value instanceof UploadedFile) {
                    $values[$key] = Storage::disk('public')->putFile($directory, $value);
                } elseif (is_string($value) && $value !== '') {
                    $values[$key] = $value;
                }
                continue;
            }

            if ($field->type === CustomFieldType::Checkbox) {
                $values[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                continue;
            }

            if ($field->type === CustomFieldType::Number && is_numeric($value)) {
                $values[$key] = $value + 0;
                continue;
            }

            if ($value !== null && $value !== '') {
                $values[$key] = $value;
            }
        }

        return $this->computeCalculated($calculated, $values, $baseVariables);
    }

    /**
     * Evaluate every calculated field, resolving dependencies between them.
     * Fields are computed in dependency order; cycles throw.
     *
     * @param  array<string, CustomField>  $calculated
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $baseVariables
     * @return array<string, mixed>
     */
    private function computeCalculated(array $calculated, array $values, array $baseVariables): array
    {
        if ($calculated === []) {
            return $values;
        }

        $context = array_merge($baseVariables, $values);
        $resolved = [];
        $visiting = [];

        $resolve = function (string $key) use (&$resolve, &$resolved, &$visiting, $calculated, &$context): void {
            if (array_key_exists($key, $resolved)) {
                return;
            }

            if (isset($visiting[$key])) {
                throw new InvalidArgumentException("Circular reference detected for '{$key}'.");
            }

            $visiting[$key] = true;
            $field = $calculated[$key];
            $formula = $field->validation['formula'] ?? '';

            if (! is_string($formula) || trim($formula) === '') {
                throw new InvalidArgumentException("The calculated field '{$key}' has no formula.");
            }

            foreach ($this->formula->referencedVariables($formula) as $ref) {
                if (isset($calculated[$ref])) {
                    $resolve($ref);
                }
            }

            $context[$key] = $this->formula->evaluate($formula, $context);
            $resolved[$key] = true;
            unset($visiting[$key]);
        };

        foreach (array_keys($calculated) as $key) {
            $resolve($key);
        }

        foreach ($calculated as $key => $field) {
            $context[$key] = round($context[$key] ?? 0.0, $field->validation['precision'] ?? 2);
        }

        return $context;
    }

    /**
     * Resolve stored file paths to public URLs for API output.
     *
     * @param  \Illuminate\Support\Collection<int, CustomField>  $definitions
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>
     */
    public function presentValues($definitions, ?array $values): array
    {
        $values ??= [];

        return $definitions->mapWithKeys(function (CustomField $field) use ($values) {
            $raw = $values[$field->key] ?? null;

            if ($field->type === CustomFieldType::File && is_string($raw) && $raw !== '') {
                $raw = Storage::disk('public')->url($raw);
            }

            return [$field->key => $raw];
        })->all();
    }
}
