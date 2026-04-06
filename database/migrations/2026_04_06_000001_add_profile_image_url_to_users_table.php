<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_image_url', 2048)->nullable()->after('email');
        });

        DB::table('users')
            ->select(['id', 'first_name', 'last_name'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $name = trim(sprintf('%s %s', (string) $user->first_name, (string) $user->last_name));

                    if ($name === '') {
                        $name = 'User';
                    }

                    DB::table('users')
                        ->where('id', $user->id)
                        ->whereNull('profile_image_url')
                        ->update([
                            'profile_image_url' => sprintf(
                                'https://ui-avatars.com/api/?background=0D8ABC&color=fff&name=%s',
                                rawurlencode($name)
                            ),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_image_url');
        });
    }
};
