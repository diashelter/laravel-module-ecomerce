<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Every API input is validated here and handed to the application as a typed DTO,
 * never as a raw array.
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * Converts the validated input into a typed DTO.
     */
    abstract public function toDto(): object;
}
