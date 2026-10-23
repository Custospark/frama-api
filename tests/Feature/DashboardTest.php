<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_returns_a_row_per_active_branch(): void
    {
        Branch::factory()->create(['branch_name' => 'Rubaga']);
        Branch::factory()->create(['branch_name' => 'Kibuya']);
        Branch::factory()->inactive()->create();
        $executive = User::factory()->executive()->create();

        $this->actingAs($executive)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonCount(2, 'data.rows');
    }

    public function test_summary_marks_submitted_and_pending(): void
    {
        $submitted = Branch::factory()->create(['branch_name' => 'Rubaga']);
        Branch::factory()->create(['branch_name' => 'Kibuya']);
        $executive = User::factory()->executive()->create();

        Report::factory()->forBranch($submitted)->onDate(now()->toDateString())->create([
            'rooms_rev' => 100000,
            'bar_rev' => 50000,
            'food_rev' => 0,
            'sauna_rev' => 0,
            'total_rev' => 150000,
        ]);

        $response = $this->actingAs($executive)
            ->getJson('/api/v1/dashboard/summary?date=' . now()->toDateString())
            ->assertOk();

        $rows = collect($response->json('data.rows'));
        $rubaga = $rows->firstWhere('branch_name', 'Rubaga');
        $kibuya = $rows->firstWhere('branch_name', 'Kibuya');

        $this->assertSame('Submitted', $rubaga['status']);
        $this->assertSame(150000, $rubaga['total_rev']);
        $this->assertSame('Pending', $kibuya['status']);
        $this->assertSame(0, $kibuya['total_rev']);
    }

    public function test_grand_total_sums_submitted_rows(): void
    {
        $a = Branch::factory()->create();
        $b = Branch::factory()->create();
        $executive = User::factory()->executive()->create();
        $date = now()->toDateString();

        Report::factory()->forBranch($a)->onDate($date)->create(['total_rev' => 100000]);
        Report::factory()->forBranch($b)->onDate($date)->create(['total_rev' => 250000]);

        $response = $this->actingAs($executive)
            ->getJson('/api/v1/dashboard/summary?date=' . $date)
            ->assertOk();

        $this->assertEquals(350000, $response->json('data.grand_total'));
        $response->assertJsonPath('data.submitted_count', 2)
            ->assertJsonPath('data.pending_count', 0);
    }

    public function test_summary_totals_stay_in_sync_with_the_displayed_rows(): void
    {
        $motel = Branch::factory()->create(['branch_name' => 'Kibuye']);
        $hostel = Branch::factory()->create(['branch_name' => 'Frama Hostel']);
        Branch::factory()->create(['branch_name' => 'Rubaga']);
        $executive = User::factory()->executive()->create();
        $date = now()->toDateString();

        Report::factory()->forBranch($motel)->onDate($date)->create([
            'rooms_rev' => 120000,
            'bar_rev' => 60000,
            'food_rev' => 80000,
            'sauna_rev' => 0,
            'other_rev' => 10000,
            'total_rev' => 270000,
            'expenses' => 20000,
            'cash_handed_over' => 250000,
        ]);

        Report::factory()->forBranch($hostel)->onDate($date)->create([
            'rooms_rev' => 0,
            'bar_rev' => 0,
            'food_rev' => 0,
            'sauna_rev' => 0,
            'other_rev' => 0,
            'rooms_occupied' => 18,
            'total_rev' => 450000,
            'expenses' => 35000,
            'cash_handed_over' => 415000,
        ]);

        $response = $this->actingAs($executive)
            ->getJson('/api/v1/dashboard/summary?date=' . $date)
            ->assertOk();

        $data = $response->json('data');
        $rows = collect($data['rows']);

        $this->assertSame(720000, $data['grand_total']);
        $this->assertSame(720000, $data['month_total']);
        $this->assertSame(55000, $data['expenses_total']);
        $this->assertSame(665000, $data['net_total']);
        $this->assertSame(665000, $data['cash_handed_total']);
        $this->assertSame(2, $data['submitted_count']);
        $this->assertSame(1, $data['pending_count']);

        $this->assertSame($data['grand_total'], $rows->sum('total_rev'));
        $this->assertSame($data['expenses_total'], $rows->sum('expenses'));
        $this->assertSame($data['cash_handed_total'], $rows->sum('cash_handed_over'));
        $this->assertSame(
            $data['grand_total'] - $data['expenses_total'],
            $rows->sum('net_rev'),
        );

        $hostelRow = $rows->firstWhere('branch_name', 'Frama Hostel');
        $this->assertSame(450000, $hostelRow['total_rev']);
        $this->assertSame(450000, $hostelRow['month_total']);
        $this->assertFalse($hostelRow['allow_edit']);
        $this->assertSame(18, $hostelRow['rooms_occupied']);
        $this->assertSame('Pending', $rows->firstWhere('branch_name', 'Rubaga')['status']);
    }

    public function test_manager_cannot_access_dashboard(): void    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        $this->actingAs($manager)
            ->getJson('/api/v1/dashboard/summary')
            ->assertStatus(403);
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->getJson('/api/v1/dashboard/summary')->assertStatus(401);
    }

    public function test_trend_returns_daily_totals(): void
    {
        $branch = Branch::factory()->create();
        $executive = User::factory()->executive()->create();

        Report::factory()->forBranch($branch)->onDate(now()->subDay()->toDateString())->create(['total_rev' => 90000]);
        Report::factory()->forBranch($branch)->onDate(now()->toDateString())->create(['total_rev' => 120000]);

        $this->actingAs($executive)
            ->getJson('/api/v1/dashboard/trend')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
