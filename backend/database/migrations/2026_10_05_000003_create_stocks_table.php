<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            // unique() enforces the 1:1 relationship between products and stocks.
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
        });

        // Last line of defense: the database itself rejects negative stock.
        DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_quantity_non_negative CHECK (quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
