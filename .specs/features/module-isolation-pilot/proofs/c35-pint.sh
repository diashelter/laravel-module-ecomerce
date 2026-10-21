#!/usr/bin/env bash
# C35: make pint leaves no file to fix.
source "$(dirname "$0")/lib.sh"; start c35
(cd "$ROOT" && make pint) 2>&1 | strip_ansi > "$EVID/c35-pint.txt"; code=${PIPESTATUS[0]}
tail -2 "$EVID/c35-pint.txt" | tee -a "$OUT"
[ "$code" -eq 0 ] || fail "make pint exited $code"
say "PASS: code style clean"
