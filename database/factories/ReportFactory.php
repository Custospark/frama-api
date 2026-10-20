<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rooms = fake()->numberBetween(0, 500000);
        $bar = fake()->numberBetween(0, 300000);
        $food = fake()->numberBetween(0, 200000);
        $sauna = fake()->numberBetween(0, 150000);
        $total = $rooms + $bar + $food + $sauna;
        $expenses = fake()->numberBetween(0, (int) ($total / 4) + 1);

        return [
            'branch_id' => Branch::factory(),
            'user_id' => null,
            'report_date' => now()->toDateString(),
            'rooms_rev' => $rooms,
            'bar_rev' => $bar,
            'food_rev' => $food,
            'sauna_rev' => $sauna,
            'total_rev' => $total,
            'expenses' => $expenses,
            'cash_handed_over' => $total - $expenses,
            'comment' => fake()->optional()->sentence(),
            'manager_name' => fake()->name(),
            'submitted_at' => now(),
        ];
    }

    /** Attach a single sample image attachment after the report is created. */
    public function withAttachment(): static
    {
        return $this->afterCreating(function (Report $report) {
            $path = 'reports/attachments/' . fake()->uuid() . '.jpg';
            \Illuminate\Support\Facades\Storage::disk('public')->put($path, 'image-bytes');

            $report->attachments()->create([
                'original_name' => 'receipt.jpg',
                'path' => $path,
                'mime_type' => 'image/jpeg',
                'size' => 11,
                'is_archive' => false,
            ]);
        });
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(['branch_id' => $branch->id]);
    }

    public function by(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'manager_name' => $user->name,
        ]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn () => ['report_date' => $date]);
    }
}
