<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('photo_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('status', ['idle', 'capturing', 'review', 'checkout', 'paid', 'completed', 'expired'])->default('idle');
            $table->integer('total_price')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->string('kiosk_label')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photo_sessions');
    }
};
