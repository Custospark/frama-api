<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('report_date');
            $table->decimal('rooms_rev', 15, 2)->default(0);
            $table->decimal('bar_rev', 15, 2)->default(0);
            $table->decimal('food_rev', 15, 2)->default(0);
            $table->decimal('sauna_rev', 15, 2)->default(0);
            $table->decimal('total_rev', 15, 2)->default(0);
            $table->string('receipt_image_url', 255)->nullable();
            $table->string('manager_name', 255)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'report_date']);
            $table->index('report_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
