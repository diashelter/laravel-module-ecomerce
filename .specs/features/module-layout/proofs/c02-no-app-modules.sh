#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c02
[ ! -d "$BUILD/backend/app/Modules" ] || fail "backend/app/Modules still exists"
needle='App.\{1,2\}Modules'
hits=$(cd "$BUILD" && grep -rIlE "$needle" backend load-tests .github --include='*.php' --include='*.json' --include='*.xml' --include='*.yml' --include='*.yaml' --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=storage --exclude-dir=results --exclude-dir=cache 2>/dev/null | grep -v 'ModulePackagesTest.php' || true)
say "files naming the old namespace: ${hits:-none}"
[ -z "$hits" ] || fail "App\\Modules is still named in: $hits"
say "OK"
