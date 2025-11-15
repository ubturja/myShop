<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Physical stores for quick commerce (hyperlocal delivery).
     * Each store has geolocation for distance-based queries.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('latitude', 10, 8); // Latitude coordinate
            $table->decimal('longitude', 11, 8); // Longitude coordinate
            $table->text('address');
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->json('opening_hours')->nullable(); // Format: {"monday": "09:00-21:00", ...}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['latitude', 'longitude']); // Geospatial queries
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
