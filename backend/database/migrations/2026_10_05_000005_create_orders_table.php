<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The 27 UFs, spelled out so this migration never changes meaning if the enum does. */
    private const STATES = [
        'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA',
        'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
    ];

    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: a customer with orders cannot be removed (order history must be kept).
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            // Money is stored as an integer amount of cents.
            // total_cents = the sum of the items + shipping_cents.
            $table->bigInteger('total_cents');
            $table->bigInteger('shipping_cents');
            // Business days counted from the payment approval, as quoted when the order was placed.
            $table->smallInteger('delivery_business_days');
            // Set once, when the payment is approved (the Ordering listener of DeliveryScheduled).
            $table->date('estimated_delivery_on')->nullable();
            // A copy of the delivery address, not a reference to the customer's address book:
            // editing or deleting an address must never rewrite an order already placed.
            $table->string('delivery_recipient_name', 120);
            $table->char('delivery_postal_code', 8);
            $table->string('delivery_street', 150);
            $table->string('delivery_number', 20);
            $table->string('delivery_complement', 100)->nullable();
            $table->string('delivery_district', 100);
            $table->string('delivery_city', 100);
            $table->char('delivery_state', 2);
            $table->string('status', 30)->index();
            $table->timestamps();

            $table->index('created_at');
        });

        $states = "'".implode("','", self::STATES)."'";

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_cents_non_negative CHECK (total_cents >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_shipping_cents_non_negative CHECK (shipping_cents >= 0)');
        // Named to sort after orders_total_cents_non_negative, so a negative total still reports that one.
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_cents_not_below_shipping CHECK (total_cents >= shipping_cents)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_delivery_business_days_positive CHECK (delivery_business_days > 0)');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_postal_code_digits CHECK (delivery_postal_code ~ '^[0-9]{8}$')");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_state_known CHECK (delivery_state IN ({$states}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
