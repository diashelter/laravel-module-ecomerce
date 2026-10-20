#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c15
art route:list --json > "$EVID/route-list.build.json" || fail "route:list failed"
python3 - "$EVID/route-list.main.json" "$EVID/route-list.build.json" <<'PY' | tee -a "$OUT"
import json, sys
def norm(o):
    if isinstance(o, str): return o.replace('App\\Modules\\', 'Modules\\')
    if isinstance(o, list): return [norm(x) for x in o]
    if isinstance(o, dict): return {k: norm(v) for k, v in o.items()}
    return o
key = lambda r: (r['uri'], r['method'], r.get('name') or '')
main = sorted(norm(json.load(open(sys.argv[1]))), key=key)
build = sorted(norm(json.load(open(sys.argv[2]))), key=key)
print(f"main routes: {len(main)}  build routes: {len(build)}")
assert len(main) == 73, f"expected 73 routes on main, found {len(main)}"
bad = 0
for a, b in zip(main, build):
    if a != b:
        bad += 1; print("DIFF", json.dumps(a), "!=", json.dumps(b))
if len(main) != len(build) or bad:
    sys.exit(1)
print("OK - 73 of 73 routes identical after normalizing App\\Modules -> Modules")
PY
exit ${PIPESTATUS[0]}
