<?php

declare(strict_types=1);

namespace Modules\Catalog\Persistence\ReadModel;

use MongoDB\Collection;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * One write of the projector: an upsert whose filter holds the version rule, so the database decides in one command.
 *
 * When the filter matches nothing, the upsert tries to insert the _id and hits a duplicate key if the document is
 * there. That means one of two things: the stored version is newer (the delivery lost, a discard), or two upserts of
 * a document that did not exist ran at the same time and this one lost the insert (it may well be the newer one).
 * MongoDB retries only the simple equality filters by itself, and this filter is not one, so the write runs a second
 * time: by then the document exists, the filter says which version is newer, and a duplicate key again can only be a
 * discard.
 */
final class ConditionalUpsert
{
    private const DUPLICATE_KEY = 11000;

    /**
     * @param  array<string, mixed>  $filter
     * @param  array<string, mixed>  $update
     * @return bool whether the write changed the document
     */
    public static function apply(Collection $collection, array $filter, array $update): bool
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $result = $collection->updateOne($filter, $update, ['upsert' => true]);

                return $result->getModifiedCount() > 0 || $result->getUpsertedCount() > 0;
            } catch (BulkWriteException $exception) {
                if (! self::isDuplicateKey($exception)) {
                    throw $exception;
                }

                if ($attempt === 2) {
                    return false;
                }
            }
        }
    }

    /** Server error code 11000, read from the write errors and not from the message text. */
    private static function isDuplicateKey(BulkWriteException $exception): bool
    {
        foreach ($exception->getWriteResult()->getWriteErrors() as $error) {
            if ($error->getCode() === self::DUPLICATE_KEY) {
                return true;
            }
        }

        return false;
    }
}
