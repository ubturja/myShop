<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Gamification events (spin wheel, quiz completions, referrals).
     * Rewards stored as JSON (discount codes, points, badges, etc.).
     */
    public function up(): void
    {
        Schema::create('gamification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // SPIN, QUIZ, REFERRAL_BONUS, DAILY_CHECK_IN, etc.
            $table->json('reward'); // {type: "discount", value: 500, code: "ABC123"} or {type: "points", value: 100}
            $table->boolean('used')->default(false); // Has reward been redeemed?
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('type');
            $table->index('used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gamification_events');
    }
};
