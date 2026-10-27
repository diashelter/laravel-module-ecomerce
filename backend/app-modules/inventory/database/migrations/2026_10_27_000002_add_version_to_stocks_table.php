<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The version of a stock row (door 3 of the storefront read model): it only grows, one per write, and travels in
 * inventory.stock_changed. Existing rows get 1 from the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->bigInteger('version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
