#!/usr/bin/env bash
# C33: AGENTS.md, README.md, docs/domain-analysis.md and .design/module-isolation.md describe the pilot (each one
# names every item below), none keeps a stale claim, and their relative links resolve.
source "$(dirname "$0")/lib.sh"; start c33
python3 - "$ROOT" <<'PY' | tee -a "$OUT"
import os, re, sys
root = sys.argv[1]
docs = ['AGENTS.md', 'README.md', 'docs/domain-analysis.md', '.design/module-isolation.md']
required = {
    'the Integration package': lambda t: '`Integration`' in t or 'Integration' in t,
    'its boundary exception': lambda t: any('Integration' in p and 'exceção' in p for p in t.split('\n\n')),
    'the consumer port OrdersToPay': lambda t: 'OrdersToPay' in t,
    'the 503 SERVICE_UNAVAILABLE': lambda t: 'SERVICE_UNAVAILABLE' in t and '503' in t,
    'the edited migration': lambda t: '2026_10_06_000001_create_payments_table' in t,
    'the module test folders': lambda t: 'app-modules/<módulo>/tests' in t,
    'the 5 modules with tests': lambda t: any(all(m in p for m in ('Identity', 'Messaging', 'Shared', 'Catalog', 'Payment')) and 'test' in p for p in t.split('\n\n')),
    'the 16 frozen migrations': lambda t: re.search(r'\b16 (nomes|migrations)', t) is not None,
}
stale = [r'congela os 15', r'\b15 nomes', r'continuam em `backend/tests`', r'`payments\.order_id` também usa `restrict`', r'Mover os testes para os módulos exigiria',
         # the Payment reading Ordering's contract directly (found by the first verification round)
         r'Payment → Ordering: PayableOrders', r'o Payment lê o pedido pelo contrato `PayableOrders`', r'O Payment lê o pedido pelo contrato',
         r'Ordering \(lido pelo Payment\)', r'OrderForPayment`, para o Payment',
         r'^\| Tentativas de pagamento presas ao pedido por `restrictOnDelete`',
         # found by the second round: payments named as restricted, and the vocabulary count before OrderToPay
         r'como `payments`\)', r'Hoje são 29 tipos públicos',
         # found by the third round: the vocabulary count in any phrasing (30 public types since OrderToPay)
         r'\bsão 29 (tipos públicos|e 28)\b']
# any other line that still describes payments as restricted on delete, unless it says the key is gone
GONE = re.compile(r'saiu|sem chave|isola|Resolvida|perdeu|não tem|nenhuma|drop_payments|era até|tirar')
bad = 0
for doc in docs:
    text = open(os.path.join(root, doc), encoding='utf-8').read()
    for name, check in required.items():
        if not check(text):
            bad += 1; print(f'{doc}: does not describe {name}')
    for pattern in stale:
        for m in re.finditer(pattern, text, re.M):
            bad += 1; print(f'{doc}: stale claim {m.group(0)!r}')
    for number, line in enumerate(text.split('\n'), 1):
        if 'payments' in line and re.search(r'restri', line, re.I) and not GONE.search(line):
            bad += 1; print(f'{doc}:{number}: payments still described as restricted')
    base = os.path.dirname(os.path.join(root, doc))
    for m in re.finditer(r'\]\(([^)\s]+)\)', text):
        target = m.group(1)
        if re.match(r'^(https?:|mailto:|#)', target):
            continue
        path = target.split('#')[0]
        if path and not os.path.exists(os.path.normpath(os.path.join(base, path))):
            bad += 1; print(f'{doc}: broken link {target}')
print('PASS: the four documents describe the pilot, with no stale claim and no broken link' if not bad else f'FAIL: {bad} problems')
sys.exit(1 if bad else 0)
PY
exit ${PIPESTATUS[0]}
