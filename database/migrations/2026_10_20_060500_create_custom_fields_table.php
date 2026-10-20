<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20); // asset | report
            $table->string('asset_type', 20)->nullable(); // room | hostel | sauna | motel
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label', 255);
            $table->string('type', 20); // text | textarea | number | date | select | checkbox | file
            $table->text('help_text')->nullable();
            $table->string('placeholder', 255)->nullable();
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable(); // for select: [{value,label}]
            $table->json('validation')->nullable(); // min,max,pattern,max_file_kb,accept,min_length,max_length
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['scope', 'asset_type', 'branch_id', 'key'], 'custom_fields_scope_unique');
            $table->index(['scope', 'asset_type']);
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
