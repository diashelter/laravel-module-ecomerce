#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c07
art modules:clear >/dev/null
out=$(art route:list --path=orders -vv | strip_ansi)
say "$out" 
block=$(grep -A14 'POST .*api/orders/{order}/payment' <<<"$out")
grep -q 'EnsureFrontendRequestsAreStateful' <<<"$block" || fail "EnsureFrontendRequestsAreStateful not listed for the payment route"
say "OK"
