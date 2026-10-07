<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per payment attempt (approved or declined). A row is never updated.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: the payment attempts of an order must be kept.
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            // Copy of orders.total_cents at the moment of the attempt, in cents.
            $table->bigInteger('amount_cents');
            $table->string('status', 20);
            $table->string('decline_reason', 40)->nullable();
            $table->string('card_token', 64);
            // Which adapter charged it, so old rows stay readable when the gateway changes.
            $table->string('gateway', 30);
            $table->string('gateway_transaction_id', 100);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_cents_non_negative CHECK (amount_cents >= 0)');
        // A decline reason exists if and only if the attempt was declined.
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_decline_reason_matches_status CHECK ((status = 'declined') = (decline_reason IS NOT NULL))");
        // At most one approved payment per order: a concurrent second approval fails here.
        DB::statement("CREATE UNIQUE INDEX payments_order_id_approved_unique ON payments (order_id) WHERE status = 'approved'");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
