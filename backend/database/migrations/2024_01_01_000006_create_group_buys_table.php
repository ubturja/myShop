<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Group buying campaigns (Pinduoduo-style).
     * Users can create or join group buys to unlock team pricing.
     */
    public function up(): void
    {
        Schema::create('group_buys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('starter_user_id')->constrained('users')->onDelete('cascade'); // User who created the group
            $table->integer('target_size'); // Minimum members needed
            $table->bigInteger('team_price_cents'); // Discounted price when target is met
            $table->bigInteger('solo_price_cents'); // Fallback price if target not met
            $table->enum('status', ['ACTIVE', 'FULFILLED', 'FAILED', 'EXPIRED'])->default('ACTIVE');
            $table->timestamp('expires_at'); // Deadline to reach target
            $table->timestamps();
            
            $table->index('product_id');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_buys');
    }
};
