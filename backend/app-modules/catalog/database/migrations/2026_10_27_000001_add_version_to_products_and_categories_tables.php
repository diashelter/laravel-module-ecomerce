<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The version of a record (door 3 of the storefront read model): it only grows, one per write, and travels in the
 * events so the projector can tell a repeated or late delivery from a new one. Existing rows get 1 from the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'categories'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->bigInteger('version')->default(1);
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'categories'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
    }
};
