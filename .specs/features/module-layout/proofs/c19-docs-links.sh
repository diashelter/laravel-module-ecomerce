#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c19
python3 - "$BUILD" <<'PY' | tee -a "$OUT"
import re, sys, os
root = sys.argv[1]
docs = ['README.md', 'AGENTS.md', 'docs/domain-analysis.md']
bad = 0
for d in docs:
    text = open(os.path.join(root, d)).read()
    for needle in ('backend/app/Modules', 'App\\Modules', 'App\\\\Modules'):
        n = text.count(needle)
        if n: bad += 1; print(f"{d}: {n} occurrences of {needle!r}")
    base = os.path.dirname(os.path.join(root, d))
    for m in re.finditer(r'\]\(([^)\s]+)\)', text):
        target = m.group(1)
        if re.match(r'^(https?:|mailto:|#)', target): continue
        path = target.split('#')[0]
        if not path: continue
        full = os.path.normpath(os.path.join(base, path))
        if not os.path.exists(full):
            bad += 1; print(f"{d}: broken link {target}")
print("OK" if not bad else f"FAIL: {bad} problems")
sys.exit(1 if bad else 0)
PY
exit ${PIPESTATUS[0]}
