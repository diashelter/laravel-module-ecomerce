<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // restrictOnDelete: products present in past orders cannot be deleted.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Historical snapshot: later product changes must not affect past orders.
            $table->string('product_name');
            $table->bigInteger('unit_price_cents');
            $table->unsignedInteger('quantity');
            $table->bigInteger('subtotal_cents');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_unit_price_cents_non_negative CHECK (unit_price_cents >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_subtotal_cents_non_negative CHECK (subtotal_cents >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
