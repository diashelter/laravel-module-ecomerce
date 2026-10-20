# Project state

## Decisions

| ID | Decision | Rationale | Status | Date |
| --- | --- | --- | --- | --- |
| AD-001 | Todo consumidor de evento declara a sua chave natural de idempotência (escrita condicional ou linha única) e tem um teste que entrega o mesmo envelope duas vezes pelo `EventDelivery`; o `EventDelivery` não deduplica por id de mensagem | a entrega é ao menos uma vez e não há inbox; sem a regra escrita, o próximo consumidor repete o buraco do Fulfillment | active | 2026-10-20 |
| AD-002 | Um produtor só grava o evento no outbox quando a escrita guardada mudou uma linha, na mesma transação; o trabalho seguinte é enfileirado depois do commit e é seguro de repetir | grava-e-anuncia sem estado multiplica mensagens a cada reentrega; enfileirar dentro da transação perde o job se o commit falhar | active | 2026-10-20 |

## Handoff

**Feature**: event-idempotency
**Where**: plan.md escrito e validado; checks.md ainda não existe
**In progress**: nada
**Next step**: o usuário revisa `.specs/features/event-idempotency/plan.md`; depois, escrever `checks.md`
**Blockers**: none
**Uncommitted**: `.specs/features/event-idempotency/plan.md`, `.specs/STATE.md`
**Branch**: main
