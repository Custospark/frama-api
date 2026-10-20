<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `users` is created before `branches` (see create_branches_table), so the
        // branch FK is added here in a later migration - after branches exists.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('role', 20)->default('manager')->after('email');
            $table->string('pin_hash', 255)->nullable()->after('password');
            $table->boolean('is_active')->default(true)->after('pin_hash');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->foreignId('branch_id')->nullable()->after('role')->constrained('branches')->nullOnDelete();

            $table->index('role');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['role']);
            $table->dropIndex(['branch_id']);
            $table->dropColumn(['role', 'pin_hash', 'is_active', 'last_login_at', 'branch_id']);
        });
    }
};
