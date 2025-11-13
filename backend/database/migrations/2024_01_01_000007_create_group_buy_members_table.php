<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tracks users who have joined a group buy.
     * paid flag indicates whether payment has been processed.
     */
    public function up(): void
    {
        Schema::create('group_buy_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_buy_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('joined_at')->useCurrent();
            $table->boolean('paid')->default(false); // Payment processed?
            $table->timestamps();
            
            $table->unique(['group_buy_id', 'user_id']); // User can only join once
            $table->index('group_buy_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_buy_members');
    }
};
