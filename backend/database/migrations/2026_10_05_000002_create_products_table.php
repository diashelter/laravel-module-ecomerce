<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            // Money is stored as an integer amount of cents, never as float or decimal.
            $table->bigInteger('price_cents');
            $table->text('description');
            $table->string('image_url')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index('price_cents');
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_cents_non_negative CHECK (price_cents >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
