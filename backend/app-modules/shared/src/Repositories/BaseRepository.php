<?php

declare(strict_types=1);

namespace Modules\Shared\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The only layer that builds Eloquent queries. Concrete repositories extend this class
 * and add the queries specific to their model.
 *
 * @template TModel of Model
 */
abstract class BaseRepository
{
    /** @return class-string<TModel> */
    abstract protected function model(): string;

    /** @return Builder<TModel> */
    protected function query(): Builder
    {
        return $this->model()::query();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->update($attributes);

        return $model;
    }

    /** @param  TModel  $model */
    public function delete(Model $model): void
    {
        $model->delete();
    }

    /**
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(string $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    /**
     * SELECT ... FOR UPDATE on the row: it reads the stored state and keeps the row until the caller's transaction ends,
     * so a write computes its new state (and its version) from the row no other write can change. Must run inside one.
     *
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function lockById(string $id): Model
    {
        return $this->query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Deletes by id with a single DELETE, without reading the row first.
     *
     * @throws ModelNotFoundException when no row has the id
     */
    public function deleteById(string $id): void
    {
        if ($this->query()->whereKey($id)->delete() === 0) {
            throw (new ModelNotFoundException)->setModel($this->model(), [$id]);
        }
    }

    public function count(): int
    {
        return $this->query()->count();
    }
}
