<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppNotifier
{
    public function notifyReportSubmitted(Report $report): void
    {
        if (! config('services.whatsapp.enabled')) {
            return;
        }

        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $token = config('services.whatsapp.access_token');
        $bossNumber = config('services.whatsapp.boss_number');

        if (! $phoneNumberId || ! $token || ! $bossNumber) {
            return;
        }

        $report->loadMissing('branch');

        $body = sprintf(
            "%s daily report - %s\nRooms: %s\nBar: %s\nFood: %s\nSauna: %s\nTotal: %s",
            $report->branch?->branch_name ?? 'Branch',
            $report->report_date->toDateString(),
            number_format((float) $report->rooms_rev),
            number_format((float) $report->bar_rev),
            number_format((float) $report->food_rev),
            number_format((float) $report->sauna_rev),
            number_format((float) $report->total_rev),
        );

        try {
            Http::withToken($token)
                ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $bossNumber,
                    'type' => 'text',
                    'text' => ['body' => $body],
                ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp notification failed', ['error' => $e->getMessage()]);
        }
    }
}
