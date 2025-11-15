<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Orders represent completed purchases.
     * fulfillment_type: STANDARD (shipping), QUICK_LOCAL (micro-fulfillment), PICKUP, GROUP_BUY
     * tx_hash stores blockchain transaction reference if provenance is anchored.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('seller_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('set null'); // For quick commerce
            $table->bigInteger('total_cents'); // Total price in cents
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['PENDING', 'CONFIRMED', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED', 'REFUNDED'])->default('PENDING');
            $table->enum('fulfillment_type', ['STANDARD', 'QUICK_LOCAL', 'PICKUP', 'GROUP_BUY'])->default('STANDARD');
            $table->integer('eta')->nullable(); // Estimated time of arrival in minutes (for quick commerce)
            $table->string('tx_hash')->nullable(); // Blockchain transaction hash (provenance)
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('seller_id');
            $table->index('store_id');
            $table->index('status');
            $table->index('fulfillment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
