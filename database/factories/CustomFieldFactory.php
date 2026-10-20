<?php

namespace Database\Factories;

use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use App\Models\BusinessType;
use App\Models\CustomField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomField>
 */
class CustomFieldFactory extends Factory
{
    protected $model = CustomField::class;

    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return [
            'scope' => CustomFieldScope::Report->value,
            'business_type_id' => BusinessType::factory(),
            'branch_id' => null,
            'key' => Str::snake($label) . '_' . fake()->unique()->numberBetween(1, 99999),
            'label' => Str::headline($label),
            'type' => CustomFieldType::Number->value,
            'help_text' => null,
            'placeholder' => null,
            'is_required' => false,
            'options' => null,
            'validation' => null,
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function forBranch(int $branchId): static
    {
        return $this->state(['branch_id' => $branchId]);
    }

    public function scope(CustomFieldScope $scope): static
    {
        return $this->state(['scope' => $scope->value]);
    }

    public function forBusinessType(?BusinessType $type): static
    {
        return $this->state(['business_type_id' => $type?->id]);
    }

    public function type(CustomFieldType $type): static
    {
        return $this->state(['type' => $type->value]);
    }
}
