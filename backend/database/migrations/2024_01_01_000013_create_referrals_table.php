<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Referral tracking system.
     * Users can invite others via email; rewards granted when referred user registers.
     */
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->onDelete('cascade');
            $table->string('referred_email');
            $table->boolean('accepted')->default(false); // Has referred user signed up?
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index('referrer_user_id');
            $table->index('referred_email');
            $table->index('accepted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
