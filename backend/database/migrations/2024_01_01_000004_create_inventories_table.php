<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tracks stock levels for products at specific stores (quick commerce).
     * Reserved quantity handles concurrent order reservations.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(0); // Available stock
            $table->integer('reserved')->default(0); // Reserved for pending orders
            $table->timestamps();
            
            $table->unique(['product_id', 'store_id']); // One inventory record per product-store combo
            $table->index('store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
