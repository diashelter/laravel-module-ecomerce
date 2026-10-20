#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c01
art route:list --json > "$EVID/route-list.build.json" || fail "route:list failed"
python3 - "$EVID/route-list.base.json" "$EVID/route-list.build.json" <<'PY' | tee -a "$OUT"
import json, sys
key = lambda r: (r['uri'], r['method'], r.get('name') or '')
base = sorted(json.load(open(sys.argv[1])), key=key)
build = sorted(json.load(open(sys.argv[2])), key=key)
print(f"base routes: {len(base)}  build routes: {len(build)}")
assert len(base) == 73, f"expected 73 routes at the base, found {len(base)}"
bad = 0
for a, b in zip(base, build):
    if a != b:
        bad += 1; print("DIFF", json.dumps(a), "!=", json.dumps(b))
if len(base) != len(build) or bad:
    sys.exit(1)
print("OK - 73 of 73 routes identical (uri, method, name, action, middleware), order ignored")
PY
exit ${PIPESTATUS[0]}
