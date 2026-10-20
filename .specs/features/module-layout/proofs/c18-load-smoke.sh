#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c18
snap() { (cd "$BUILD/backend/bootstrap/cache" && find . -type f -exec shasum {} + 2>/dev/null | sort); }
snap > "$EVID/c18-cache.before"
(cd "$BUILD" && make load-test TYPE=smoke > "$EVID/c18-load-smoke.txt" 2>&1); code=$?
(cd "$BUILD" && make load-down >/dev/null 2>&1)
tail -5 "$EVID/c18-load-smoke.txt" | tee -a "$OUT"
snap > "$EVID/c18-cache.after"
[ "$code" = "0" ] || fail "make load-test TYPE=smoke exited $code"
diff "$EVID/c18-cache.before" "$EVID/c18-cache.after" >/dev/null || fail "bootstrap/cache changed during the load run"
say "OK - smoke exit 0, bootstrap/cache unchanged"
