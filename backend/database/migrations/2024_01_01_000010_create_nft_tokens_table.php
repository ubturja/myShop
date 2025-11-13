<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NFT loyalty tokens minted on Polygon Mumbai.
     * Tracks ownership and metadata URI for each token.
     */
    public function up(): void
    {
        Schema::create('nft_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token_id'); // On-chain token ID
            $table->string('contract_address'); // Smart contract address
            $table->text('metadata_uri'); // IPFS or HTTP URL to token metadata
            $table->string('tx_hash')->nullable(); // Mint transaction hash
            $table->timestamp('minted_at')->useCurrent();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('token_id');
            $table->index('contract_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nft_tokens');
    }
};
