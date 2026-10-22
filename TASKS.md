
## 2026-10-22 — TEST payment mode (owner-approved)
- [x] MockPay: when no BOG client_id/secret and BOG_ENV != production, checkout opens /billing/test/{order} (signed, owner-only, no card fields) with success/fail buttons.
- [x] Success → Entitlements::grant (same path as a verified BOG callback); fail → failOrder. Orders provider=mock, invoices numbered TEST-… and the PDF plan line says TEST.
- [x] Guard: impossible with credentials or BOG_ENV=production (tests). Optional kill switch BOG_MOCK=false.
- [ ] When the owner sends BOG credentials, mock turns off automatically; old TEST orders stay flagged.
