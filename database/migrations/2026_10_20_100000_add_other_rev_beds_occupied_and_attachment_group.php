<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->decimal('other_rev', 15, 2)->default(0)->after('sauna_rev');
            $table->unsignedInteger('beds_occupied')->nullable()->after('other_rev');
        });

        Schema::table('report_attachments', function (Blueprint $table) {
            $table->string('group', 20)->default('receipt')->after('report_id');
        });

        // Legacy attachments predate the split; tag them as receipts.
        DB::table('report_attachments')->whereNull('group')->update(['group' => 'receipt']);
    }

    public function down(): void
    {
        Schema::table('report_attachments', function (Blueprint $table) {
            $table->dropColumn('group');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['other_rev', 'beds_occupied']);
        });
    }
};
