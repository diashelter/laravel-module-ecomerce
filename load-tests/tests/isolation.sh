#!/bin/sh
# A smoke run leaves the development stack as it was (check C16): sh load-tests/tests/isolation.sh --only dev-untouched.
# The development stack must be up.
. "$(dirname "$0")/common.sh"

dev_counts() {
    psql_dev "select (select count(*) from customers) || ' ' || (select count(*) from orders) || ' ' || (select count(*) from products) || ' ' || (select count(*) from stocks) || ' ' || (select count(*) from outbox_messages)"
}

case "$CASE" in
    dev-untouched)
        BEFORE=$(dev_counts) || fail "a pilha de dev não está no ar"
        CACHE_BEFORE=$(ls backend/bootstrap/cache | tr '\n' ' ')
        mkdir -p "$RESULTS"
        make load-test TYPE=smoke >"$RESULTS/isolation-output.log" 2>&1 || true
        expect_eq "customers, orders, products, stocks e outbox_messages de dev" "$(dev_counts)" "$BEFORE"
        expect_eq "backend/bootstrap/cache" "$(ls backend/bootstrap/cache | tr '\n' ' ')" "$CACHE_BEFORE"
        ;;
    *)
        unknown_case "dev-untouched"
        ;;
esac
