<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('to_card_attempts', function (Blueprint $table) {
            $table->string('info_photo_path')->nullable()->after('info_photo');
        });

        $disk = Storage::disk(config('tbe-gateway-card.disk', 'local'));

        DB::table('to_card_attempts')
            ->whereNotNull('info_photo')
            ->orderBy('id')
            ->chunkById(100, function ($attempts) use ($disk) {
                foreach ($attempts as $attempt) {
                    $binary = base64_decode($attempt->info_photo);
                    if ($binary === false) {
                        continue;
                    }

                    $path = "gateway-card/to-card-attempts/{$attempt->id}.jpg";
                    $disk->put($path, $binary);

                    DB::table('to_card_attempts')
                        ->where('id', $attempt->id)
                        ->update(['info_photo_path' => $path]);
                }
            });

        Schema::table('to_card_attempts', function (Blueprint $table) {
            $table->dropColumn('info_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('to_card_attempts', function (Blueprint $table) {
            $table->text('info_photo')->nullable();
            $table->dropColumn('info_photo_path');
        });
    }
};
