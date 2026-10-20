<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('branches', 'share_token')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropUnique(['share_token']);
                $table->dropColumn('share_token');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('branches', 'share_token')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('share_token', 64)->nullable()->unique()->after('slug');
            });
        }
    }
};
