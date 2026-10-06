<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: a customer with orders cannot be removed (order history must be kept).
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Money is stored as an integer amount of cents.
            $table->bigInteger('total_cents');
            $table->string('status', 30)->index();
            $table->timestamps();

            $table->index('created_at');
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_cents_non_negative CHECK (total_cents >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
