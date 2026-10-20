#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c14
python3 - "$BUILD" "$ROOT" <<'PY' | tee -a "$OUT"
import re, subprocess, sys
build, root = sys.argv[1], sys.argv[2]
text = open(f"{build}/load-tests/README.md").read()
section = text.split("### Linha de base por transporte", 1)[1].split("\n## ", 1)[0]
rows = [l for l in section.splitlines() if l.startswith("|") and "HEL-12" in l]
assert len(rows) == 1, f"expected one HEL-12 row, found {len(rows)}"
cells = [c.strip() for c in rows[0].strip("|").split("|")]
assert cells[3] == "redis" and cells[4] == "load", f"the row is not redis/load: {cells[3:5]}"
commit = cells[1]
subprocess.run(["git", "-C", build, "cat-file", "-e", commit + "^{commit}"], check=True)
num = lambda s: float(s.replace(".", "").replace(",", ".")) if re.search(r"\d\.\d{3}\b", s) else float(s.replace(",", "."))
now = [float(cells[7]), float(cells[8]), float(cells[9])]
m = re.search(r"p95 de ([\d.,]+) ms na vitrine, ([\d.,]+) ms em `POST /api/orders` e ([\d.,]+) ms em `order_payable_wait`", cells[-1])
assert m, "the last cell does not cite the previous round p95s"
prev = [num(g) for g in m.groups()]
bad = 0
for name, a, b in zip(("vitrine", "POST /api/orders", "order_payable_wait"), now, prev):
    diff = abs(a - b) / min(a, b)
    print(f"{name}: row={a} previous={b} diff={diff:.1%}")
    if diff >= 0.20: bad += 1
print("OK" if not bad else f"FAIL: {bad} p95 differ by 20% or more")
sys.exit(1 if bad else 0)
PY
exit ${PIPESTATUS[0]}
