# Design

Public pages use `src/SiteChrome.php`, `public/assets/site.css`, and the FiraGO files in `public/assets/fonts`. The mark is `public/assets/brand/`. Static layout references live in `design/`.

Default language is Georgian (`html lang="ka"`). `/demo` is an English page. Do not restyle colors, type, or layout except where a fix requires a new block (pricing, locale link, security meta).

Auth screens are the split layout in `Platform\View::auth`. Admin screens use `public/assets/admin.css`.
