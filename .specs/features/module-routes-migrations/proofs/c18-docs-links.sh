#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c18
python3 - "$BUILD" <<'PY' | tee -a "$OUT"
import os, re, sys
root = sys.argv[1]
bad = 0
# every mention of routes/api.php must be the path of a module: app-modules/<module>/routes/api.php
files = [os.path.join(root, d) for d in ('README.md', 'AGENTS.md', 'docs/domain-analysis.md')]
for top in ('load-tests', 'backend'):
    for dirpath, dirs, names in os.walk(os.path.join(root, top)):
        dirs[:] = [d for d in dirs if d not in ('vendor', 'node_modules', 'results', 'storage', '.git')]
        files += [os.path.join(dirpath, n) for n in names if n.endswith(('.php', '.md', '.js', '.json', '.yml', '.yaml', '.xml', '.sh'))]
for f in files:
    try:
        text = open(f, encoding='utf-8').read()
    except (UnicodeDecodeError, OSError):
        continue
    for m in re.finditer(r'routes/api\.php', text):
        if not re.search(r'app-modules/[^/\s`]+/$', text[max(0, m.start() - 60):m.start()]):
            bad += 1; print(f"{os.path.relpath(f, root)}: routes/api.php outside a module path: ...{text[max(0, m.start()-40):m.end()+10]!r}")
for d in ('README.md', 'AGENTS.md', 'docs/domain-analysis.md'):
    text = open(os.path.join(root, d)).read()
    base = os.path.dirname(os.path.join(root, d))
    for m in re.finditer(r'\]\(([^)\s]+)\)', text):
        target = m.group(1)
        if re.match(r'^(https?:|mailto:|#)', target): continue
        path = target.split('#')[0]
        if not path: continue
        if not os.path.exists(os.path.normpath(os.path.join(base, path))):
            bad += 1; print(f"{d}: broken link {target}")
print("OK" if not bad else f"FAIL: {bad} problems")
sys.exit(1 if bad else 0)
PY
exit ${PIPESTATUS[0]}
