<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            // Money is stored as an exact decimal, never as float.
            $table->decimal('price', 10, 2);
            $table->text('description');
            $table->string('image_url')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index('price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
