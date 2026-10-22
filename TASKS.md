
## 2026-10-22 — TEST payment mode (owner-approved)
- [x] MockPay: when no BOG client_id/secret and BOG_ENV != production, checkout opens /billing/test/{order} (signed, owner-only, no card fields) with success/fail buttons.
- [x] Success → Entitlements::grant (same path as a verified BOG callback); fail → failOrder. Orders flagged bog_order_id=mock:<id> (prod DB user cannot ALTER billing_orders), invoices numbered TEST-… and the PDF plan line says TEST.
- [x] Guard: impossible with credentials or BOG_ENV=production (tests). Optional kill switch BOG_MOCK=false.
- [ ] When the owner sends BOG credentials, mock turns off automatically; old TEST orders stay flagged.

## 2026-10-22 — verification link
- [x] Prod has ELECTRIK_EMAIL_VERIFICATION=false, so Electrik skipped verification.verify and VerifyEmail threw at registration. routes/product.php now registers verification.verify when Electrik does not.
