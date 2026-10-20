#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c06
routes="$SPIKE/backend/app-modules/payment/routes/payment-routes.php"
cp "$routes" "$EVID/payment-routes.wrapped.bak"
trap 'cp "$EVID/payment-routes.wrapped.bak" "$routes"' EXIT
cat > "$routes" <<'PHP'
<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentController;

Route::middleware('auth:customer')->group(function (): void {
    Route::post('orders/{order}/payment', [PaymentController::class, 'store'])->middleware('throttle:20,1,payment');
});
PHP
art modules:clear >/dev/null
list=$(art route:list --path=payment --json)
say "$list"
grep -q '"uri":"orders\\/{order}\\/payment"' <<<"$list" || fail "expected the URI orders/{order}/payment without the api prefix"
status=$(art tinker --execute="echo app()->handle(Illuminate\Http\Request::create('/api/orders/0190a2f3-0000-7000-8000-000000000000/payment', 'POST'))->getStatusCode();" | tr -dc '0-9')
say "POST /api/orders/{order}/payment -> $status"
[ "$status" = "404" ] || fail "expected 404 for /api/... without the wrapper, got '$status'"
say "OK"
