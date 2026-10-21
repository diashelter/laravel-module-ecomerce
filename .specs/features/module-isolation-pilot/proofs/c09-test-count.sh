#!/usr/bin/env bash
# C9: every it(/test( declaration of the base still exists by name (at least as many times), in backend/tests or
# backend/app-modules/*/tests, but the one replaced case; and HEAD has at least 627 declarations.
source "$(dirname "$0")/lib.sh"; start c09
REPLACED='ties payments to existing orders and keeps orders that have payments'
# The two cases the checks themselves rename (C28 and C11).
renamed_to() {
    case "$1" in
        "'keeps the fifteen frozen migration names'") echo "'keeps the sixteen frozen migration names'" ;;
        "'lists exactly the ten module packages'") echo "'lists exactly the eleven module packages'" ;;
    esac
}
names_at() { git -C "$ROOT" grep -hE '^\s*(it|test)\(' "$1" -- 'backend/tests/*.php' 'backend/app-modules/*/tests/*.php' | sed -E "s/^\s*(it|test)\(('[^']*'|\"[^\"]*\").*/\2/" | sort; }
names_at "$BASE" > "$EVID/c09-names.base"; names_at HEAD > "$EVID/c09-names.head"
base=$(wc -l < "$EVID/c09-names.base" | tr -d ' '); now=$(wc -l < "$EVID/c09-names.head" | tr -d ' ')
say "declarations at $BASE: $base; at HEAD: $now"
[ "$base" -eq 628 ] || fail "the base should have 628 declarations, it has $base"
missing=$(comm -23 <(uniq -c "$EVID/c09-names.base" | awk '{c=$1; $1=""; print substr($0,2) "\t" c}' | sort) <(uniq -c "$EVID/c09-names.head" | awk '{c=$1; $1=""; print substr($0,2) "\t" c}' | sort) | cut -f1 | sort -u)
lost=""
while IFS= read -r name; do
    [ -z "$name" ] && continue
    b=$(grep -cxF -- "$name" "$EVID/c09-names.base"); h=$(grep -cxF -- "$name" "$EVID/c09-names.head")
    [ "$h" -ge "$b" ] && continue
    [ "$name" = "'$REPLACED'" ] && { say "replaced by C26: $name"; continue; }
    renamed=$(renamed_to "$name")
    if [ -n "$renamed" ] && grep -qxF -- "$renamed" "$EVID/c09-names.head"; then
        say "renamed by the checks: $name -> $renamed"; continue
    fi
    lost="$lost$name ($b -> $h)"$'\n'
done <<< "$missing"
[ -z "$lost" ] || { printf '%s' "$lost" | tee -a "$OUT"; fail "test cases of the base are missing"; }
[ "$now" -ge 627 ] || fail "only $now declarations, fewer than 627"
say "PASS: every case of the base is still there but the replaced one; $now declarations"
