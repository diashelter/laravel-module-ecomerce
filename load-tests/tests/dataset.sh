#!/bin/sh
# Assertions over the load database (checks C1 to C8 and C64): sh load-tests/tests/dataset.sh --only <case>.
# The load stack must be up (a run leaves it up). The seed cases reseed first, because a run changes the data;
# `untouched` reads the database as the last run left it.
. "$(dirname "$0")/common.sh"

password_is() {
    $LOAD exec -T api php artisan tinker --execute="echo Hash::check('password', DB::table('$1')->where('email', '$2')->value('password')) ? 'yes' : 'no';" | grep -q '^yes'
}

fingerprint() {
    psql_load "select md5(
        (select string_agg(name, ',' order by id) from customers) || '|' ||
        (select string_agg(name || ':' || price_cents, ',' order by id) from products) || '|' ||
        (select string_agg(total_cents::text, ',' order by id) from orders))"
}

# The relay and the worker read tables that migrate:fresh drops, so they stop while the seed runs.
reseed() {
    $LOAD stop queue-worker outbox-relay >/dev/null 2>&1
    $LOAD exec -T api sh -c 'php artisan migrate:fresh --force >/dev/null && php artisan db:seed --class=LoadTestSeeder --force >/dev/null'
    $LOAD start queue-worker outbox-relay >/dev/null 2>&1
}

case "$CASE" in
    customers)
        reseed
        expect_eq "1.000 clientes" "$(psql_load 'select count(*) from customers')" 1000
        expect_eq "e-mails load-customer-0 a 999" "$(psql_load "select count(*) from generate_series(0, 999) n join customers c on c.email = 'load-customer-' || n || '@example.com'")" 1000
        expect_eq "uma senha para todos" "$(psql_load 'select count(distinct password) from customers')" 1
        password_is customers load-customer-0@example.com || fail "a senha não é password"
        ok "a senha é password"
        expect_eq "1.000 endereços" "$(psql_load 'select count(*) from customer_addresses')" 1000
        expect_eq "um endereço por cliente" "$(psql_load 'select count(*) from customers c where (select count(*) from customer_addresses a where a.customer_id = c.id) <> 1')" 0
        ;;
    staff)
        reseed
        expect_eq "2 membros da equipe" "$(psql_load 'select count(*) from users')" 2
        expect_eq "o admin" "$(psql_load "select role from users where email = 'admin@example.com'")" admin
        expect_eq "o suporte" "$(psql_load "select role from users where email = 'suporte@example.com'")" support
        password_is users admin@example.com || fail "a senha do admin não é password"
        password_is users suporte@example.com || fail "a senha do suporte não é password"
        ok "as senhas são password"
        ;;
    catalog)
        reseed
        expect_eq "8 categorias" "$(psql_load 'select count(*) from categories')" 8
        expect_eq "201 produtos" "$(psql_load 'select count(*) from products')" 201
        expect_eq "170 ativos com 1.000.000" "$(psql_load "select count(*) from products p join stocks s on s.product_id = p.id where p.status = 'active' and s.quantity = 1000000")" 170
        expect_eq "10 ativos com 0" "$(psql_load "select count(*) from products p join stocks s on s.product_id = p.id where p.status = 'active' and s.quantity = 0")" 10
        expect_eq "20 inativos com 100" "$(psql_load "select count(*) from products p join stocks s on s.product_id = p.id where p.status = 'inactive' and s.quantity = 100")" 20
        expect_eq "um Produto Disputado, ativo, com 10" "$(psql_load "select count(*) from products p join stocks s on s.product_id = p.id where p.name = 'Produto Disputado' and p.status = 'active' and s.quantity = 10")" 1
        expect_eq "só um com esse nome" "$(psql_load "select count(*) from products where name = 'Produto Disputado'")" 1
        ;;
    orders)
        reseed
        expect_eq "10.000 pedidos" "$(psql_load 'select count(*) from orders')" 10000
        expect_eq "todos nos últimos 12 meses" "$(psql_load "select count(*) from orders where created_at < now() - interval '12 months'")" 0
        expect_eq "de 1 a 4 itens" "$(psql_load 'select count(*) from (select o.id, count(i.id) n from orders o left join order_items i on i.order_id = o.id group by o.id) t where n < 1 or n > 4')" 0
        expect_eq "a cópia de um endereço do cliente" "$(psql_load 'select count(*) from orders o where not exists (select 1 from customer_addresses a where a.customer_id = o.customer_id and a.recipient_name = o.delivery_recipient_name and a.postal_code = o.delivery_postal_code and a.street = o.delivery_street and a.number = o.delivery_number and a.district = o.delivery_district and a.city = o.delivery_city and a.state = o.delivery_state)')" 0
        WRONG_SHIPPING=$($LOAD exec -T api php artisan tinker --execute="echo DB::table('orders')->select('delivery_state', 'shipping_cents', 'delivery_business_days')->distinct()->get()->filter(fn (\$o) => config('shop.shipping_rates.'.\$o->delivery_state) !== ['price_cents' => (int) \$o->shipping_cents, 'business_days' => (int) \$o->delivery_business_days])->count();" | tr -d '[:space:]')
        expect_eq "o frete da tabela para a UF" "$WRONG_SHIPPING" 0
        expect_eq "um pagamento aprovado por pedido pago" "$(psql_load "select count(*) from orders o where o.status in ('payment_approved', 'delivered') and (select count(*) from payments p where p.order_id = o.id and p.status = 'approved') <> 1")" 0
        expect_eq "nenhum pagamento nos outros" "$(psql_load "select count(*) from payments p join orders o on o.id = p.order_id where o.status not in ('payment_approved', 'delivered')")" 0
        [ "$(psql_load "select count(*) from orders o join customers c on c.id = o.customer_id where c.email = 'load-customer-0@example.com'")" -ge 1 ] || fail "load-customer-0 sem pedido"
        ok "load-customer-0 tem pedido"
        ;;
    stock-and-outbox)
        reseed
        expect_eq "uma linha de estoque por produto" "$(psql_load 'select count(*) from products p where (select count(*) from stocks s where s.product_id = p.id) <> 1')" 0
        expect_eq "nenhuma quantidade fora do C3" "$(psql_load "select count(*) from products p join stocks s on s.product_id = p.id where not ((p.name = 'Produto Disputado' and s.quantity = 10) or (p.name <> 'Produto Disputado' and p.status = 'active' and s.quantity in (0, 1000000)) or (p.status = 'inactive' and s.quantity = 100))")" 0
        expect_eq "outbox vazio" "$(psql_load 'select count(*) from outbox_messages')" 0
        ;;
    deterministic)
        reseed
        FIRST=$(fingerprint)
        reseed
        SECOND=$(fingerprint)
        expect_eq "a mesma impressão digital nos dois seeds ($FIRST)" "$SECOND" "$FIRST"
        ;;
    seed-time)
        START=$(date +%s)
        reseed
        SECONDS_TAKEN=$(( $(date +%s) - START ))
        [ "$SECONDS_TAKEN" -le 90 ] || fail "o seed levou ${SECONDS_TAKEN} s"
        ok "migrate:fresh e seed em ${SECONDS_TAKEN} s"
        ;;
    untouched)
        expect_eq "10.000 pedidos" "$(psql_load 'select count(*) from orders')" 10000
        expect_eq "1.000 clientes" "$(psql_load 'select count(*) from customers')" 1000
        expect_eq "1.000 endereços" "$(psql_load 'select count(*) from customer_addresses')" 1000
        expect_eq "pagamentos só dos pedidos pagos do seed" "$(psql_load 'select count(*) from payments')" "$(psql_load "select count(*) from orders where status in ('payment_approved', 'delivered')")"
        ;;
    *)
        unknown_case "customers staff catalog orders stock-and-outbox deterministic seed-time untouched"
        ;;
esac
