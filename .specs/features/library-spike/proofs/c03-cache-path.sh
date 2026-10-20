#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c03
art modules:clear >/dev/null
msg=$(art modules:cache); say "$msg"
file=$(find "$SPIKE/backend" -path "$SPIKE/backend/vendor" -prune -o -name 'app-modules.php' -print | head -1)
[ -n "$file" ] || fail "modules:cache wrote no app-modules.php under backend/"
say "CACHE_PATH: $file"
art modules:clear >/dev/null
say "OK"
