#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c15
python3 - "$BUILD" <<'PY' | tee -a "$OUT"
import re, sys
text = open(f"{sys.argv[1]}/load-tests/README.md").read()
assert "mediana" in text and "20 execuções" in text, "the README does not say median of 20 runs"
vals = {}
for key in ("sem", "com"):
    m = re.search(rf"\|\s*{key} `modules:cache`\s*\|\s*([\d.,]+) ms", text)
    assert m, f"no row '{key} `modules:cache`' with a value in ms"
    vals[key] = float(m.group(1).replace(",", "."))
    assert vals[key] > 0, f"{key} is not greater than 0"
print(f"sem modules:cache = {vals['sem']} ms; com modules:cache = {vals['com']} ms")
print("OK")
PY
exit ${PIPESTATUS[0]}
