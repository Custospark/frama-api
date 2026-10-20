<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('access_code', 32)->nullable()->unique()->after('email');
        });

        $used = [];

        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $code = match ($user->role) {
                'platform_admin' => 'FRAMA',
                'executive' => 'EXEC',
                default => $user->branch_id
                    ? (string) DB::table('branches')->where('id', $user->branch_id)->value('code')
                    : '',
            };

            $code = strtoupper(trim($code));
            $code = $code !== '' ? $code : 'USER' . $user->id;

            while (isset($used[$code])) {
                $code .= '2';
            }

            $used[$code] = true;

            DB::table('users')->where('id', $user->id)->update(['access_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['access_code']);
            $table->dropColumn('access_code');
        });
    }
};
