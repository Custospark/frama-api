<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BusinessType;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $businessTypes = collect([
            ['name' => 'Room', 'slug' => 'room', 'sort_order' => 0],
            ['name' => 'Hostel', 'slug' => 'hostel', 'sort_order' => 1],
            ['name' => 'Sauna', 'slug' => 'sauna', 'sort_order' => 2],
            ['name' => 'Motel', 'slug' => 'motel', 'sort_order' => 3],
        ])->mapWithKeys(fn ($type) => [
            $type['slug'] => BusinessType::firstOrCreate(
                ['slug' => $type['slug']],
                ['name' => $type['name'], 'sort_order' => $type['sort_order'], 'is_active' => true],
            ),
        ]);

        $rubaga = Branch::firstOrCreate(
            ['code' => 'RUBAGA'],
            ['branch_name' => 'Rubaga', 'manager_name' => 'Rubaga Manager', 'business_type_id' => $businessTypes['room']->id, 'is_active' => true],
        );

        $kibuya = Branch::firstOrCreate(
            ['code' => 'KIBUYA'],
            ['branch_name' => 'Kibuya', 'manager_name' => 'Kibuya Manager', 'business_type_id' => $businessTypes['hostel']->id, 'is_active' => true],
        );

        $seededUsers = [
            [
                'email' => 'admin@framainvestments.com',
                'name' => 'Platform Admin',
                'role' => 'platform_admin',
                'branch_id' => null,
                'access_code' => 'FRAMA',
                'pin' => env('FRAMA_ADMIN_PIN', '1234'),
            ],
            [
                'email' => 'executive@framainvestments.com',
                'name' => 'Executive',
                'role' => 'executive',
                'branch_id' => null,
                'access_code' => 'EXEC',
                'pin' => '1234',
            ],
            [
                'email' => 'rubaga@framainvestments.com',
                'name' => 'Rubaga Manager',
                'role' => 'manager',
                'branch_id' => $rubaga->id,
                'access_code' => $rubaga->code,
                'pin' => '1234',
            ],
            [
                'email' => 'kibuya@framainvestments.com',
                'name' => 'Kibuya Manager',
                'role' => 'manager',
                'branch_id' => $kibuya->id,
                'access_code' => $kibuya->code,
                'pin' => '1234',
            ],
        ];

        foreach ($seededUsers as $seeded) {
            $user = User::firstOrCreate(
                ['email' => $seeded['email']],
                [
                    'name' => $seeded['name'],
                    'role' => $seeded['role'],
                    'branch_id' => $seeded['branch_id'],
                    'pin_hash' => Hash::make($seeded['pin']),
                    'is_active' => true,
                ],
            );

            $user->forceFill([
                'access_code' => strtoupper((string) $seeded['access_code']),
                'pin_hash' => Hash::make($seeded['pin']),
            ])->save();
        }

        $this->seedDefaultReportFields($businessTypes);
    }

    /**
     * Org-wide report custom fields per business type, grounded in Ugandan
     * hospitality operations. Includes number, text, select, checkbox and
     * calculated (derived) fields — the calculated ones use lazy if() guards
     * so division by zero is safe when a manager has not filled the inputs.
     */
    private function seedDefaultReportFields($businessTypes): void
    {
        $defaults = [
            // ── Room ────────────────────────────────────────────────────────
            ['slug' => 'room', 'key' => 'rooms_occupied', 'label' => 'Rooms Occupied', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'rooms_available', 'label' => 'Rooms Available', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'rooms_dirty', 'label' => 'Rooms Awaiting Cleaning', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'check_ins', 'label' => 'Check-ins Today', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'check_outs', 'label' => 'Check-outs Today', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'corporate_bookings', 'label' => 'Corporate / Per-diem Bookings', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'walk_in_guests', 'label' => 'Walk-in Guests', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'room_service_sales', 'label' => 'Room Service Sales (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'laundry_sales', 'label' => 'Laundry Sales (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'extra_bed_sales', 'label' => 'Extra Bed Sales (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'cash_sales', 'label' => 'Cash Taken (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'mtn_mobile_money', 'label' => 'MTN Mobile Money (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'airtel_money', 'label' => 'Airtel Money (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'card_payments', 'label' => 'Card Payments (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'tips_ugx', 'label' => 'Tips Received (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'generator_hours', 'label' => 'Generator Hours Run', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'staff_on_duty', 'label' => 'Staff on Duty', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'room', 'key' => 'efris_invoice_no', 'label' => 'EFRIS Invoice No.', 'type' => 'text', 'placeholder' => 'e.g. EFRIS-2026-00123', 'help_text' => 'Uganda Revenue Authority electronic receipt number for the day.'],
            ['slug' => 'room', 'key' => 'currency_used', 'label' => 'Primary Currency', 'type' => 'select', 'options' => [['value' => 'UGX', 'label' => 'Ugandan Shilling (UGX)'], ['value' => 'USD', 'label' => 'US Dollar (USD)']], 'help_text' => 'Most rooms are billed in UGX; USD is common for embassies and NGOs.'],
            ['slug' => 'room', 'key' => 'all_rooms_ready', 'label' => 'All Rooms Ready for Guests', 'type' => 'checkbox', 'placeholder' => 'Yes — every sellable room is clean and available'],
            ['slug' => 'room', 'key' => 'incidents', 'label' => 'Incidents / Handover Notes', 'type' => 'textarea', 'placeholder' => 'e.g. Room 12 AC under repair, guard changed at 18:00'],
            ['slug' => 'room', 'key' => 'occupancy_rate', 'label' => 'Occupancy Rate (%)', 'type' => 'calculated', 'help_text' => 'Rooms occupied divided by rooms available.', 'validation' => ['formula' => 'if(rooms_available>0, rooms_occupied/rooms_available*100, 0)', 'precision' => 1]],
            ['slug' => 'room', 'key' => 'total_room_sales', 'label' => 'Total Room-related Sales (UGX)', 'type' => 'calculated', 'help_text' => 'Room revenue plus room service, laundry and extra beds.', 'validation' => ['formula' => 'rooms_rev + room_service_sales + laundry_sales + extra_bed_sales', 'precision' => 0]],
            ['slug' => 'room', 'key' => 'revpar', 'label' => 'Revenue Per Available Room (UGX)', 'type' => 'calculated', 'help_text' => 'Room revenue (incl. room service) divided by rooms available.', 'validation' => ['formula' => 'if(rooms_available>0, (rooms_rev + room_service_sales)/rooms_available, 0)', 'precision' => 0]],
            ['slug' => 'room', 'key' => 'total_payments', 'label' => 'Total Payments Received (UGX)', 'type' => 'calculated', 'help_text' => 'Cash, MTN Mobile Money, Airtel Money and card combined.', 'validation' => ['formula' => 'cash_sales + mtn_mobile_money + airtel_money + card_payments', 'precision' => 0]],
            ['slug' => 'room', 'key' => 'mobile_money_share', 'label' => 'MTN Mobile Money Share of Payments (%)', 'type' => 'calculated', 'help_text' => 'Share of the day\u2019s payments settled on MTN Mobile Money.', 'validation' => ['formula' => 'if(total_payments>0, mtn_mobile_money/total_payments*100, 0)', 'precision' => 1]],

            // ── Hostel ──────────────────────────────────────────────────────
            ['slug' => 'hostel', 'key' => 'beds_occupied', 'label' => 'Beds Occupied', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'hostel', 'key' => 'beds_available', 'label' => 'Beds Available', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'hostel', 'key' => 'male_beds_occupied', 'label' => 'Male Beds Occupied', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'hostel', 'key' => 'female_beds_occupied', 'label' => 'Female Beds Occupied (Ladies Wing)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'hostel', 'key' => 'day_pass_guests', 'label' => 'Day-pass Guests', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'hostel', 'key' => 'locker_rentals', 'label' => 'Locker Rentals', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'hostel', 'key' => 'laundry_bags', 'label' => 'Laundry Bags Handled', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'hostel', 'key' => 'visitors_logged', 'label' => 'Day Visitors Logged', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'hostel', 'key' => 'cash_sales', 'label' => 'Cash Taken (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'hostel', 'key' => 'mtn_mobile_money', 'label' => 'MTN Mobile Money (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'hostel', 'key' => 'airtel_money', 'label' => 'Airtel Money (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'hostel', 'key' => 'generator_hours', 'label' => 'Generator Hours Run', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'hostel', 'key' => 'efris_invoice_no', 'label' => 'EFRIS Invoice No.', 'type' => 'text', 'placeholder' => 'e.g. EFRIS-2026-00456'],
            ['slug' => 'hostel', 'key' => 'incidents', 'label' => 'Incidents / Handover Notes', 'type' => 'textarea', 'placeholder' => 'e.g. Locker block B padlock changed, water tank filled at 07:00'],
            ['slug' => 'hostel', 'key' => 'bed_occupancy_rate', 'label' => 'Bed Occupancy Rate (%)', 'type' => 'calculated', 'help_text' => 'Beds occupied divided by beds available.', 'validation' => ['formula' => 'if(beds_available>0, beds_occupied/beds_available*100, 0)', 'precision' => 1]],
            ['slug' => 'hostel', 'key' => 'female_share_pct', 'label' => 'Female Occupancy Share (%)', 'type' => 'calculated', 'help_text' => 'Female beds as a share of all occupied beds.', 'validation' => ['formula' => 'if(beds_occupied>0, female_beds_occupied/beds_occupied*100, 0)', 'precision' => 1]],
            ['slug' => 'hostel', 'key' => 'total_payments', 'label' => 'Total Payments Received (UGX)', 'type' => 'calculated', 'help_text' => 'Cash, MTN Mobile Money and Airtel Money combined.', 'validation' => ['formula' => 'cash_sales + mtn_mobile_money + airtel_money', 'precision' => 0]],

            // ── Sauna ───────────────────────────────────────────────────────
            ['slug' => 'sauna', 'key' => 'sessions_today', 'label' => 'Sauna Sessions Today', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'sauna', 'key' => 'male_sessions', 'label' => 'Male Sessions', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'sauna', 'key' => 'female_sessions', 'label' => 'Female Sessions', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'sauna', 'key' => 'steam_sessions', 'label' => 'Steam Room Sessions', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'sauna', 'key' => 'traditional_sessions', 'label' => 'Traditional (Bare) Sauna Sessions', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'sauna', 'key' => 'packages_sold', 'label' => 'Packages Sold (e.g. 5-session bundle)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'sauna', 'key' => 'product_sales', 'label' => 'Product Sales — Oils, Towels, Soap (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'sauna', 'key' => 'cash_sales', 'label' => 'Cash Taken (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'sauna', 'key' => 'mtn_mobile_money', 'label' => 'MTN Mobile Money (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'sauna', 'key' => 'airtel_money', 'label' => 'Airtel Money (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'sauna', 'key' => 'equipment_status', 'label' => 'Equipment Status', 'type' => 'select', 'options' => [['value' => 'good', 'label' => 'All good'], ['value' => 'minor', 'label' => 'Minor issue — running'], ['value' => 'down', 'label' => 'Out of service']], 'help_text' => 'Steam generator / heater condition at close.'],
            ['slug' => 'sauna', 'key' => 'incident_reported', 'label' => 'Incident Reported', 'type' => 'checkbox', 'placeholder' => 'Yes — an incident needs follow-up'],
            ['slug' => 'sauna', 'key' => 'incidents', 'label' => 'Incidents / Handover Notes', 'type' => 'textarea', 'placeholder' => 'e.g. Steam generator pressure low, service booked Friday'],
            ['slug' => 'sauna', 'key' => 'female_share_pct', 'label' => 'Female Session Share (%)', 'type' => 'calculated', 'help_text' => 'Female sessions as a share of all sessions.', 'validation' => ['formula' => 'if(sessions_today>0, female_sessions/sessions_today*100, 0)', 'precision' => 1]],
            ['slug' => 'sauna', 'key' => 'steam_share_pct', 'label' => 'Steam Room Share (%)', 'type' => 'calculated', 'help_text' => 'Steam sessions as a share of all sessions.', 'validation' => ['formula' => 'if(sessions_today>0, steam_sessions/sessions_today*100, 0)', 'precision' => 1]],
            ['slug' => 'sauna', 'key' => 'avg_revenue_per_session', 'label' => 'Average Revenue per Session (UGX)', 'type' => 'calculated', 'help_text' => 'Sauna revenue divided by sessions.', 'validation' => ['formula' => 'if(sessions_today>0, sauna_rev/sessions_today, 0)', 'precision' => 0]],
            ['slug' => 'sauna', 'key' => 'total_payments', 'label' => 'Total Payments Received (UGX)', 'type' => 'calculated', 'help_text' => 'Cash, MTN Mobile Money and Airtel Money combined.', 'validation' => ['formula' => 'cash_sales + mtn_mobile_money + airtel_money', 'precision' => 0]],

            // ── Motel ───────────────────────────────────────────────────────
            ['slug' => 'motel', 'key' => 'rooms_occupied', 'label' => 'Rooms Occupied', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'motel', 'key' => 'rooms_available', 'label' => 'Rooms Available', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'motel', 'key' => 'short_stay_bookings', 'label' => 'Short-stay Bookings (per hour)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'motel', 'key' => 'overnight_bookings', 'label' => 'Overnight Bookings', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'motel', 'key' => 'bar_consumption', 'label' => 'In-room Bar Consumption (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'motel', 'key' => 'laundry_sales', 'label' => 'Laundry Sales (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'motel', 'key' => 'cash_sales', 'label' => 'Cash Taken (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'motel', 'key' => 'mtn_mobile_money', 'label' => 'MTN Mobile Money (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'motel', 'key' => 'airtel_money', 'label' => 'Airtel Money (UGX)', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'motel', 'key' => 'generator_hours', 'label' => 'Generator Hours Run', 'type' => 'number', 'validation' => ['min' => 0]],
            ['slug' => 'motel', 'key' => 'incidents', 'label' => 'Incidents / Handover Notes', 'type' => 'textarea', 'placeholder' => 'e.g. CCTV camera 3 offline, reported to technician'],
            ['slug' => 'motel', 'key' => 'occupancy_rate', 'label' => 'Occupancy Rate (%)', 'type' => 'calculated', 'help_text' => 'Rooms occupied divided by rooms available.', 'validation' => ['formula' => 'if(rooms_available>0, rooms_occupied/rooms_available*100, 0)', 'precision' => 1]],
            ['slug' => 'motel', 'key' => 'short_stay_share', 'label' => 'Short-stay Share of Bookings (%)', 'type' => 'calculated', 'help_text' => 'Short-stay bookings as a share of all bookings.', 'validation' => ['formula' => 'if((short_stay_bookings + overnight_bookings)>0, short_stay_bookings/(short_stay_bookings + overnight_bookings)*100, 0)', 'precision' => 1]],
            ['slug' => 'motel', 'key' => 'total_motel_sales', 'label' => 'Total Motel-related Sales (UGX)', 'type' => 'calculated', 'help_text' => 'Room revenue plus in-room bar and laundry.', 'validation' => ['formula' => 'rooms_rev + bar_consumption + laundry_sales', 'precision' => 0]],
            ['slug' => 'motel', 'key' => 'total_payments', 'label' => 'Total Payments Received (UGX)', 'type' => 'calculated', 'help_text' => 'Cash, MTN Mobile Money and Airtel Money combined.', 'validation' => ['formula' => 'cash_sales + mtn_mobile_money + airtel_money', 'precision' => 0]],
        ];

        foreach ($defaults as $index => $field) {
            CustomField::firstOrCreate(
                [
                    'scope' => 'report',
                    'business_type_id' => $businessTypes[$field['slug']]->id,
                    'branch_id' => null,
                    'key' => $field['key'],
                ],
                [
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'help_text' => $field['help_text'] ?? null,
                    'placeholder' => $field['placeholder'] ?? null,
                    'options' => $field['options'] ?? [],
                    'validation' => $field['validation'] ?? [],
                    'is_required' => false,
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }
}
