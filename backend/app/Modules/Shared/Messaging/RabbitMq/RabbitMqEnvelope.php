<?php

declare(strict_types=1);

namespace App\Modules\Shared\Messaging\RabbitMq;

use App\Modules\Shared\Messaging\EventEnvelope;
use Carbon\CarbonImmutable;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use stdClass;
use Throwable;
use UnexpectedValueException;

/**
 * The envelope as a RabbitMQ message and back: the published format of `domain_events`. A persistent
 * `application/json` message whose properties repeat the id and the event name and whose body holds
 * exactly `id`, `name`, `version`, `payload`, `occurred_at` and `request_id`.
 */
final class RabbitMqEnvelope
{
    private const OCCURRED_AT_FORMAT = 'Y-m-d\TH:i:s.u\Z';

    private const BODY_KEYS = ['id', 'name', 'version', 'payload', 'occurred_at', 'request_id'];

    public static function toMessage(EventEnvelope $envelope): AMQPMessage
    {
        return new AMQPMessage(json_encode([
            'id' => $envelope->id,
            'name' => $envelope->name,
            'version' => $envelope->version,
            'payload' => $envelope->payload === [] ? new stdClass : $envelope->payload,
            'occurred_at' => $envelope->occurredAt->utc()->format(self::OCCURRED_AT_FORMAT),
            'request_id' => $envelope->requestId,
        ], JSON_THROW_ON_ERROR), [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $envelope->id,
            'type' => $envelope->name,
        ]);
    }

    /** @throws UnexpectedValueException when the body is not a JSON object with the keys of the format */
    public static function fromMessage(AMQPMessage $message): EventEnvelope
    {
        try {
            $body = json_decode($message->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedValueException('The message body is not JSON: '.$e->getMessage(), previous: $e);
        }

        if (! is_array($body) || ! self::hasExactly($body)) {
            throw new UnexpectedValueException('The message body must be a JSON object with the keys '.implode(', ', self::BODY_KEYS).'.');
        }

        if (! is_string($body['id']) || ! is_string($body['name']) || ! is_int($body['version'])
            || ! is_array($body['payload']) || ! is_string($body['occurred_at'])
            || ($body['request_id'] !== null && ! is_string($body['request_id']))) {
            throw new UnexpectedValueException('The message body has a value of the wrong type.');
        }

        try {
            $occurredAt = CarbonImmutable::parse($body['occurred_at']);
        } catch (Throwable $e) {
            throw new UnexpectedValueException('The message occurred_at is not a date.', previous: $e);
        }

        return new EventEnvelope($body['id'], $body['name'], $body['version'], $body['payload'], $occurredAt, $body['request_id']);
    }

    /** @param  array<mixed>  $body */
    private static function hasExactly(array $body): bool
    {
        $keys = array_keys($body);
        sort($keys);
        $expected = self::BODY_KEYS;
        sort($expected);

        return $keys === $expected;
    }
}
