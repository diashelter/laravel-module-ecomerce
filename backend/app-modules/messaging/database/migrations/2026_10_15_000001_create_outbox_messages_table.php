<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The transactional outbox: one row per domain event, written in the same transaction as the
     * change it announces and published later by the outbox relay. No foreign key: the payload
     * carries the ids as data, and the retention deletes a row without touching any other table.
     */
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            // A version 7 UUID from the application, also the message id the consumers see.
            $table->uuid('id')->primary();
            // The stable event name, "<module>.<event>" (ordering.order_paid), never the PHP class.
            $table->string('name', 100);
            $table->smallInteger('version');
            // Only scalars and null, under snake_case keys.
            $table->jsonb('payload');
            // The request_id of the Context that recorded it: the 128 characters AssignRequestId accepts.
            $table->string('request_id', 128)->nullable();
            $table->timestamp('occurred_at');
            // Null while pending; the relay fills it once the transport accepted the message.
            $table->timestamp('published_at')->nullable();

            // The retention deletes the published messages by age.
            $table->index('published_at');
        });

        // The relay reads only the pending messages, oldest first.
        DB::statement('CREATE INDEX outbox_messages_pending_index ON outbox_messages (occurred_at, id) WHERE published_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
