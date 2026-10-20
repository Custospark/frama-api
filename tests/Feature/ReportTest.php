<?php

namespace Tests\Feature;

use App\Models\Branch;
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
            'expenses' => 25000,
            'cash_handed_over' => 195000,
            'comment' => 'Power outage from 14:00, generator ran all evening.',
            'attachments' => [
                UploadedFile::fake()->image('receipt.jpg'),
                UploadedFile::fake()->create('takings.pdf', 100),
                UploadedFile::fake()->create('zreport.zip', 200),
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.status', 'Submitted')
            ->assertJsonCount(3, 'data.attachments')
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

    public function test_attachments_are_required(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager($branch)->create();

        $this->actingAs($manager)->post('/api/v1/reports', [
            'report_date' => now()->toDateString(),
            'rooms_rev' => 1000,
            'bar_rev' => 0,
            'food_rev' => 0,
            'sauna_rev' => 0,
            'expenses' => 0,
            'cash_handed_over' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors('attachments');
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
            'expenses' => 0,
            'cash_handed_over' => 1000,
            'attachments' => [UploadedFile::fake()->image('receipt.jpg')],
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
            'expenses' => 0,
            'cash_handed_over' => 1000,
            'attachments' => [UploadedFile::fake()->image('receipt.jpg')],
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
            'expenses' => 0,
            'cash_handed_over' => 1000,
            'attachments' => [UploadedFile::fake()->image('receipt.jpg')],
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
}
