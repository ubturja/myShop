<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_intent_id')->nullable()->after('tx_hash');
            $table->enum('payment_status', [
                'pending',
                'processing',
                'succeeded',
                'failed',
                'canceled',
                'refunded'
            ])->default('pending')->after('payment_intent_id');
            $table->json('payment_method_details')->nullable()->after('payment_status');
            
            // Add index for payment_intent_id lookups (for webhook idempotency)
            $table->index('payment_intent_id', 'idx_orders_payment_intent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_payment_intent');
            $table->dropColumn(['payment_intent_id', 'payment_status', 'payment_method_details']);
        });
    }
};
