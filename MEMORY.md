
## 2026-10-22 — TEST payments
- App\Billing\MockPay::enabled() = no BOG creds && BOG_ENV != production && services.bog.mock !== false. billing_orders.provider ('bog' default, 'mock' for tests). Mock success goes through Entitlements::grant, so plan/invoice logic is shared with the BOG callback.
