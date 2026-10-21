# Project state

## Decisions

| ID | Decision | Rationale | Status | Date |
| --- | --- | --- | --- | --- |
| AD-001 | Todo consumidor de evento declara a sua chave natural de idempotência (escrita condicional ou linha única) e tem um teste que entrega o mesmo envelope duas vezes pelo `EventDelivery`; o `EventDelivery` não deduplica por id de mensagem | a entrega é ao menos uma vez e não há inbox; sem a regra escrita, o próximo consumidor repete o buraco do Fulfillment | active | 2026-10-20 |
| AD-002 | Um produtor só grava o evento no outbox quando a escrita guardada mudou uma linha, na mesma transação; o trabalho seguinte é enfileirado depois do commit e é seguro de repetir | grava-e-anuncia sem estado multiplica mensagens a cada reentrega; enfileirar dentro da transação perde o job se o commit falhar | active | 2026-10-20 |
| AD-003 | Um módulo de negócio isolado não nomeia outro módulo de negócio: pede o que precisa por uma porta no próprio `Contracts`, no próprio vocabulário, que o pacote `Integration` liga ao contrato do dono (ou a uma porta que responde `503 SERVICE_UNAVAILABLE` sem o dono); lê o evento de outro módulo com uma classe própria em `Core/InboundEvents`; não aponta chave estrangeira para a tabela de outro; e os testes dele moram em `app-modules/<módulo>/tests` e passam com só ele, o `Shared` e o `Messaging` | é a forma que o piloto Payment ↔ Ordering provou e que as próximas slices do `.design/module-isolation.md` copiam | active | 2026-10-20 |

## Handoff

**Feature**: module-isolation-pilot (slices Module Tests e Payment ↔ Ordering de `.design/module-isolation.md`)
**Where**: construída na branch `helterdias/module-isolation-pilot`, enviada ao remoto até `40058c5`, com o PR [diashelter/laravel-module-ecomerce#30](https://github.com/diashelter/laravel-module-ecomerce/pull/30) aberto; 39 checks com prova verde no `HEAD`. A verificação independente rodou 5 rodadas: as rodadas 1 a 3 deram FAIL (documentação e, na 2, o membro do design que virou o C39), a 4 deu PASS em `91be57d`, e a 5, escopada sobre a correção da review do PR (`8418897..40058c5`), deu FAIL em `40058c5` por um mutante que sobrevive à prova do C33 (R5-M5)
**In progress**: nada
**Next step**: a rodada 6 da verificação, escopada sobre a correção da prova do C33 (`test(module-isolation): match the explicit module list in the C33 proof`, que exige a lista explícita dos 5 módulos com testes), está sendo despachada; depois, o `git push` pede o ok de diashelter. Achados não bloqueantes no `verification.md`: a regra nova não vê helper declarado dentro de um arquivo de teste, a contagem do C9 inclui 3 `it(` de fixtures, o padrão do C33 só casa a contagem depois de "são" e o `ConsumeEventsCommandTest.php:55` falhou 1 vez em 3 por tempo na rodada 4. No ambiente de dev, o `make migrate` e o `make queue-restart` já rodaram
**Blockers**: none
**Uncommitted**: none
**Branch**: helterdias/module-isolation-pilot
