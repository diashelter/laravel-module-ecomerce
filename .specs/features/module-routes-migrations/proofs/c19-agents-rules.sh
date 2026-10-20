#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c19
f="$BUILD/AGENTS.md"
for needle in "app-modules/<módulo>/routes/api.php" "Route::prefix('api')->middleware('api')" "Route::pattern" "register()" "service provider do módulo dono" "app-modules/<módulo>/database/migrations" "nunca renomeie nem edite uma migration" '`cache`, `jobs` e `features`'; do
  grep -qF -- "$needle" "$f" || fail "AGENTS.md lacks '$needle'"
done
say "OK"
