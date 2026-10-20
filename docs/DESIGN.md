# Design

The public site uses the approved palette: ink `#091413`, pine `#285A48`, leaf `#408A71`, mint `#B0E4CC`, wash `#f4faf7`. Georgian text uses the self-hosted Noto Sans Georgian file, with FiraGO for the other letters. The header and footer are ink. The page wash sits behind white cards. Section rhythm on the home page is a dark hero, then the picker card overlapping that hero, then the three benefit cards and the catalog counts.

The approved static mockups are `docs/mockups/home.html` and `docs/mockups/dashboard.html` on `cursor/home-mockup-81d8`. The live picker does not use that mockup's lightbox. The photo still opens inside `#vp-card`, from `GET /api/models/{id}/photo`, with the server watermark.

## 2026-10-20

- Home is full-bleed: title and curl sample on the ink hero, then the existing vehicle picker. Benefit cards do not lead with `carapi_sk_` or `carapi_pk_`.
- Pricing is Free versus Pro (soon), with a comparison table. No Stripe checkout. The Free card carries the pine outline.
- Guide examples stay three tabs: curl, JavaScript, PHP. The embed snippet uses `public-key="demo"`.
- Body text is 16px / 1.6. Muted text on the wash is `#3d564c`.
- Locale switch is the text KA / EN. `/ka` and `/en` set the session and redirect home. Canonical, hreflang, Open Graph, and the SVG favicon are in the public layout.
- Auth screens use the same public layout, including password reset and email verification. The register form states the 15-character password minimum.
- The signed-in cabinet keeps the Electrik shell and the legacy project desk. Both use this palette. Keys, usage, plan, and the vehicle explorer stay on their current routes.
- Empty picker steps say to choose the previous level first.
- Unknown URLs render `resources/views/errors/404.blade.php`. Forbidden URLs render `resources/views/errors/403.blade.php`.
- The admin panel keeps its layout. `admin.css` only changes color.
