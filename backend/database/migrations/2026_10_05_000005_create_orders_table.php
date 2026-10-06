<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: a customer with orders cannot be removed (order history must be kept).
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('total', 12, 2);
            $table->string('status', 30)->index();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
