<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CustomField;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        // Ensure submissions are never blocked by the deadline during tests.
        config(['app.report_deadline' => '23:59']);
    }

    public function test_manager_can_submit_report_with_attachments(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        $response = $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => now()->toDateString(),
            'rooms_rev' => 120000,
            'bar_rev' => 50000,
            'food_rev' => 30000,
            'sauna_rev' => 20000,
            'other_rev' => 0,
            'expenses' => 25000,
            'cash_handed_over' => 195000,
            'comment' => 'Power outage from 14:00, generator ran all evening.',
            'receipt_photos' => [
                UploadedFile::fake()->image('receipt.jpg'),
                UploadedFile::fake()->create('takings.pdf', 100),
            ],
            'expense_receipts' => [
                UploadedFile::fake()->create('zreport.zip', 200),
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.status', 'Submitted')
            ->assertJsonCount(3, 'data.attachments')
            ->assertJsonCount(2, 'data.receipt_photos')
            ->assertJsonCount(1, 'data.expense_receipts')
            ->assertJsonPath('data.attachments_count', 3);

        $this->assertEquals(220000, $response->json('data.total_rev'));
        $this->assertEquals(25000, $response->json('data.expenses'));
        $this->assertEquals(195000, $response->json('data.cash_handed_over'));
        $this->assertEquals('Power outage from 14:00, generator ran all evening.', $response->json('data.comment'));

        $this->assertDatabaseHas('reports', [
            'branch_id' => $branch->id,
            'user_id' => $manager->id,
            'total_rev' => 220000,
            'expenses' => 25000,
            'cash_handed_over' => 195000,
        ]);

        $this->assertDatabaseHas('report_attachments', [
            'original_name' => 'zreport.zip',
            'is_archive' => true,
        ]);
    }

    public function test_upload_groups_are_required(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => now()->toDateString(),
            'rooms_rev' => 1000,
            'bar_rev' => 0,
            'food_rev' => 0,
            'sauna_rev' => 0,
            'other_rev' => 0,
            'expenses' => 0,
            'cash_handed_over' => 1000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['receipt_photos', 'expense_receipts']);
    }

    public function test_duplicate_submission_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();
        $date = now()->toDateString();

        Report::factory()->forBranch($branch)->onDate($date)->create();

        $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => $date,
            'rooms_rev' => 1000,
            'bar_rev' => 0,
            'food_rev' => 0,
            'sauna_rev' => 0,
            'other_rev' => 0,
            'expenses' => 0,
            'cash_handed_over' => 1000,
            'receipt_photos' => [UploadedFile::fake()->image('receipt.jpg')],
            'expense_receipts' => [UploadedFile::fake()->image('expense.jpg')],
        ])->assertStatus(409);
    }

    public function test_future_date_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => now()->addDay()->toDateString(),
            'rooms_rev' => 1000,
            'bar_rev' => 0,
            'food_rev' => 0,
            'sauna_rev' => 0,
            'other_rev' => 0,
            'expenses' => 0,
            'cash_handed_over' => 1000,
            'receipt_photos' => [UploadedFile::fake()->image('receipt.jpg')],
            'expense_receipts' => [UploadedFile::fake()->image('expense.jpg')],
        ])->assertStatus(422);
    }

    public function test_report_is_locked_after_deadline(): void
    {
        config(['app.report_deadline' => '00:00']);

        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => now()->toDateString(),
            'rooms_rev' => 1000,
            'bar_rev' => 0,
            'food_rev' => 0,
            'sauna_rev' => 0,
            'other_rev' => 0,
            'expenses' => 0,
            'cash_handed_over' => 1000,
            'receipt_photos' => [UploadedFile::fake()->image('receipt.jpg')],
            'expense_receipts' => [UploadedFile::fake()->image('expense.jpg')],
        ])->assertStatus(422);
    }

    public function test_manager_can_download_an_attachment(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();
        $report = Report::factory()->forBranch($branch)->create();
        Storage::disk('public')->put('reports/attachments/receipt.jpg', 'image-bytes');
        $attachment = $report->attachments()->create([
            'original_name' => 'receipt.jpg',
            'path' => 'reports/attachments/receipt.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 11,
            'is_archive' => false,
        ]);

        $this->actingAs($manager)
            ->get("/api/v1/reports/{$report->id}/attachments/{$attachment->id}/download")
            ->assertOk()
            ->assertDownload('receipt.jpg');
    }

    public function test_manager_can_download_all_attachments_as_zip(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();
        $report = Report::factory()->forBranch($branch)->create();
        Storage::disk('public')->put('reports/attachments/receipt.jpg', 'image-bytes');
        $report->attachments()->create([
            'original_name' => 'receipt.jpg',
            'path' => 'reports/attachments/receipt.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 11,
            'is_archive' => false,
        ]);

        $response = $this->actingAs($manager)
            ->get("/api/v1/reports/{$report->id}/attachments/download-all");

        $response->assertOk();
        $this->assertStringContainsString('application/zip', $response->headers->get('content-type'));
    }

    public function test_manager_cannot_download_another_branch_attachment(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $manager = User::factory()->manager($own)->create();
        $report = Report::factory()->forBranch($other)->create();
        Storage::disk('public')->put('reports/attachments/receipt.jpg', 'image-bytes');
        $attachment = $report->attachments()->create([
            'original_name' => 'receipt.jpg',
            'path' => 'reports/attachments/receipt.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 11,
            'is_archive' => false,
        ]);

        $this->actingAs($manager)
            ->get("/api/v1/reports/{$report->id}/attachments/{$attachment->id}/download")
            ->assertStatus(404);
    }

    public function test_manager_only_sees_own_branch_reports(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $manager = User::factory()->manager($own)->create();

        Report::factory()->forBranch($own)->create();
        Report::factory()->forBranch($other)->create();

        $this->actingAs($manager)
            ->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_executive_sees_all_reports(): void
    {
        Report::factory()->count(3)->create();
        $executive = User::factory()->executive()->create();

        $this->actingAs($executive)
            ->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_executive_can_export_csv(): void
    {
        $branch = Branch::factory()->create(['branch_name' => 'Rubaga']);
        Report::factory()->forBranch($branch)->create();
        $executive = User::factory()->executive()->create();

        $response = $this->actingAs($executive)->get('/api/v1/reports/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    public function test_manager_cannot_export(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        $this->actingAs($manager)
            ->getJson('/api/v1/reports/export')
            ->assertStatus(403);
    }

    public function test_submission_with_unguarded_division_formula_returns_validation_error(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        CustomField::factory()->create([
            'scope' => 'report',
            'key' => 'cost_ratio',
            'label' => 'Cost Ratio',
            'type' => 'calculated',
            'validation' => ['formula' => 'expenses / total_rev', 'precision' => 1],
        ]);

        $response = $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => now()->toDateString(),
            'rooms_rev' => 0,
            'bar_rev' => 0,
            'food_rev' => 0,
            'sauna_rev' => 0,
            'other_rev' => 0,
            'expenses' => 0,
            'cash_handed_over' => 0,
            'receipt_photos' => [UploadedFile::fake()->image('receipt.jpg')],
            'expense_receipts' => [UploadedFile::fake()->image('expense.jpg')],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('custom_values');

        $this->assertStringContainsString('cost_ratio', $response->json('errors.custom_values.0'));
    }

    public function test_submission_computes_calculated_fields_from_base_variables(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        CustomField::factory()->create([
            'scope' => 'report',
            'key' => 'cost_ratio',
            'label' => 'Cost Ratio',
            'type' => 'calculated',
            'validation' => ['formula' => 'if(total_rev > 0, expenses / total_rev * 100, 0)', 'precision' => 1],
        ]);

        $response = $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => now()->toDateString(),
            'rooms_rev' => 100000,
            'bar_rev' => 50000,
            'food_rev' => 0,
            'sauna_rev' => 0,
            'other_rev' => 0,
            'expenses' => 25000,
            'cash_handed_over' => 125000,
            'receipt_photos' => [UploadedFile::fake()->image('receipt.jpg')],
            'expense_receipts' => [UploadedFile::fake()->image('expense.jpg')],
        ]);

        $response->assertStatus(201);
        $this->assertEqualsWithDelta(16.7, $response->json('data.custom_values.cost_ratio'), 0.001);
    }

    public function test_hostel_branch_submits_total_and_beds_without_category_breakdown(): void
    {
        $type = \App\Models\BusinessType::create(['name' => 'Hostel', 'slug' => 'hostel']);
        $branch = Branch::factory()->create(['business_type_id' => $type->id]);
        $manager = User::factory()->manager($branch)->create();

        $response = $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => now()->toDateString(),
            'beds_occupied' => 120,
            'total_rev' => 1750000,
            'expenses' => 300000,
            'cash_handed_over' => 1450000,
            'receipt_photos' => [UploadedFile::fake()->image('receipt.jpg')],
            'expense_receipts' => [UploadedFile::fake()->image('expense.jpg')],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.beds_occupied', 120)
            ->assertJsonPath('data.total_rev', 1750000)
            ->assertJsonPath('data.rooms_rev', 0)
            ->assertJsonPath('data.sauna_rev', 0);

        $this->assertDatabaseHas('reports', [
            'branch_id' => $branch->id,
            'beds_occupied' => 120,
            'total_rev' => 1750000,
        ]);
    }

    public function test_motel_branch_requires_category_revenues(): void
    {
        $type = \App\Models\BusinessType::create(['name' => 'Motel', 'slug' => 'motel']);
        $branch = Branch::factory()->create(['business_type_id' => $type->id]);
        $manager = User::factory()->manager($branch)->create();

        $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => now()->toDateString(),
            'receipt_photos' => [UploadedFile::fake()->image('receipt.jpg')],
            'expense_receipts' => [UploadedFile::fake()->image('expense.jpg')],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['rooms_rev', 'food_rev', 'sauna_rev', 'other_rev']);
    }
}
