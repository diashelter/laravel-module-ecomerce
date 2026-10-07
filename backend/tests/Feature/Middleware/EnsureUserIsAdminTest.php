<?php

use App\Modules\Identity\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

function runAdminMiddleware($member, Closure $next): Response
{
    $request = Request::create('/api/admin/users');
    $request->setUserResolver(fn () => $member);

    return (new EnsureUserIsAdmin)->handle($request, $next);
}

it('lets only the admin role through the admin-only middleware', function () {
    $reached = 0;
    $next = function () use (&$reached) {
        $reached++;

        return new Response('next');
    };

    expect(runAdminMiddleware(admin(), $next)->getContent())->toBe('next')
        ->and($reached)->toBe(1);

    try {
        runAdminMiddleware(support(), $next);
        $this->fail('The support role passed the admin-only middleware.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403)
            ->and($reached)->toBe(1);
    }
});
