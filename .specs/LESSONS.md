# LESSONS - auto-maintained by scripts/lessons.py

> Machine-owned. Do NOT hand-edit. Changes are overwritten on the next `lessons.py` write.
> Canonical state lives in `.specs/lessons.json`. Edit lessons only via the script.
> promote_threshold=2 distinct features · window_days=45 · quarantine_threshold=2

## Confirmed (load these at Plan/Checks)

Corroborated across multiple features. Safe to apply as guidance.

_none_

## Candidates (under observation - do NOT load as guidance yet)

Seen once or not yet corroborated. Tracked, not trusted.

### L-001 - Every one-way constraint listed in plan Relations, including foreign keys and their onDelete rule, needs its own Coverage member and a database-level proof.
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `backend/database/migrations` · harmful: 0
- features: fake-payment-gateway
- evidence: plan.md Relations; migration payments.order_id (backend/database/migrations)
- last seen: 2026-10-07T00:33:38Z

### L-002 - When the Test policy counts N outcomes of a use case, write one own-layer check per outcome, not only a boundary test for the rarest one.
- signal: `spec_precision_gap` · recurrence: 1 feature(s) · scope: `backend/app/Modules/*/UseCases` · harmful: 0
- features: fake-payment-gateway
- evidence: checks.md Test policy; PayOrderUseCase lost race (backend/app/Modules/*/UseCases)
- last seen: 2026-10-07T00:33:38Z

### L-003 - A fault exercise for a computed rule must place the probe where the rule reads its input, such as a native signature, not only in a use statement, or it also fails under the rejected alternative.
- signal: `surviving_mutant` · recurrence: 1 feature(s) · scope: `backend/tests/Unit/Architecture` · harmful: 0
- features: module-vocabulary
- evidence: verification.md F1; C10 (backend/tests/Unit/Architecture/ModuleBoundariesTest.php:168) (backend/tests/Unit/Architecture)
- last seen: 2026-10-09T16:32:07Z

### L-004 - When a check says every route that does something, list those routes in the check and make the test dataset cover exactly that list.
- signal: `spec_precision_gap` · recurrence: 1 feature(s) · scope: `backend/tests/Feature` · harmful: 0
- features: module-vocabulary
- evidence: verification.md gap 2; C21 (backend/tests/Feature/Auth/EmailAndPasswordTest.php:264) (backend/tests/Feature)
- last seen: 2026-10-09T16:32:07Z

### L-005 - When a computed rule is proven only by a temporary exercise, add a permanent fixture proof or state in the checks that only the exercise protects the rule.
- signal: `spec_precision_gap` · recurrence: 1 feature(s) · scope: `backend/tests/Unit/Architecture` · harmful: 0
- features: module-vocabulary
- evidence: verification.md round 2 gap 1; C10 (backend/tests/Unit/Architecture/ModuleBoundariesTest.php:168) (backend/tests/Unit/Architecture)
- last seen: 2026-10-09T16:44:55Z

### L-006 - Before writing a check about a database refusal citing a constraint, confirm the column type rejects the value first; a char(8) refuses a 9-character value before any CHECK runs.
- signal: `spec_precision_gap` · recurrence: 1 feature(s) · scope: `backend/migrations` · harmful: 0
- features: uuid-record-ids
- evidence: checks.md C23 (backend/migrations)
- last seen: 2026-10-15T21:42:15Z

### L-007 - A negative-grep proof must be shown failing on a planted line; a pattern keyed to a renamed token (the old {id}) silently matches nothing.
- signal: `spec_precision_gap` · recurrence: 1 feature(s) · scope: `docs` · harmful: 0
- features: uuid-record-ids
- evidence: checks.md C63 (docs)
- last seen: 2026-10-15T21:42:15Z

### L-008 - Every type the plan's Impact names as changing (here the id-keyed maps) owes a Coverage row and a proof, not only the ones that appear in Surface.
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `checks` · harmful: 0
- features: uuid-record-ids
- evidence: plan.md Impact (checks)
- last seen: 2026-10-15T21:42:15Z

### L-009 - Assert every field of a published envelope as built from the stored row, including its timestamp, not only on a hand-built envelope.
- signal: `surviving_mutant` · recurrence: 1 feature(s) · scope: `outbox, relay` · harmful: 0
- features: transactional-outbox
- evidence: F5 backend/app/Modules/Shared/Repositories/OutboxMessageRepository.php:75 (outbox, relay)
- last seen: 2026-10-16T01:17:32Z

### L-010 - Every Caller sees cell of a design state table owes a check, including operator CLI output such as the failed job display name.
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `queue, design` · harmful: 0
- features: transactional-outbox
- evidence: design EventConsumer state 'O consumidor falha'; backend/app/Modules/Shared/Messaging/DeliverEvent.php:51-54 (queue, design)
- last seen: 2026-10-16T01:17:32Z

### L-011 - A claim whose consequence depends on a transaction or row lock must be proven by observing the transaction, not only by the SQL text.
- signal: `spec_precision_gap` · recurrence: 1 feature(s) · scope: `relay, concurrency` · harmful: 0
- features: transactional-outbox
- evidence: C22 backend/tests/Feature/Outbox/OutboxRelayTest.php:83-87 (relay, concurrency)
- last seen: 2026-10-16T01:17:32Z

### L-012 - When a Test policy row names the edge for a decision, write each decision's check through that edge, not against the internal class it reaches.
- signal: `spec_precision_gap` · recurrence: 1 feature(s) · scope: `test-policy` · harmful: 0
- features: transactional-outbox
- evidence: Test policy row 1; C8, C9, C15, C16 backend/tests/Feature/Outbox/EventDeliveryTest.php:54,76,156,168 (test-policy)
- last seen: 2026-10-16T01:17:32Z

### L-013 - When a claim refuses every value except listed types, table-drive the refusal over each remaining PHP value type: array, object and resource.
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `validation` · harmful: 0
- features: transactional-outbox
- evidence: C5 backend/tests/Feature/Outbox/EventOutboxTest.php:102-106 (validation)
- last seen: 2026-10-16T01:17:32Z

### L-014 - When a check claims an HTTP result, the proof must call the route, not the query or use case behind it
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `tests` · harmful: 0
- features: cqrs-read-side
- evidence: C40 round 1 (tests)
- last seen: 2026-10-16T14:02:09Z

### L-015 - Run each cited test filter against a deliberate violation to confirm it matches the generated test names
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `tests` · harmful: 0
- features: cqrs-read-side
- evidence: C7 round 1 (tests)
- last seen: 2026-10-16T14:02:09Z

### L-016 - Assert page size and tie break ordering with more rows than one page and equal sort keys
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `tests` · harmful: 0
- features: cqrs-read-side
- evidence: C34 round 1 (tests)
- last seen: 2026-10-16T14:02:09Z

### L-017 - Pin an inclusive date bound with a row created exactly at the boundary
- signal: `surviving_mutant` · recurrence: 1 feature(s) · scope: `tests` · harmful: 0
- features: cqrs-read-side
- evidence: F5 round 1: OrderMetricsQuery >= to > (tests)
- last seen: 2026-10-16T14:02:09Z

### L-018 - Give each counted table a different row count in the fixture so counting the wrong table fails
- signal: `surviving_mutant` · recurrence: 1 feature(s) · scope: `tests` · harmful: 0
- features: cqrs-read-side
- evidence: F4 round 1: CustomerMetricsQuery counted users (tests)
- last seen: 2026-10-16T14:02:09Z

### L-019 - Prove what a wrapper hands to an external client with a fake client at its own layer, not only the pure helpers it calls.
- signal: `surviving_mutant` · recurrence: 1 feature(s) · scope: `load-tests/lib` · harmful: 0
- features: load-tests-k6
- evidence: C74 F3, F3c (verification rounds 1 and 2) (load-tests/lib)
- last seen: 2026-10-16T21:08:51Z

### L-020 - When a flow wires a tested decision to a metric, pass the metric itself and pin the exact call, so a no-op callback cannot replace it.
- signal: `surviving_mutant` · recurrence: 1 feature(s) · scope: `load-tests/flows` · harmful: 0
- features: load-tests-k6
- evidence: C42 F1, F1b (verification rounds 1 and 2) (load-tests/flows)
- last seen: 2026-10-16T21:08:51Z

### L-021 - Assert the distinctive output of the step a claim names, not a message an earlier step already printed.
- signal: `surviving_mutant` · recurrence: 1 feature(s) · scope: `load-tests/tests` · harmful: 0
- features: load-tests-k6
- evidence: C14 F5 (verification round 1), Makefile:154 (load-tests/tests)
- last seen: 2026-10-16T21:08:51Z

### L-022 - When a rule applies to several variants, assert it for every variant, not only the first.
- signal: `surviving_mutant` · recurrence: 1 feature(s) · scope: `load-tests/lib` · harmful: 0
- features: load-tests-k6
- evidence: C77 F2 (verification round 1) (load-tests/lib)
- last seen: 2026-10-16T21:08:51Z

### L-023 - Prove what a counter counts through a tested decision; a lower bound on its total cannot tell the counted outcome from its opposite.
- signal: `surviving_mutant` · recurrence: 1 feature(s) · scope: `load-tests/scenarios` · harmful: 0
- features: load-tests-k6
- evidence: C63 burst counter mutant (verification round 2), scenarios/limits.js:77 (load-tests/scenarios)
- last seen: 2026-10-16T21:08:52Z

### L-024 - Record a baseline only on a commit whose measured code stays unchanged until the feature closes, and prove it with a diff over the measured paths.
- signal: `spec_deviation` · recurrence: 1 feature(s) · scope: `load-tests` · harmful: 0
- features: load-tests-k6
- evidence: C76, AC 71 (verification round 1) (load-tests)
- last seen: 2026-10-16T21:08:52Z

### L-025 - Prove a documented rule by grepping its full sentence; a short fragment also matches the surrounding text.
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `docs` · harmful: 0
- features: load-tests-k6
- evidence: C85 (verification round 2), tests/docs.sh:56 (docs)
- last seen: 2026-10-16T21:08:52Z

### L-026 - Prove that a shared request helper counts an unexpected status as an error, not only what it sends.
- signal: `surviving_mutant` · recurrence: 1 feature(s) · scope: `load-tests/lib` · harmful: 0
- features: load-tests-k6
- evidence: F7b (verification round 3), load-tests/lib/http.js:120 (load-tests/lib)
- last seen: 2026-10-17T00:42:57Z

## Quarantined (failed when applied - ignore)

A confirmed lesson that recurred alongside failure. Kept for the maintainer to review.

_none_
