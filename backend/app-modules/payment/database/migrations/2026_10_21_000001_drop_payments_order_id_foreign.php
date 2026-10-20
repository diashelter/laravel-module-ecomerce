<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The payments of a database migrated before the module isolation (see .design/module-isolation.md) still
     * point a foreign key at orders: the payment module runs without the ordering module, so the key goes. The
     * creation migration no longer creates it, so on a fresh database there is nothing to drop.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_order_id_foreign');
    }

    /**
     * Nothing: putting the key back would fail on a database without the orders table, or with a payment of
     * an order that does not exist.
     */
    public function down(): void {}
};
