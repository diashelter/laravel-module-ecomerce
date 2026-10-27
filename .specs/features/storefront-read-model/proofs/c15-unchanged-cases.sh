#!/bin/sh
# C15: the cases below are byte for byte what they were at the base commit (82337f0), and they pass.
# Run from the repository root: sh .specs/features/storefront-read-model/proofs/c15-unchanged-cases.sh
set -eu

BASE=82337f0

python3 - "$BASE" <<'PY'
import re, subprocess, sys

base = sys.argv[1]

# file -> test names whose source must be unchanged ("*" = the whole file)
CASES = {
    "backend/tests/Unit/Architecture/OrderEventPayloadTest.php": [
        "declares the name and version of every order event",
        "maps every order event payload and rebuilds it",
    ],
    "backend/tests/Feature/Messaging/ConsumerRedeliveryTest.php": [
        "redelivers OrderPlaced to MarkOrderAsAwaitingPayment",
        "redelivers PaymentApproved to MarkOrderAsPaid",
        "redelivers DeliveryScheduled to RecordEstimatedDelivery",
        "redelivers OrderDelivered to MarkOrderAsDelivered",
        "redelivers OrderPaid to ScheduleOrderDelivery",
    ],
    "backend/tests/Feature/CartValidationTest.php": ["*"],
    "backend/tests/Feature/CheckoutTest.php": [
        "returns 409 and changes nothing when stock is insufficient",
        "rejects inactive and out of stock products",
        "never lets stock go negative (spec scenario: stock 5, buy 4 then 3)",
        "locks the stock rows with SELECT ... FOR UPDATE",
    ],
    "backend/tests/Feature/ProductResponseShapeTest.php": ["lists the admin products newest first fifteen per page"],
    "backend/app-modules/messaging/tests/Feature/Messaging/EventTransportFlagTest.php": ["*"],
}


def source(text, name):
    """The `it('name', ...)` block, from its first line to the line that closes it at column 0."""
    start = re.search(r"^it\(" + re.escape(repr(name)[1:-1]) + r"'?,", text, re.M) or re.search(r"^it\('" + re.escape(name) + r"'", text, re.M)
    if start is None:
        return None
    end = re.search(r"^\}\)[^\n]*;\n", text[start.start():], re.M)
    return text[start.start():start.start() + end.end()]


failed = False
for path, names in CASES.items():
    old = subprocess.run(["git", "show", f"{base}:{path}"], capture_output=True, text=True, check=True).stdout
    new = open(path).read()
    for name in names:
        if name == "*":
            same = old == new
        else:
            before, after = source(old, name), source(new, name)
            same = before is not None and before == after
        print(("same     " if same else "CHANGED  ") + f"{path} :: {name}")
        failed |= not same

sys.exit(1 if failed else 0)
PY

make test-filter FILTER='declares the name and version of every order event|maps every order event payload and rebuilds it|redelivers (OrderPlaced|PaymentApproved|DeliveryScheduled|OrderDelivered|OrderPaid) to|CartValidationTest|returns 409 and changes nothing when stock is insufficient|rejects inactive and out of stock products|never lets stock go negative|locks the stock rows with SELECT|lists the admin products newest first fifteen per page|EventTransportFlagTest'
