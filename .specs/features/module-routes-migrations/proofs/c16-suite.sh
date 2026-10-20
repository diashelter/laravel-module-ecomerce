#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c16
(cd "$BUILD" && make test 2>&1 | strip_ansi > "$EVID/c16-make-test.txt")
summary=$(grep -E 'Tests:' "$EVID/c16-make-test.txt" | tail -1); say "$summary"
grep -qE '[0-9]+ failed' <<<"$summary" && fail "tests failed: $summary"
[ -n "$summary" ] || fail "no Tests: summary in the output"
count() { strip_ansi < "$1" | awk '/^ +(PASS|FAIL) +Tests/{f=$2; next} /^ +(✓|⨯)/{n[f]++} END{for(k in n)print k, n[k]}' | sort; }
count "$EVID/baseline-make-test.txt" > "$EVID/c16-counts.base"
count "$EVID/c16-make-test.txt" > "$EVID/c16-counts.now"
base_files=$(grep -vc 'Tests.Unit.Architecture' "$EVID/c16-counts.base"); say "baseline files outside Architecture: $base_files"
[ "$base_files" -gt 90 ] || fail "too few baseline files outside Architecture: $base_files"
bad=0
while read -r file n; do
  case "$file" in Tests\\Unit\\Architecture\\*) continue;; esac
  now=$(F="$file" awk '$1==ENVIRON["F"]{print $2}' "$EVID/c16-counts.now"); now=${now:-MISSING}
  [ "$now" = "$n" ] || { say "COUNT MISMATCH $file: baseline=$n now=$now"; bad=1; }
done < "$EVID/c16-counts.base"
[ "$bad" = "0" ] || fail "per-file counts differ from the baseline"
say "OK - 0 failures; all $base_files files outside Architecture keep their baseline count"
