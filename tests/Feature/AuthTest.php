<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_login_with_branch_code_and_pin(): void
    {
        $branch = Branch::factory()->create(['code' => 'RUBAGA']);
        $manager = User::factory()->manager($branch)->withPin('4821')->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'access_code' => 'RUBAGA',
            'pin' => '4821',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $manager->id)
            ->assertJsonPath('user.role', 'manager')
            ->assertJsonPath('user.branch.code', 'RUBAGA')
            ->assertJsonStructure(['token']);
    }

    public function test_executive_can_login_with_own_access_code(): void
    {
        $executive = User::factory()->executive()->withPin('9001')->create();

        $this->postJson('/api/v1/auth/login', [
            'access_code' => $executive->access_code,
            'pin' => '9001',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $executive->id)
            ->assertJsonPath('user.role', 'executive');
    }

    public function test_admin_and_executive_have_distinct_login_identities(): void
    {
        $admin = User::factory()->platformAdmin()->withPin('1234')->create();
        $executive = User::factory()->executive()->withPin('1234')->create();

        $this->assertNotSame($admin->access_code, $executive->access_code);

        $this->postJson('/api/v1/auth/login', [
            'access_code' => $admin->access_code,
            'pin' => '1234',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.role', 'platform_admin');

        $this->postJson('/api/v1/auth/login', [
            'access_code' => $executive->access_code,
            'pin' => '1234',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $executive->id)
            ->assertJsonPath('user.role', 'executive');
    }

    public function test_access_code_lookup_is_case_insensitive(): void
    {
        $executive = User::factory()->executive()->withPin('1234')->create();

        $this->postJson('/api/v1/auth/login', [
            'access_code' => strtolower((string) $executive->access_code),
            'pin' => '1234',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $executive->id);
    }

    public function test_login_fails_with_wrong_pin(): void
    {
        $branch = Branch::factory()->create(['code' => 'RUBAGA']);
        User::factory()->manager($branch)->withPin('4821')->create();

        $this->postJson('/api/v1/auth/login', [
            'access_code' => 'RUBAGA',
            'pin' => '0000',
        ])->assertStatus(401);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $branch = Branch::factory()->create(['code' => 'RUBAGA']);
        User::factory()->manager($branch)->inactive()->withPin('4821')->create();

        $this->postJson('/api/v1/auth/login', [
            'access_code' => 'RUBAGA',
            'pin' => '4821',
        ])->assertStatus(401);
    }

    public function test_login_requires_valid_payload(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'access_code' => '',
            'pin' => '1',
        ])->assertStatus(422)->assertJsonValidationErrors(['access_code', 'pin']);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        $this->actingAs($manager)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $manager->id)
            ->assertJsonPath('data.role', 'manager');
    }

    public function test_user_can_change_pin(): void
    {
        $user = User::factory()->executive()->withPin('1234')->create();

        $this->actingAs($user)
            ->postJson('/api/v1/auth/pin', [
                'current_pin' => '1234',
                'pin' => '567890',
                'pin_confirmation' => '567890',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('567890', $user->pin_hash));
    }

    public function test_change_pin_rejects_wrong_current_pin(): void
    {
        $user = User::factory()->executive()->withPin('1234')->create();

        $this->actingAs($user)
            ->postJson('/api/v1/auth/pin', [
                'current_pin' => '9999',
                'pin' => '5678',
                'pin_confirmation' => '5678',
            ])
            ->assertStatus(422);
    }

    public function test_user_can_change_pin_with_access_code_and_no_email_verification(): void
    {
        $branch = Branch::factory()->create(['code' => 'RUBAGA']);
        $manager = User::factory()->manager($branch)->withPin('1234')->create();

        $this->postJson('/api/v1/auth/change-pin', [
            'access_code' => 'RUBAGA',
            'current_pin' => '1234',
            'pin' => '9876',
            'pin_confirmation' => '9876',
        ])->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('9876', $manager->fresh()->pin_hash));
    }

    public function test_change_pin_by_code_rejects_wrong_current_pin(): void
    {
        $branch = Branch::factory()->create(['code' => 'RUBAGA']);
        User::factory()->manager($branch)->withPin('1234')->create();

        $this->postJson('/api/v1/auth/change-pin', [
            'access_code' => 'RUBAGA',
            'current_pin' => '0000',
            'pin' => '9876',
            'pin_confirmation' => '9876',
        ])->assertStatus(422);
    }

    public function test_change_pin_by_code_requires_confirmation_match(): void
    {
        $branch = Branch::factory()->create(['code' => 'RUBAGA']);
        User::factory()->manager($branch)->withPin('1234')->create();

        $this->postJson('/api/v1/auth/change-pin', [
            'access_code' => 'RUBAGA',
            'current_pin' => '1234',
            'pin' => '9876',
            'pin_confirmation' => '1111',
        ])->assertStatus(422)->assertJsonValidationErrors('pin');
    }
}
