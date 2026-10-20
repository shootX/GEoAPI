# Design

The public site uses the approved palette: ink `#091413`, pine `#285A48`, leaf `#408A71`, mint `#B0E4CC`, wash `#f4faf7`. Georgian text uses the self-hosted Noto Sans Georgian file, with FiraGO for the other letters. The home page follows `docs/mockups/home.html`: gradient hero, API illustration, overlapping demo, four feature cards, how-it-works, pricing, example stories, FAQ, contact, and a four-column footer. Styles live in `public/assets/public-design.css`.

The approved static mockups are `docs/mockups/home.html` and `docs/mockups/dashboard.html` on `cursor/home-mockup-81d8`. The live picker does not use that mockup's lightbox. The photo still opens inside `#vp-card`, from `GET /api/models/{id}/photo?generation=`, and only when `model_photos.generation_id` matches. Otherwise the card shows the car placeholder. `DemoWatermark` draws one small corner `GEOAPI.GE`.

## 2026-10-20

- Home is the mockup section order. The hero visual is the illustration, not a curl sample. The demo card keeps the real five-step picker (it adds year, which the mockup's four selects omit).
- Pricing is Free versus Pro (soon), with equal-height cards and a “notify me at launch” email on Pro. The comparison table stays. No Stripe checkout.
- Guide examples stay three tabs: curl, JavaScript, PHP, with a sidebar table of contents. The embed snippet uses `public-key="demo"`.
- Body text is 16px / 1.6. Muted text on the wash is `#4d6159`.
- Locale switch is the text KA / EN. `/ka` and `/en` set the session and redirect home. Canonical, hreflang, Open Graph, and the SVG favicon are in the public layout.
- Auth screens use the same public layout, including password reset and email verification. The register form states the 15-character password minimum once.
- `/account` is the cabinet from `dashboard.html`: sidebar, overview, keys, plan, and catalog cards with a drawer. It reads the platform project, usage, secret keys, and widget key. The legacy desk remains at `/account/projects/{id}`. `/dashboard` stays the Electrik settings shell.
- Empty picker steps say to choose the previous level first.
- Unknown URLs render `resources/views/errors/404.blade.php`. Forbidden URLs render `resources/views/errors/403.blade.php`.
- The admin panel keeps its layout. `admin.css` only changes color.
