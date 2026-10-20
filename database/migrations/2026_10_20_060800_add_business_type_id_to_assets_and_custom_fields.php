<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('business_type_id')->nullable()->after('branch_id')
                ->constrained('business_types')->nullOnDelete();
        });

        Schema::table('custom_fields', function (Blueprint $table) {
            $table->foreignId('business_type_id')->nullable()->after('scope')
                ->constrained('business_types')->cascadeOnDelete();
        });

        // Backfill: map the legacy string slug to a business type id.
        foreach (['room', 'hostel', 'sauna', 'motel'] as $slug) {
            $id = DB::table('business_types')->where('slug', $slug)->value('id');

            if ($id) {
                DB::table('assets')->where('type', $slug)->update(['business_type_id' => $id]);
                DB::table('custom_fields')->where('asset_type', $slug)->update(['business_type_id' => $id]);
            }
        }

        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });

        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropUnique('custom_fields_scope_unique');
            $table->dropIndex(['scope', 'asset_type']);
            $table->dropColumn('asset_type');
        });

        Schema::table('custom_fields', function (Blueprint $table) {
            $table->unique(['scope', 'business_type_id', 'branch_id', 'key'], 'custom_fields_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropUnique('custom_fields_scope_unique');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->string('type', 20)->nullable()->after('branch_id');
        });

        Schema::table('custom_fields', function (Blueprint $table) {
            $table->string('asset_type', 20)->nullable()->after('scope');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_type_id');
        });

        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_type_id');
        });

        Schema::table('custom_fields', function (Blueprint $table) {
            $table->unique(['scope', 'asset_type', 'branch_id', 'key'], 'custom_fields_scope_unique');
            $table->index(['scope', 'asset_type']);
        });
    }
};
