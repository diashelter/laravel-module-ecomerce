<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The shopper's own account. Staff live in `users`: the two tables are independent, so the
     * same e-mail can exist in both, and a session of one side can never become the other.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Same last line of defense as users: only the canonical form of the Email value object.
        DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_email_normalized CHECK (email = lower(btrim(email)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
