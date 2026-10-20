#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c12
expected_file="$EVID/c12-expected-failures.txt"
[ -f "$expected_file" ] || fail "missing $expected_file (one failing test file per line, with the cause)"
(cd "$SPIKE" && make test 2>&1 | strip_ansi > "$EVID/c12-make-test.txt")
summary=$(grep -E 'Tests:' "$EVID/c12-make-test.txt" | tail -1)
say "$summary"
num() { grep -oE "[0-9]+ $1" <<<"$2" | grep -oE '[0-9]+' | head -1; }
passed=$(num passed "$summary"); passed=${passed:-0}
failed=$(num failed "$summary"); failed=${failed:-0}
total=$((passed + failed))
baseline=$(num passed "$(grep -E 'Tests:' "$EVID/baseline-make-test.txt" | strip_ansi | tail -1)")
added=3; lost=75
say "total=$total baseline=$baseline added=$added lost=$lost"
[ "$baseline" = "1928" ] || fail "baseline is $baseline, the check was approved with 1928"
[ "$total" = "$((baseline + added - lost))" ] || fail "total $total != $baseline + $added - $lost"
# per-file counts: PASS/FAIL header, then one line per test or dataset row
count() { strip_ansi < "$1" | awk '/^ +(PASS|FAIL) +Tests/{f=$2; next} /^ +(✓|⨯)/{n[f]++} END{for(k in n)print k, n[k]}' | sort; }
count "$EVID/baseline-make-test.txt" > "$EVID/c12-counts.base"
count "$EVID/c12-make-test.txt" > "$EVID/c12-counts.now"
bad=0
while read -r file now; do
  base=$(F="$file" awk '$1==ENVIRON["F"]{print $2}' "$EVID/c12-counts.base"); base=${base:-0}
  case "$file" in
    *DomainEventsTest) want=$((base - 1));;
    *ModuleBoundariesTest) want=$((base - 74));;
    *Spike\\ModulePaymentRouteTest) want=2;;
    *Spike\\ModulePaymentFactoryTest) want=1;;
    *) want=$base;;
  esac
  [ "$now" = "$want" ] || { say "COUNT MISMATCH $file: now=$now want=$want (baseline $base)"; bad=1; }
done < "$EVID/c12-counts.now"
while read -r file base; do
  F="$file" awk '$1==ENVIRON["F"]{found=1} END{exit !found}' "$EVID/c12-counts.now" || { say "FILE MISSING from the run: $file"; bad=1; }
done < "$EVID/c12-counts.base"
[ "$bad" = "0" ] || fail "per-file counts differ from the approved shape"
# every failure belongs to a file in the expected list
unexpected=0
while read -r line; do
  file=$(sed -E 's/.*Tests\\(Feature|Unit)\\([A-Za-z\\]+).*/\2/; s/.*\\//' <<<"$line")
  grep -qF "$file " "$expected_file" || { say "UNEXPECTED FAILURE in $file"; unexpected=1; }
done < <(grep -E '^ +FAILED +Tests' "$EVID/c12-make-test.txt")
[ "$unexpected" = "0" ] || fail "failures outside the expected list"
say "OK - $failed failures, all in the expected files; per-file counts match baseline except the two named files and the spike tests"
