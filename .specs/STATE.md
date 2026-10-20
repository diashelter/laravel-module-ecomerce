# Project state

## Decisions

| ID | Decision | Rationale | Status | Date |
| --- | --- | --- | --- | --- |
| AD-001 | Todo consumidor de evento declara a sua chave natural de idempotência (escrita condicional ou linha única) e tem um teste que entrega o mesmo envelope duas vezes pelo `EventDelivery`; o `EventDelivery` não deduplica por id de mensagem | a entrega é ao menos uma vez e não há inbox; sem a regra escrita, o próximo consumidor repete o buraco do Fulfillment | active | 2026-10-20 |
| AD-002 | Um produtor só grava o evento no outbox quando a escrita guardada mudou uma linha, na mesma transação; o trabalho seguinte é enfileirado depois do commit e é seguro de repetir | grava-e-anuncia sem estado multiplica mensagens a cada reentrega; enfileirar dentro da transação perde o job se o commit falhar | active | 2026-10-20 |

## Handoff

**Feature**: event-idempotency (HEL-16)
**Where**: implementada e verificada (`verification.md`: PASS, 24/24 checks, 5 de 5 mutantes mortos, `validate_verification.py` com exit 0)
**In progress**: nada
**Next step**: abrir o PR da branch `helterdias/hel-16-event-idempotency` (nada foi enviado ao remoto); antes, `make migrate` e `make queue-restart` no ambiente de dev
**Blockers**: none
**Uncommitted**: none
**Branch**: helterdias/hel-16-event-idempotency
**Achados não bloqueantes da verificação**: C21 só exercita o ramo "já entregue"; C14 não distingue fusos (o `DeliverOrder` sem linha grava o dia UTC, o agendamento grava o dia de São Paulo); `.design/event-idempotency.md:133` contradiz a Key decision 2 sobre a contagem de jobs na reentrega
