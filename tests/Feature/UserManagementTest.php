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

    public function test_executive_requires_a_unique_access_code(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/users', [
                'name' => 'First Exec',
                'role' => Role::Executive->value,
                'access_code' => 'HOUSETWO',
                'pin' => '7733',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.access_code', 'HOUSETWO');

        $this->actingAs($admin)
            ->postJson('/api/v1/users', [
                'name' => 'Second Exec',
                'role' => Role::Executive->value,
                'access_code' => 'housetwo',
                'pin' => '7733',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('access_code');
    }

    public function test_api_created_manager_can_login_with_branch_code(): void
    {
        $branch = Branch::factory()->create(['code' => 'NAKKAWA']);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/users', [
                'name' => 'Branch Manager',
                'role' => Role::Manager->value,
                'branch_id' => $branch->id,
                'pin' => '5566',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.access_code', 'NAKKAWA');

        $this->postJson('/api/v1/auth/login', [
            'access_code' => 'NAKKAWA',
            'pin' => '5566',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'manager')
            ->assertJsonPath('user.branch.code', 'NAKKAWA');
    }

    public function test_changing_a_branch_code_updates_the_manager_login_code(): void
    {
        $branch = Branch::factory()->create(['code' => 'OLDNAME']);
        $manager = User::factory()->manager($branch)->withPin('1234')->create();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->putJson("/api/v1/branches/{$branch->id}", [
                'branch_name' => 'Oldname Renamed',
                'code' => 'NEWNAME',
            ])
            ->assertOk();

        $this->assertSame('NEWNAME', $manager->fresh()->access_code);

        $this->postJson('/api/v1/auth/login', [
            'access_code' => 'NEWNAME',
            'pin' => '1234',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $manager->id);

        $this->postJson('/api/v1/auth/login', [
            'access_code' => 'OLDNAME',
            'pin' => '1234',
        ])->assertStatus(401);
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
