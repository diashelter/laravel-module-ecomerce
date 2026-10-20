<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per paid order the fulfillment module scheduled. The unique order_id is what makes a
     * redelivered OrderPaid (or a retried DeliverOrder) recognisable: the event is announced only
     * by the write that created the row or filled delivered_at.
     */
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // restrictOnDelete, as payments: an order that has a delivery cannot be deleted.
            $table->foreignUuid('order_id')->unique()->constrained()->restrictOnDelete();
            // The first estimate wins: written when the row is created and never updated.
            $table->date('estimated_delivery_on');
            // Null until the carrier delivers; filled once. A filled value is the delivered state.
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
