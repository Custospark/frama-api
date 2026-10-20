<?php

namespace Tests\Feature;

use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use App\Models\Branch;
use App\Models\BusinessType;
use App\Models\CustomField;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['app.report_deadline' => '23:59']);
    }

    public function test_manager_can_create_custom_field(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();
        $type = BusinessType::factory()->create(['name' => 'Room', 'slug' => 'room']);

        $this->actingAs($manager)->postJson('/api/v1/custom-fields', [
            'scope' => 'report',
            'business_type_id' => $type->id,
            'label' => 'Beds Occupied',
            'type' => 'number',
            'is_required' => true,
        ])->assertStatus(201)
            ->assertJsonPath('data.key', 'beds_occupied')
            ->assertJsonPath('data.scope', 'report')
            ->assertJsonPath('data.business_type_id', $type->id);

        $this->assertDatabaseHas('custom_fields', [
            'branch_id' => $branch->id,
            'business_type_id' => $type->id,
            'key' => 'beds_occupied',
            'type' => 'number',
        ]);
    }

    public function test_duplicate_key_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();
        $type = BusinessType::factory()->create(['name' => 'Room', 'slug' => 'room']);

        CustomField::factory()->forBranch($branch->id)->forBusinessType($type)->create([
            'scope' => 'report',
            'key' => 'occupancy',
        ]);

        $this->actingAs($manager)->postJson('/api/v1/custom-fields', [
            'scope' => 'report',
            'business_type_id' => $type->id,
            'key' => 'occupancy',
            'label' => 'Occupancy',
            'type' => 'number',
        ])->assertStatus(422);
    }

    public function test_manager_only_lists_own_branch_fields(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $manager = User::factory()->manager($own)->create();

        CustomField::factory()->forBranch($own->id)->create();
        CustomField::factory()->forBranch($other->id)->create();
        CustomField::factory()->create(['branch_id' => null]);

        $this->actingAs($manager)
            ->getJson('/api/v1/custom-fields')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_report_submission_stores_numeric_custom_values(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        CustomField::factory()->forBranch($branch->id)->create([
            'scope' => 'report',
            'key' => 'beds_occupied',
            'label' => 'Beds Occupied',
            'type' => 'number',
        ]);

        $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => now()->toDateString(),
            'rooms_rev' => 100000,
            'bar_rev' => 0,
            'food_rev' => 0,
            'sauna_rev' => 0,
            'expenses' => 0,
            'cash_handed_over' => 100000,
            'attachments' => [UploadedFile::fake()->image('receipt.jpg')],
            'custom_values' => ['beds_occupied' => 12],
        ])->assertStatus(201)
            ->assertJsonPath('data.custom_values.beds_occupied', 12);

        $report = Report::first();
        $this->assertEquals(12, $report->custom_values['beds_occupied']);
    }

    public function test_required_custom_field_is_enforced(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        CustomField::factory()->forBranch($branch->id)->create([
            'scope' => 'report',
            'key' => 'beds_occupied',
            'label' => 'Beds Occupied',
            'type' => 'number',
            'is_required' => true,
        ]);

        $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => now()->toDateString(),
            'rooms_rev' => 100000,
            'bar_rev' => 0,
            'food_rev' => 0,
            'sauna_rev' => 0,
            'attachments' => [UploadedFile::fake()->image('receipt.jpg')],
        ])->assertStatus(422)->assertJsonValidationErrors('custom_values.beds_occupied');
    }

    public function test_select_options_are_validated(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        CustomField::factory()->forBranch($branch->id)->create([
            'scope' => 'report',
            'key' => 'condition',
            'label' => 'Condition',
            'type' => 'select',
            'options' => [
                ['value' => 'good', 'label' => 'Good'],
                ['value' => 'bad', 'label' => 'Bad'],
            ],
        ]);

        $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => now()->toDateString(),
            'rooms_rev' => 100000,
            'bar_rev' => 0,
            'food_rev' => 0,
            'sauna_rev' => 0,
            'attachments' => [UploadedFile::fake()->image('receipt.jpg')],
            'custom_values' => ['condition' => 'excellent'],
        ])->assertStatus(422)->assertJsonValidationErrors('custom_values.condition');
    }

    public function test_dashboard_aggregates_numeric_custom_fields(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $executive = User::factory()->executive()->create();

        CustomField::factory()->create([
            'scope' => 'report',
            'branch_id' => null,
            'key' => 'beds_occupied',
            'label' => 'Beds Occupied',
            'type' => 'number',
        ]);

        Report::factory()->forBranch($branchA)->onDate(now()->toDateString())->create([
            'custom_values' => ['beds_occupied' => 10],
        ]);
        Report::factory()->forBranch($branchB)->onDate(now()->toDateString())->create([
            'custom_values' => ['beds_occupied' => 25],
        ]);

        $this->actingAs($executive)
            ->getJson('/api/v1/dashboard/summary?date=' . now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.custom_grand_totals.beds_occupied', 35);
    }

    public function test_asset_custom_values_are_stored_per_type(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();
        $type = BusinessType::factory()->create(['name' => 'Room', 'slug' => 'room']);

        CustomField::factory()->forBranch($branch->id)->forBusinessType($type)->create([
            'scope' => 'asset',
            'key' => 'bed_count',
            'label' => 'Bed Count',
            'type' => 'number',
        ]);

        $this->actingAs($manager)->postJson('/api/v1/assets', [
            'business_type_id' => $type->id,
            'name' => 'Room 101',
            'custom_values' => ['bed_count' => 2],
        ])->assertStatus(201)
            ->assertJsonPath('data.custom_values.bed_count', 2);
    }

    public function test_executive_can_manage_org_wide_fields(): void
    {
        $executive = User::factory()->executive()->create();
        $type = BusinessType::factory()->create(['name' => 'Sauna', 'slug' => 'sauna']);

        $this->actingAs($executive)->postJson('/api/v1/custom-fields', [
            'scope' => 'report',
            'business_type_id' => $type->id,
            'label' => 'Sauna Sessions',
            'type' => 'number',
        ])->assertStatus(201)
            ->assertJsonPath('data.branch_id', null);
    }

    public function test_platform_admin_can_manage_org_wide_fields(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $type = BusinessType::factory()->create(['name' => 'Sauna', 'slug' => 'sauna']);

        $this->actingAs($admin)->postJson('/api/v1/custom-fields', [
            'scope' => 'report',
            'business_type_id' => $type->id,
            'label' => 'Sauna Sessions',
            'type' => 'number',
        ])->assertStatus(201)
            ->assertJsonPath('data.branch_id', null);
    }
}
