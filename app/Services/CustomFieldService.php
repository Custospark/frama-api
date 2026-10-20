<?php

namespace App\Services;

use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\User;
use App\Repositories\Contracts\CustomFieldRepositoryInterface;
use App\Services\Contracts\CustomFieldServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CustomFieldService implements CustomFieldServiceInterface
{
    /** Fixed report revenue keys that calculated fields may reference. */
    private const BASE_REPORT_VARS = [
        'rooms_rev', 'bar_rev', 'food_rev', 'sauna_rev', 'total_rev',
        'expenses', 'cash_handed_over',
    ];

    public function __construct(
        protected CustomFieldRepositoryInterface $customFieldRepository,
        protected DynamicFieldValidator $dynamicFieldValidator,
        protected FormulaEvaluator $formulaEvaluator,
    ) {}

    public function list(User $user, ?CustomFieldScope $scope = null, ?int $businessTypeId = null): Collection
    {
        return $this->customFieldRepository->allForBranch($this->managementBranchId($user), $scope, $businessTypeId);
    }

    public function create(User $user, array $data): CustomField
    {
        $scope = CustomFieldScope::from($data['scope']);
        $businessTypeId = $data['business_type_id'] ?? null;
        $branchId = $this->resolveBranchIdForWrite($user, $data['branch_id'] ?? null);
        $key = $data['key'] ?? Str::snake($data['label']);

        if ($this->customFieldRepository->keyExists($scope, $businessTypeId, $branchId, $key)) {
            throw new RuntimeException('A field with this key already exists for this type.');
        }

        if (CustomFieldType::from($data['type']) === CustomFieldType::Calculated) {
            $this->assertValidFormula(
                $scope,
                $businessTypeId,
                $branchId,
                $key,
                $data['validation']['formula'] ?? null,
            );
        }

        return $this->customFieldRepository->create([
            'scope' => $scope->value,
            'business_type_id' => $businessTypeId,
            'branch_id' => $branchId,
            'key' => $key,
            'label' => $data['label'],
            'type' => $data['type'],
            'help_text' => $data['help_text'] ?? null,
            'placeholder' => $data['placeholder'] ?? null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'options' => $this->normaliseOptions($data['type'], $data['options'] ?? null),
            'validation' => $data['validation'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    public function update(User $user, int $id, array $data): CustomField
    {
        $field = $this->customFieldRepository->find($id);

        if (! $field || ! $this->canManage($user, $field)) {
            throw new RuntimeException('Custom field not found.');
        }

        $payload = [];

        foreach (['label', 'type', 'help_text', 'placeholder', 'sort_order', 'is_active'] as $attr) {
            if (array_key_exists($attr, $data)) {
                $payload[$attr] = $data[$attr];
            }
        }

        if (array_key_exists('is_required', $data)) {
            $payload['is_required'] = (bool) $data['is_required'];
        }

        if (array_key_exists('options', $data)) {
            $payload['options'] = $this->normaliseOptions($data['type'] ?? $field->type->value, $data['options']);
        }

        if (array_key_exists('validation', $data)) {
            $payload['validation'] = $data['validation'];
        }

        $newType = CustomFieldType::from($payload['type'] ?? $field->type->value);
        if ($newType === CustomFieldType::Calculated) {
            $this->assertValidFormula(
                $field->scope,
                $field->business_type_id,
                $field->branch_id,
                $field->key,
                ($payload['validation'] ?? $field->validation)['formula'] ?? null,
            );
        }

        return $this->customFieldRepository->update($field, $payload);
    }

    public function delete(User $user, int $id): bool
    {
        $field = $this->customFieldRepository->find($id);

        if (! $field || ! $this->canManage($user, $field)) {
            throw new RuntimeException('Custom field not found.');
        }

        return $this->customFieldRepository->delete($field);
    }

    public function schemaFor(?int $branchId, CustomFieldScope $scope, ?int $businessTypeId): Collection
    {
        return $this->dynamicFieldValidator->definitions($scope, $businessTypeId, $branchId);
    }

    /**
     * Managers only see/manage their own branch's custom fields. Executives and
     * platform admins operate org-wide (branch_id null).
     */
    private function managementBranchId(User $user): ?int
    {
        return $user->hasAtLeastRole(\App\Enums\Role::Executive) ? null : $user->branch_id;
    }

    private function resolveBranchIdForWrite(User $user, ?int $requested): ?int
    {
        if ($user->hasAtLeastRole(\App\Enums\Role::Executive)) {
            return $requested;
        }

        if (! $user->branch_id) {
            throw new RuntimeException('Your account is not linked to a branch.');
        }

        return $user->branch_id;
    }

    private function canManage(User $user, CustomField $field): bool
    {
        if ($user->hasAtLeastRole(\App\Enums\Role::Executive)) {
            return true;
        }

        return $field->branch_id !== null && $field->branch_id === $user->branch_id;
    }

    /**
     * Ensure a calculated field has a syntactically valid formula whose
     * references resolve to known fields, and that no dependency cycle exists.
     */
    private function assertValidFormula(
        CustomFieldScope $scope,
        ?int $businessTypeId,
        ?int $branchId,
        string $selfKey,
        mixed $formula,
    ): void {
        if (! is_string($formula) || trim($formula) === '') {
            throw new RuntimeException('A calculated field needs a formula.');
        }

        try {
            $references = $this->formulaEvaluator->referencedVariables($formula);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException('The formula is invalid: ' . $e->getMessage());
        }

        $known = $this->customFieldRepository->allForBranch($branchId, $scope, $businessTypeId);

        $calculatedFormulas = [];
        foreach ($known as $field) {
            if ($field->key === $selfKey) {
                continue;
            }
            if ($field->type === CustomFieldType::Calculated) {
                $calculatedFormulas[$field->key] = (string) ($field->validation['formula'] ?? '');
            }
        }

        $validKeys = $known->pluck('key')->push($selfKey)->all();
        if ($scope === CustomFieldScope::Report) {
            $validKeys = array_merge($validKeys, self::BASE_REPORT_VARS);
        }

        foreach ($references as $ref) {
            if (! in_array($ref, $validKeys, true) && ! isset($calculatedFormulas[$ref])) {
                throw new RuntimeException("The formula references unknown field '{$ref}'.");
            }
        }

        // Detect cycles: treat the new field as referencing its dependencies.
        $calculatedFormulas[$selfKey] = $formula;
        $this->assertNoCycles($calculatedFormulas, $selfKey);
    }

    /**
     * @param  array<string, string>  $formulas
     */
    private function assertNoCycles(array $formulas, string $start): void
    {
        $visit = function (string $key, array $trail) use (&$visit, $formulas): void {
            if (in_array($key, $trail, true)) {
                throw new RuntimeException('The formula creates a circular reference.');
            }

            $trail[] = $key;
            $formula = $formulas[$key] ?? '';

            try {
                $refs = $this->formulaEvaluator->referencedVariables($formula);
            } catch (InvalidArgumentException) {
                return;
            }

            foreach ($refs as $ref) {
                if (isset($formulas[$ref])) {
                    $visit($ref, $trail);
                }
            }
        };

        $visit($start, []);
    }

    /**
     * @return array<int, array{value: string, label: string}>|null
     */
    private function normaliseOptions(string $type, mixed $options): ?array
    {
        if (CustomFieldType::from($type) !== CustomFieldType::Select) {
            return null;
        }

        if (! is_array($options)) {
            return [];
        }

        return collect($options)
            ->map(function ($option) {
                if (is_array($option)) {
                    $value = (string) ($option['value'] ?? $option['label'] ?? '');

                    return ['value' => $value, 'label' => (string) ($option['label'] ?? $value)];
                }

                $value = (string) $option;

                return ['value' => $value, 'label' => $value];
            })
            ->filter(fn ($option) => $option['value'] !== '')
            ->values()
            ->all();
    }
}
