<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->renameColumn('beds_occupied', 'rooms_occupied');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->decimal('amount_expected_semester', 15, 2)->nullable()->after('rooms_occupied');
            $table->decimal('collected_so_far', 15, 2)->nullable()->after('amount_expected_semester');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['amount_expected_semester', 'collected_so_far']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->renameColumn('rooms_occupied', 'beds_occupied');
        });
    }
};
