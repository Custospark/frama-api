<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->json('custom_values')->nullable()->after('is_active');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->json('custom_values')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('custom_values');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('custom_values');
        });
    }
};
