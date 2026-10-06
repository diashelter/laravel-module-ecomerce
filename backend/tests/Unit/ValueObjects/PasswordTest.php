<?php

use App\Modules\Identity\ValueObjects\Password;

it('refuses a short password without echoing it', function () {
    try {
        new Password('abc1234');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())->not->toContain('abc1234');

        return;
    }

    $this->fail('A 7-character password must be refused.');
});

it('accepts the minimum length and reveals the text explicitly', function () {
    expect((new Password('abcd1234'))->reveal())->toBe('abcd1234');
});

it('never reveals the plain password', function (string $channel) {
    $password = new Password('segredo-123');

    $output = match ($channel) {
        'json_encode' => json_encode($password),
        'serialize' => rescue(fn () => serialize($password), fn (Throwable $e) => $e->getMessage(), false),
        'var_export' => var_export($password, true),
        'print_r' => print_r($password, true),
        'var_dump' => (function () use ($password) {
            ob_start();
            var_dump($password);

            return ob_get_clean();
        })(),
        'string cast' => null,
    };

    if ($channel === 'string cast') {
        expect(fn () => (string) $password)->toThrow(Error::class);

        return;
    }

    expect($output)->not->toContain('segredo-123');
})->with(['json_encode', 'serialize', 'var_export', 'print_r', 'var_dump', 'string cast']);

it('keeps the plain password out of exception traces', function () {
    $failing = function (#[SensitiveParameter] string $plain) {
        $password = new Password($plain);

        throw new RuntimeException('boom '.strlen($password->reveal()));
    };

    try {
        $failing('segredo-123');
    } catch (RuntimeException $exception) {
        expect(json_encode($exception->getTrace()))->not->toContain('segredo-123')
            ->and($exception->getTraceAsString())->not->toContain('segredo-123');

        return;
    }

    $this->fail('The closure must throw.');
});

it('keeps the plain password out of exception traces when the constructor refuses it', function () {
    // No sensitive marker in the test itself: only Password's own constructor can hide the argument.
    try {
        new Password('segr-12');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getTrace()[0]['class'])->toBe(Password::class)
            ->and(json_encode($exception->getTrace()))->not->toContain('segr-12')
            ->and($exception->getTraceAsString())->not->toContain('segr-12');

        return;
    }

    $this->fail('A 7-character password must be refused.');
});
