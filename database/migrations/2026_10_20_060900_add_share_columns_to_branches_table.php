<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->unique()->after('code');
        });

        Branch::query()->whereNull('slug')->get()->each(function (Branch $branch) {
            $branch->forceFill([
                'slug' => $branch->slug ?: Branch::generateSlug($branch->branch_name, $branch->id),
            ])->save();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
