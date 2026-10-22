
## 2026-10-22 — TEST payments
- App\Billing\MockPay::enabled() = no BOG creds && BOG_ENV != production && services.bog.mock !== false. mock orders flagged via bog_order_id 'mock:<public_id>' (MockPay::isMock); app DB user is not owner of billing_orders, so no ALTER migrations on it. Mock success goes through Entitlements::grant, so plan/invoice logic is shared with the BOG callback.
