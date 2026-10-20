<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_can_list_branches(): void
    {
        Branch::factory()->count(3)->create();
        $executive = User::factory()->executive()->create();

        $this->actingAs($executive)
            ->getJson('/api/v1/branches')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_manager_cannot_list_branches(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        $this->actingAs($manager)
            ->getJson('/api/v1/branches')
            ->assertStatus(403);
    }

    public function test_platform_admin_can_create_branch_with_auto_code(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/branches', ['branch_name' => 'Kibuya'])
            ->assertStatus(201)
            ->assertJsonPath('data.branch_name', 'Kibuya')
            ->assertJsonPath('data.code', 'KIBUYA');
    }

    public function test_platform_admin_can_regenerate_branch_code(): void
    {
        $branch = Branch::factory()->create(['branch_name' => 'Rubaga', 'code' => 'RUBAGA']);
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/branches/{$branch->id}/regenerate-code")
            ->assertOk();

        $this->assertNotSame('RUBAGA', $response->json('data.code'));
    }

    public function test_duplicate_branch_code_is_rejected(): void
    {
        Branch::factory()->create(['code' => 'RUBAGA']);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/branches', ['branch_name' => 'Other', 'code' => 'RUBAGA'])
            ->assertStatus(422);
    }

    public function test_executive_cannot_create_branch(): void
    {
        $executive = User::factory()->executive()->create();

        $this->actingAs($executive)
            ->postJson('/api/v1/branches', ['branch_name' => 'Nope'])
            ->assertStatus(403);
    }
}
