<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\BusinessType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'business_type_id' => BusinessType::factory(),
            'name' => 'Unit ' . fake()->unique()->numberBetween(1, 999),
            'unit_price' => fake()->randomFloat(2, 10000, 200000),
            'is_active' => true,
        ];
    }

    public function forBusinessType(BusinessType $type): static
    {
        return $this->state(fn () => ['business_type_id' => $type->id]);
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(['branch_id' => $branch->id]);
    }
}
