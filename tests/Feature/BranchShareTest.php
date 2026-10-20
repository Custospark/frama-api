<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_branches_get_a_slug_on_create(): void
    {
        $branch = Branch::create(['branch_name' => 'Rubaga Main', 'code' => 'RUBAGAMAIN']);

        $this->assertSame('rubaga-main', $branch->slug);
    }

    public function test_guest_cannot_view_a_shared_branch_summary(): void
    {
        $branch = Branch::factory()->create();

        $this->getJson("/api/v1/share/branch/{$branch->slug}")->assertStatus(401);
    }

    public function test_any_authenticated_role_can_view_a_shared_branch_summary(): void
    {
        $branch = Branch::factory()->create(['branch_name' => 'Rubaga']);
        $manager = User::factory()->manager()->create();

        Report::factory()->forBranch($branch)->onDate(now()->toDateString())->create([
            'rooms_rev' => 100000,
            'bar_rev' => 50000,
            'food_rev' => 25000,
            'sauna_rev' => 5000,
            'total_rev' => 180000,
        ]);

        $this->actingAs($manager)
            ->getJson("/api/v1/share/branch/{$branch->slug}")
            ->assertOk()
            ->assertJsonPath('data.branch.branch_name', 'Rubaga')
            ->assertJsonPath('data.branch.id', $branch->id)
            ->assertJsonPath('data.today.total_rev', 180000)
            ->assertJsonPath('data.month_total', 180000)
            ->assertJsonPath('data.submitted_this_month', 1);
    }

    public function test_shared_summary_never_exposes_receipt_images_or_other_branches(): void
    {
        $viewer = User::factory()->executive()->create();
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();

        Report::factory()->forBranch($branch)->onDate(now()->toDateString())->create();
        Report::factory()->forBranch($other)->onDate(now()->toDateString())->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/share/branch/{$branch->slug}")
            ->assertOk();

        $this->assertStringNotContainsString('receipt_image_url', $response->getContent());
        $this->assertStringNotContainsString($other->branch_name, $response->getContent());
    }

    public function test_shared_summary_includes_a_trend_series(): void
    {
        $viewer = User::factory()->manager()->create();
        $branch = Branch::factory()->create();

        Report::factory()->forBranch($branch)->onDate(now()->toDateString())->create(['total_rev' => 5000]);
        Report::factory()->forBranch($branch)->onDate(now()->subDay()->toDateString())->create(['total_rev' => 3000]);

        $this->actingAs($viewer)
            ->getJson("/api/v1/share/branch/{$branch->slug}")
            ->assertOk()
            ->assertJsonCount(2, 'data.trend')
            ->assertJsonPath('data.trend.0.total', 3000)
            ->assertJsonPath('data.trend.1.total', 5000);
    }

    public function test_unknown_slug_returns_404(): void
    {
        $viewer = User::factory()->manager()->create();

        $this->actingAs($viewer)
            ->getJson('/api/v1/share/branch/does-not-exist')
            ->assertStatus(404);
    }
}
