<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\BusinessType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_only_own_branch_assets(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $manager = User::factory()->manager($own)->create();

        Asset::factory()->forBranch($own)->count(2)->create();
        Asset::factory()->forBranch($other)->count(3)->create();

        $this->actingAs($manager)
            ->getJson('/api/v1/assets')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_manager_can_create_asset_for_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();
        $type = BusinessType::factory()->create(['name' => 'Room', 'slug' => 'room']);

        $this->actingAs($manager)
            ->postJson('/api/v1/assets', [
                'business_type_id' => $type->id,
                'name' => 'Room 12',
                'unit_price' => 50000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.business_type_id', $type->id)
            ->assertJsonPath('data.name', 'Room 12');
    }

    public function test_manager_cannot_view_other_branch_asset(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $manager = User::factory()->manager($own)->create();
        $foreign = Asset::factory()->forBranch($other)->create();

        $this->actingAs($manager)
            ->getJson("/api/v1/assets/{$foreign->id}")
            ->assertStatus(404);
    }

    public function test_manager_cannot_update_other_branch_asset(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $manager = User::factory()->manager($own)->create();
        $foreign = Asset::factory()->forBranch($other)->create();

        $this->actingAs($manager)
            ->putJson("/api/v1/assets/{$foreign->id}", ['name' => 'Hacked'])
            ->assertStatus(404);
    }

    public function test_manager_can_update_and_delete_own_asset(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();
        $asset = Asset::factory()->forBranch($branch)->create();

        $this->actingAs($manager)
            ->putJson("/api/v1/assets/{$asset->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->actingAs($manager)
            ->deleteJson("/api/v1/assets/{$asset->id}")
            ->assertOk();

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
    }

    public function test_business_type_must_be_valid(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        $this->actingAs($manager)
            ->postJson('/api/v1/assets', ['business_type_id' => 99999, 'name' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('business_type_id');
    }

    public function test_executive_cannot_create_asset(): void
    {
        $executive = User::factory()->executive()->create();

        $this->actingAs($executive)
            ->postJson('/api/v1/assets', ['business_type_id' => 1, 'name' => 'X'])
            ->assertStatus(403);
    }
}
