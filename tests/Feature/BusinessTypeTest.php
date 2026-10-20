<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_user_can_list_business_types(): void
    {
        BusinessType::factory()->count(3)->create();

        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->getJson('/api/v1/business-types')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_guest_cannot_list_business_types(): void
    {
        $this->getJson('/api/v1/business-types')->assertStatus(401);
    }

    public function test_platform_admin_can_create_business_type_with_auto_slug(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/business-types', ['name' => 'Conference Hall'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Conference Hall')
            ->assertJsonPath('data.slug', 'conference_hall');

        $this->assertDatabaseHas('business_types', ['slug' => 'conference_hall']);
    }

    public function test_platform_admin_can_update_business_type(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $type = BusinessType::factory()->create(['name' => 'Room', 'slug' => 'room']);

        $this->actingAs($admin)
            ->putJson("/api/v1/business-types/{$type->id}", ['name' => 'Deluxe Room', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Deluxe Room')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_platform_admin_can_delete_business_type(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $type = BusinessType::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/business-types/{$type->id}")
            ->assertOk();

        $this->assertDatabaseMissing('business_types', ['id' => $type->id]);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        BusinessType::factory()->create(['name' => 'Room', 'slug' => 'room']);

        $this->actingAs($admin)
            ->postJson('/api/v1/business-types', ['name' => 'Room'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_manager_cannot_create_business_type(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->postJson('/api/v1/business-types', ['name' => 'Nope'])
            ->assertStatus(403);
    }

    public function test_executive_cannot_create_business_type(): void
    {
        $executive = User::factory()->executive()->create();

        $this->actingAs($executive)
            ->postJson('/api/v1/business-types', ['name' => 'Nope'])
            ->assertStatus(403);
    }
}
