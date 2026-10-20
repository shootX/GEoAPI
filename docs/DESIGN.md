# Design

The public site stays light paper, white cards, and one orange (`#ff7340`). Reference set used on 2026-10-20: light editorial API pages (Tracebit, Formcarry, Resend, Stripe docs) — headline, one support line, a primary action, and a code sample above the fold. Section rhythm is about 96px. The column is `max-w-6xl`.

## 2026-10-20

- Hero is a two-column grid: title and the account action on the left, the curl sample on the right. Benefit cards do not lead with `carapi_sk_` or `carapi_pk_`.
- Pricing is Free versus Pro (soon), with a comparison table. No Stripe checkout.
- Guide examples stay three tabs: curl, JavaScript, PHP. The embed snippet uses `public-key="demo"`.
- Body text is 16px / 1.6. Form borders on `.site` are `#71717a`. Muted text stays `#52525b`.
- Locale switch is the text KA / EN. `/ka` and `/en` set the session and redirect home. Canonical, hreflang, Open Graph, and the SVG favicon are in the public layout.
- Auth screens use the same public layout. The register form states the 15-character password minimum.
- Empty picker steps say to choose the previous level first.
- Unknown URLs render `resources/views/errors/404.blade.php`.
