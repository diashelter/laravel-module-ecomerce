#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; ROUND=${ROUND:-1}; start c13-round$ROUND
(cd "$BUILD" && make load-test TYPE=load > "$EVID/c13-load-round$ROUND.txt" 2>&1); code=$?
(cd "$BUILD" && make load-down >/dev/null 2>&1)
tail -40 "$EVID/c13-load-round$ROUND.txt" | strip_ansi | tee -a "$OUT"
[ "$code" = "0" ] || fail "make load-test TYPE=load exited $code (a threshold of lib/baseline.js was crossed)"
say "OK - load exit 0 (p95 thresholds 40/70/2450 ms and 0% unexpected errors hold)"
