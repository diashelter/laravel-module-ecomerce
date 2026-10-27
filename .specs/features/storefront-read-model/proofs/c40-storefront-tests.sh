#!/bin/sh
# C40: the storefront cases of six test files pass against the read model, and the files differ from the base commit
# (82337f0) only by the call of the rebuild in the setup, the case that read `stocks` (gone, C49 replaces it) and the
# case that counts the queries of the page (now MongoDB reads, C48). No expectation of status or body changed.
# Run from the repository root.
set -eu

BASE=82337f0

python3 - "$BASE" <<'PY'
import re, subprocess, sys

base = sys.argv[1]
FILES = [
    "backend/tests/Feature/ProductCatalogTest.php",
    "backend/tests/Feature/ProductResponseShapeTest.php",
    "backend/tests/Feature/ApiErrorResponseTest.php",
    "backend/tests/Feature/AuthorizationTest.php",
    "backend/tests/Feature/MoneyInCentsTest.php",
    "backend/tests/Feature/RequestIdTest.php",
]
LEAVING = ["reads the stock of the catalog page through the inventory", "lists the catalog with the same query count for 1 and 12 products"]


def normalize(text):
    for name in LEAVING:
        start = re.search(r"^it\('" + re.escape(name) + r"'", text, re.M)
        if start:
            end = re.search(r"^\}\);\n", text[start.start():], re.M)
            text = text[:start.start()] + text[start.start() + end.end():]
    text = re.sub(r"^\s*rebuildStorefront\(\);\n", "", text, flags=re.M)
    text = text.replace("use Illuminate\\Support\\Facades\\DB;\n", "")
    return re.sub(r"\n{2,}", "\n\n", text).strip()


failed = False
for path in FILES:
    old = subprocess.run(["git", "show", f"{base}:{path}"], capture_output=True, text=True, check=True).stdout
    new = open(path).read()
    same = normalize(old) == normalize(new)
    print(("same     " if same else "CHANGED  ") + path)
    failed |= not same
sys.exit(1 if failed else 0)
PY

make test-filter FILTER='ProductCatalogTest|ProductResponseShapeTest|ApiErrorResponseTest|AuthorizationTest|MoneyInCentsTest|RequestIdTest'
