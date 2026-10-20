# Project state

## Decisions

| ID | Decision | Rationale | Status | Date |
| --- | --- | --- | --- | --- |
| AD-001 | Todo consumidor de evento declara a sua chave natural de idempotência (escrita condicional ou linha única) e tem um teste que entrega o mesmo envelope duas vezes pelo `EventDelivery`; o `EventDelivery` não deduplica por id de mensagem | a entrega é ao menos uma vez e não há inbox; sem a regra escrita, o próximo consumidor repete o buraco do Fulfillment | active | 2026-10-20 |
| AD-002 | Um produtor só grava o evento no outbox quando a escrita guardada mudou uma linha, na mesma transação; o trabalho seguinte é enfileirado depois do commit e é seguro de repetir | grava-e-anuncia sem estado multiplica mensagens a cada reentrega; enfileirar dentro da transação perde o job se o commit falhar | active | 2026-10-20 |
| AD-003 | Um módulo de negócio isolado não nomeia outro módulo de negócio: pede o que precisa por uma porta no próprio `Contracts`, no próprio vocabulário, que o pacote `Integration` liga ao contrato do dono (ou a uma porta que responde `503 SERVICE_UNAVAILABLE` sem o dono); lê o evento de outro módulo com uma classe própria em `Core/InboundEvents`; não aponta chave estrangeira para a tabela de outro; e os testes dele moram em `app-modules/<módulo>/tests` e passam com só ele, o `Shared` e o `Messaging` | é a forma que o piloto Payment ↔ Ordering provou e que as próximas slices do `.design/module-isolation.md` copiam | active | 2026-10-20 |

## Handoff

**Feature**: module-isolation-pilot (slices Module Tests e Payment ↔ Ordering de `.design/module-isolation.md`)
**Where**: construída na branch `helterdias/module-isolation-pilot` (nada enviado ao remoto); 39 checks com prova verde no `HEAD`; a verificação independente rodou 3 rodadas (só achados de documentação, 15 falhas injetadas e todas pegas) e a terceira deu FAIL por uma contagem antiga, corrigida depois do limite de três rodadas
**In progress**: nada
**Next step**: diashelter decide se roda uma quarta rodada de verificação escopada (o diff `683d32e..83bd3e8`) ou aceita a correção; depois, abrir o PR. No ambiente de dev, o `make migrate` e o `make queue-restart` já rodaram
**Blockers**: none
**Uncommitted**: none
**Branch**: helterdias/module-isolation-pilot
