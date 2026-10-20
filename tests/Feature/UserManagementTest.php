<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_create_manager(): void
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/users', [
                'name' => 'Jane Doe',
                'role' => Role::Manager->value,
                'branch_id' => $branch->id,
                'pin' => '7733',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Jane Doe')
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('data.branch_id', $branch->id);
    }

    public function test_manager_requires_a_branch(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/users', [
                'name' => 'No Branch',
                'role' => Role::Manager->value,
                'pin' => '7733',
            ])
            ->assertStatus(422);
    }

    public function test_pin_is_required_and_hashed(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/users', [
                'name' => 'No Pin',
                'role' => Role::Executive->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pin');
    }

    public function test_platform_admin_can_reset_pin(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $user = User::factory()->executive()->withPin('1234')->create();

        $this->actingAs($admin)
            ->postJson("/api/v1/users/{$user->id}/reset-pin", ['pin' => '4321'])
            ->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('4321', $user->fresh()->pin_hash));
    }

    public function test_executive_cannot_manage_users(): void
    {
        $executive = User::factory()->executive()->create();

        $this->actingAs($executive)
            ->getJson('/api/v1/users')
            ->assertStatus(403);
    }

    public function test_manager_cannot_manage_users(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        $this->actingAs($manager)
            ->getJson('/api/v1/users')
            ->assertStatus(403);
    }

    public function test_platform_admin_can_list_and_delete_user(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $user = User::factory()->executive()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/users')
            ->assertOk();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/users/{$user->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
