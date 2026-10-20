<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->decimal('expenses', 15, 2)->default(0)->after('total_rev');
            $table->decimal('cash_handed_over', 15, 2)->default(0)->after('expenses');
            $table->text('comment')->nullable()->after('cash_handed_over');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['expenses', 'cash_handed_over', 'comment']);
        });
    }
};
