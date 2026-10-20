#!/usr/bin/env bash
# C36: the load smoke passes (make load-test TYPE=smoke), and the run leaves backend/bootstrap/cache as it was.
source "$(dirname "$0")/lib.sh"; start c36
snap() { (cd "$ROOT/backend/bootstrap/cache" && find . -type f -exec shasum {} + 2>/dev/null | sort); }
snap > "$EVID/c36-cache.before"
(cd "$ROOT" && make load-test TYPE=smoke) > "$EVID/c36-load-smoke.txt" 2>&1; code=$?
(cd "$ROOT" && make load-down) >/dev/null 2>&1
snap > "$EVID/c36-cache.after"
tail -5 "$EVID/c36-load-smoke.txt" | strip_ansi | tee -a "$OUT"
[ "$code" -eq 0 ] || fail "make load-test TYPE=smoke exited $code"
diff "$EVID/c36-cache.before" "$EVID/c36-cache.after" >/dev/null || fail "bootstrap/cache changed during the load run"
say "PASS: smoke exit 0, bootstrap/cache unchanged"
