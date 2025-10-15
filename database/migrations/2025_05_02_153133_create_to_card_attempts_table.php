<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('to_card_attempts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('card_number');
            $table->decimal('amount', 65, 30);
            $table->decimal('received_amount',65, 30)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('info_text')->nullable();
            $table->text('info_photo')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('to_card_attempts');
    }
};
