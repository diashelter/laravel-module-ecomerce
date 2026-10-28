#!/bin/sh
# C74 to C77: the documents describe the code as it is. Run from the repository root:
# sh .specs/features/storefront-read-model/proofs/docs.sh --only <readme|env-examples|domain-analysis|agents>
set -eu

CASE=""
while [ $# -gt 0 ]; do
    case "$1" in
        --only) CASE="$2"; shift 2 ;;
        *) echo "FALHOU: opção desconhecida $1" >&2; exit 1 ;;
    esac
done

fail() { echo "FALHOU: $*" >&2; exit 1; }
ok() { echo "ok: $*"; }
has() { grep -qF -- "$2" "$1" || fail "$1 não cita: $2"; }
hasnt() { if grep -nE -- "$2" "$1"; then fail "$1 ainda tem: $2"; fi; }

case "$CASE" in
    readme)
        F=README.md
        for term in catalog_products catalog_categories "deleted_version" "product.category_ids" \
            catalog.project_product_created catalog.project_product_updated catalog.project_product_status_changed catalog.project_product_deleted \
            catalog.project_category_created catalog.project_category_updated catalog.project_category_deleted catalog.project_stock_changed \
            "make storefront-rebuild" catalog:read-model-clear catalog:republish inventory:republish \
            api-read "resolver" mongodb MONGODB_HOST MONGODB_PORT MONGODB_DATABASE MONGODB_USERNAME MONGODB_PASSWORD \
            catalog.product_created catalog.product_updated catalog.product_status_changed catalog.product_deleted \
            catalog.category_created catalog.category_updated catalog.category_deleted inventory.stock_changed \
            "category_ids" "lista plana de escalares" "ISO 8601" "SERVICE_UNAVAILABLE"; do
            has "$F" "$term"
        done
        has "$F" '`version` (`bigint`'
        hasnt "$F" "payload (tem|com) só escalares|\(só escalares\)"
        ok "o README descreve o modelo de leitura, o projetor, a reconstrução, o api-read, o gateway, o mongodb, os eventos e a regra do payload"
        ;;
    env-examples)
        for variable in MONGODB_HOST MONGODB_PORT MONGODB_DATABASE MONGODB_USERNAME MONGODB_PASSWORD; do has backend/.env.example "$variable="; done
        for variable in MONGODB_USERNAME MONGODB_PASSWORD MONGODB_FORWARD_PORT; do has .env.example "$variable="; done
        ok "os dois .env.example listam as variáveis do MongoDB"
        ;;
    domain-analysis)
        F=docs/domain-analysis.md
        has "$F" "modelo de leitura"; has "$F" "projetor"; has "$F" "reconstrução"
        awk '/^\*\*Linguagem ubíqua:\*\* produto, categoria/ { print }' "$F" | grep -q "modelo de leitura" || fail "a linguagem ubíqua do Catalog não tem modelo de leitura"
        awk '/^\*\*Linguagem ubíqua:\*\* produto, categoria/ { print }' "$F" | grep -q "projetor" || fail "a linguagem ubíqua do Catalog não tem projetor"
        awk '/^\*\*Linguagem ubíqua:\*\* produto, categoria/ { print }' "$F" | grep -q "reconstrução" || fail "a linguagem ubíqua do Catalog não tem reconstrução"
        hasnt "$F" "StockLevels \(estoque da vitrine\)|lida pela consulta da vitrine e do cadastro"
        hasnt "$F" "payload só com escalares|payload de escalares\)"
        sed -n 4p "$F" | grep -Eq 'Última atualização: (2[7-9]|3[01])/10/2026|Última atualização: [0-9]{2}/(1[12])/2026' || fail "a linha Última atualização não é de 27/10/2026 ou depois"
        ok "a análise de domínio descreve o modelo de leitura"
        ;;
    agents)
        F=AGENTS.md
        has "$F" "lista plana de escalares"; has "$F" "ISO 8601"
        has "$F" 'têm `Console/`'; has "$F" "catalog:read-model-clear"; has "$F" "inventory:republish"
        has "$F" "chave natural de idempotência dele"; has "$F" "deleted_version"; has "$F" 'a coluna `version`'
        has "$F" "coleções do MongoDB"; has "$F" '`mongodb` no ar'; has "$F" "com \`--wait\`"
        hasnt "$F" "só o \`Messaging\` tem \`Console/\`"
        hasnt "$F" "com só escalares"
        ok "o AGENTS.md traz as regras novas"
        ;;
    *) echo "FALHOU: caso desconhecido '$CASE'" >&2; exit 1 ;;
esac
