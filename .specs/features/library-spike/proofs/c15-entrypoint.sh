#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c15
link="$SPIKE/backend/vendor/modules/payment"
[ -L "$link" ] || fail "precondition: the symlink must exist before the experiment"
[ -f "$SPIKE/backend/vendor/autoload.php" ] || fail "precondition: vendor/autoload.php must exist"
rm "$link"
trap '(cd "$SPIKE" && docker compose run --rm --no-deps -T api composer install --no-interaction --no-progress >/dev/null 2>&1)' EXIT
res=$(cd "$SPIKE" && docker compose run --rm --no-deps -T api sh -c 'if [ -L vendor/modules/payment ]; then echo present; else echo absent; fi' 2>"$EVID/c15-container-stderr.txt" | tail -1)
say "symlink after the entrypoint ran: $res"
case "$res" in
  present) say "ANSWER: yes - the entrypoint installed the package";;
  absent)  say "ANSWER: no - INSTALL_DEPENDENCIES only installs when vendor/autoload.php is missing; an existing checkout needs a manual composer install";;
  *) fail "no definite answer ('$res')";;
esac
say "OK"
