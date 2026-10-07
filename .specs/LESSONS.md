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

## Quarantined (failed when applied - ignore)

A confirmed lesson that recurred alongside failure. Kept for the maintainer to review.

_none_
