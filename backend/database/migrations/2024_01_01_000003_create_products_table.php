<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Products are the core inventory items.
     * price_cents stores price in smallest currency unit (e.g., cents for USD).
     * tags JSON stores searchable attributes for AI embeddings.
     * is_quick_item flags items available for quick commerce delivery.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->bigInteger('price_cents'); // Price in smallest currency unit (e.g., cents)
            $table->string('currency', 3)->default('USD'); // ISO 4217 currency code
            $table->string('sku')->unique();
            $table->string('image_url')->nullable();
            $table->string('category'); // Electronics, Fashion, Food, etc.
            $table->json('tags')->nullable(); // Searchable attributes: [size, color, brand, material, etc.]
            $table->boolean('is_quick_item')->default(false); // Available for quick commerce?
            $table->enum('status', ['ACTIVE', 'DRAFT', 'ARCHIVED'])->default('ACTIVE');
            $table->timestamps();
            
            $table->index('seller_id');
            $table->index('category');
            $table->index('is_quick_item');
            $table->index('status');
            $table->fullText(['title', 'description']); // Full-text search
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
