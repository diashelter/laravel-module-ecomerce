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

## Quarantined (failed when applied - ignore)

A confirmed lesson that recurred alongside failure. Kept for the maintainer to review.

_none_
