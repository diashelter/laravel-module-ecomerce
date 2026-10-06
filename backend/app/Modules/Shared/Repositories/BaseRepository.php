<?php

declare(strict_types=1);

namespace App\Modules\Shared\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

    public function count(): int
    {
        return $this->query()->count();
    }
}
