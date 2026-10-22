
## 2026-10-22 — TEST payments
- App\Billing\MockPay::enabled() = no BOG creds && BOG_ENV != production && services.bog.mock !== false. mock orders flagged via bog_order_id 'mock:<public_id>' (MockPay::isMock); app DB user is not owner of billing_orders, so no ALTER migrations on it. Mock success goes through Entitlements::grant, so plan/invoice logic is shared with the BOG callback.
- 2026-10-22: prod tables are owned by postgres; app role carapi_electrik has DML only (no ALTER). platform.project_limits.unlimited_month is boolean — insert FALSE, never 0 (caused project-creation 500).
- 2026-10-22 mail: Gmail bounced 5.7.26 (no SPF/DKIM for geoapi.ge). rspamd now DKIM-signs geoapi.ge (selector default, key /www/server/dkim/geoapi.ge); DNS TXT records still to be added by owner.
- 2026-10-22 Billing V2: flag default off so a merge does not change live billing. Migration `2026_12_31_930000_billing_v2` is CREATE TABLE only (postgres owner only if the app role lacks CREATE). It does not ALTER postgres-owned `platform.*` or `billing_orders`. Backfill does not rewrite purchased quotas. Business 240 rpm is not sold. No automatic renewal.
