<?php

use App\Modules\Shared\Messaging\EventOutbox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Outbox\Fixtures\SampleEvent;

/** Records the event through the bound outbox, inside a transaction of its own. */
function recordInTransaction(object $event): void
{
    DB::transaction(fn () => app(EventOutbox::class)->record($event));
}

it('creates the outbox table in the shape of door 1', function () {
    $columns = collect(DB::select(
        "select column_name, data_type, character_maximum_length, is_nullable from information_schema.columns
            where table_schema = current_schema() and table_name = 'outbox_messages' order by ordinal_position",
    ))->map(fn (object $column) => [$column->column_name, $column->data_type, $column->character_maximum_length, $column->is_nullable])->all();

    expect($columns)->toBe([
        ['id', 'uuid', null, 'NO'],
        ['name', 'character varying', 100, 'NO'],
        ['version', 'smallint', null, 'NO'],
        ['payload', 'jsonb', null, 'NO'],
        ['request_id', 'character varying', 128, 'YES'],
        ['occurred_at', 'timestamp without time zone', null, 'NO'],
        ['published_at', 'timestamp without time zone', null, 'YES'],
    ]);

    $indexes = collect(DB::select("select indexname, indexdef from pg_indexes where schemaname = current_schema() and tablename = 'outbox_messages'"))
        ->pluck('indexdef', 'indexname');

    expect($indexes->get('outbox_messages_pending_index'))->toEndWith('USING btree (occurred_at, id) WHERE (published_at IS NULL)')
        ->and($indexes->filter(fn (string $definition) => str_ends_with($definition, 'USING btree (published_at)')))->toHaveCount(1);

    $foreignKeys = DB::selectOne("select count(*) as total from pg_constraint where conrelid = 'outbox_messages'::regclass and contype = 'f'");

    expect($foreignKeys->total)->toBe(0);
});

it('leaves no message when the recording transaction rolls back', function () {
    expect(fn () => DB::transaction(function () {
        app(EventOutbox::class)->record(new SampleEvent);

        throw new RuntimeException('the change failed');
    }))->toThrow(RuntimeException::class, 'the change failed');

    expect(DB::table('outbox_messages')->count())->toBe(0);
});

it('refuses to record an event outside a transaction', function () {
    // Only the RefreshDatabase wrapper is open here.
    expect(DB::transactionLevel())->toBe(1);
    $event = new SampleEvent;

    expect(fn () => app(EventOutbox::class)->record($event))->toThrow(LogicException::class);
    expect(DB::table('outbox_messages')->count())->toBe(0);

    recordInTransaction($event);

    expect(DB::table('outbox_messages')->count())->toBe(1);
});

it('accepts every scalar and null in the payload', function (mixed $value) {
    recordInTransaction(new SampleEvent(['value' => $value]));

    $payload = json_decode(DB::table('outbox_messages')->sole()->payload, true);

    expect($payload)->toHaveKey('value')
        ->and($payload['value'])->toBe($value);
})->with([
    'string' => ['text'],
    'int' => [7],
    'float' => [1.5],
    'bool' => [true],
    'null' => [null],
]);

it('refuses a payload value that is not a scalar or null', function (mixed $value) {
    expect(fn () => recordInTransaction(new SampleEvent(['value' => $value])))->toThrow(InvalidArgumentException::class);

    expect(DB::table('outbox_messages')->count())->toBe(0);
})->with([
    'associative array' => [['key' => 'value']],
    'list' => [['a', 'b']],
    'object' => [CarbonImmutable::parse('2026-10-15 12:00:00')],
]);

it('records the context request id on the message', function () {
    Context::add('request_id', 'trace-outbox-0001');

    recordInTransaction(new SampleEvent);

    expect(DB::table('outbox_messages')->sole()->request_id)->toBe('trace-outbox-0001');
});

it('records a null request id without a context request id', function () {
    Context::add('request_id', 'trace-outbox-0002');
    Context::flush();

    recordInTransaction(new SampleEvent);

    expect(DB::table('outbox_messages')->sole()->request_id)->toBeNull();
});
