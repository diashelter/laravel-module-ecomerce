#!/bin/sh
# C50: the storefront query holds no availability rule: its code (comments left out) never names the product status or
# the stock quantity as a filter, and never decides the availability. The rule is read through the ProductAvailability
# of the ProductView the query returns. Run from the repository root.
set -eu

FILE=backend/app-modules/catalog/src/Persistence/Queries/StorefrontQuery.php
CODE=$(grep -vE '^\s*(/\*\*|\*|//)' "$FILE")

if printf '%s\n' "$CODE" | grep -E "product\.status|stock\.quantity|is_available|isAvailable|ProductAvailability|available_quantity"; then
    echo "FALHOU: a consulta da vitrine filtra por status ou por quantidade, ou decide a disponibilidade" >&2
    exit 1
fi

echo "ok: a consulta não filtra por status nem por quantidade e não decide a disponibilidade"
