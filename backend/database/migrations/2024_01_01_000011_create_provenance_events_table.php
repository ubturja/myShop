<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Supply chain provenance events (manufacture, shipment, quality check, etc.).
     * Metadata is uploaded to IPFS and hash is optionally anchored to blockchain.
     */
    public function up(): void
    {
        Schema::create('provenance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // MANUFACTURED, SHIPPED, QUALITY_CHECK, CUSTOMS, etc.
            $table->json('data'); // Event-specific data (location, timestamp, inspector, etc.)
            $table->string('ipfs_hash')->nullable(); // IPFS CID for metadata
            $table->string('tx_hash')->nullable(); // Blockchain transaction hash
            $table->timestamps();
            
            $table->index('product_id');
            $table->index('event_type');
            $table->index('ipfs_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provenance_events');
    }
};
