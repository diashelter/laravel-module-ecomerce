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

    /**
     * The customer's address book. Orders do not point here: they keep a copy of the address
     * (see the orders table), so editing or deleting an address never rewrites a past order.
     */
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            // cascadeOnDelete: the book goes away with the account. There is nothing to preserve,
            // because every order already holds its own copy of the delivery address.
            $table->foreignId('customer_id')->index()->constrained()->cascadeOnDelete();
            $table->string('recipient_name', 120);
            $table->char('postal_code', 8);
            $table->string('street', 150);
            $table->string('number', 20);
            $table->string('complement', 100)->nullable();
            $table->string('district', 100);
            $table->string('city', 100);
            $table->char('state', 2);
            $table->timestamps();
        });

        $states = "'".implode("','", self::STATES)."'";

        DB::statement("ALTER TABLE customer_addresses ADD CONSTRAINT customer_addresses_postal_code_digits CHECK (postal_code ~ '^[0-9]{8}$')");
        DB::statement("ALTER TABLE customer_addresses ADD CONSTRAINT customer_addresses_state_known CHECK (state IN ({$states}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
