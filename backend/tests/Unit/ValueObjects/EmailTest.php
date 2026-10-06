<?php

use App\Modules\Identity\ValueObjects\Email;

it('normalizes the email', function (string $input, string $expected) {
    expect((new Email($input))->value())->toBe($expected);
})->with([
    'non ascii capital' => ['ÉLISA@x.com', 'élisa@x.com'],
    'spaces and capitals' => [' Ana@X.com ', 'ana@x.com'],
    'domain without dot' => ['ana@x', 'ana@x'],
]);

it('refuses an invalid email', function (string $input) {
    new Email($input);
})->with([
    'no at sign' => ['ana'],
    'two at signs' => ['ana@@x.com'],
    'too long' => [str_repeat('a', 64).'@'.str_repeat('b', 62).'.'.str_repeat('c', 62).'.'.str_repeat('d', 62).'.ee'],
])->throws(InvalidArgumentException::class);
