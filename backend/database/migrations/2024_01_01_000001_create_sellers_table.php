<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Sellers represent marketplace vendors with their own storefronts.
     * Each seller is linked to a user account with role=SELLER.
     */
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('store_name');
            $table->boolean('verified')->default(false); // Admin verification status
            $table->decimal('rating', 3, 2)->default(0.00); // Average rating (0-5.00)
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->json('business_info')->nullable(); // Tax ID, legal name, address, etc.
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
