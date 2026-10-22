<article class="space-y-6 text-sm leading-6">
<section class="panel">
<p>Version 2026-09-26. These terms are not a price list, a payment promise, or an uptime guarantee.</p>
<h1>Terms of use</h1>
<p>The service is provided by LLC Inova Lab, identification code 432291851. Contact: <a href="mailto:hello@geoapi.ge">hello@geoapi.ge</a>.</p>
</section>
<section class="panel"><h2>What you get</h2>
<p>The server API returns brands, models, generations, and modifications so you can build your own interface. The widget shows the same choice on your site and sends the selected ids to your page. It does not filter your listings. Version 1 list endpoints do not return photo files. On the demo, a photo is shown only when it matches the chosen generation; otherwise a placeholder is shown. Modification list and detail are available. There is no separate technical-spec request.</p>
<p>The catalog can be incomplete or wrong. Not every model has a generation. You do not receive an exclusive copyright in the catalog data.</p>
</section>
<section class="panel"><h2>Account and keys</h2>
<p>You keep the account password and the secret API key. The secret key must not appear in a browser. The public widget key is visible in site code and is not a secret. Using someone else's account, bypassing a limit, or intentionally damaging the system is not allowed.</p>
@if (\App\Billing\V2\Flag::on())
<p>Limits apply to the workspace: one subscription and one monthly quota shared by all projects. Free is {{ \App\Billing\Plans::lari(0) }}, 1,000 successful requests a month, 1 active project, 15 requests a minute. Starter is {{ \App\Billing\Plans::lari(\App\Billing\V2\Catalog::tetri('starter', 'month')) }}/month, 25,000, 3 projects, 60 a minute. Growth is {{ \App\Billing\Plans::lari(\App\Billing\V2\Catalog::tetri('growth', 'month')) }}/month, 150,000, 10 projects, 120 a minute. Enterprise is custom, via hello@geoapi.ge. There is no unlimited plan.</p>
<p>A yearly payment is 15% less than 12 months; the quota still resets every billing month. Prices are in GEL and include VAT. Nothing renews or charges automatically — you pay again when the term ends. You can buy a prepaid request pack (Starter: +10,000 for {{ \App\Billing\Plans::lari(900) }}, Growth: +50,000 for {{ \App\Billing\Plans::lari(2000) }}), which expires after 90 days and is spent after the monthly quota, or an extra active project for {{ \App\Billing\Plans::lari(\App\Billing\V2\Catalog::EXTRA_PROJECT_TETRI) }}/month. There is no overage billing. Usage is shown in the account, under Billing.</p>
@else
<p>The limit is per project: 60 requests a minute and 10,000 successful requests in a calendar month. This is not a commercial tariff. The numbers are on the project page, in usage.</p>
@endif
<p>Your site's listings, visitors, and stored data are your responsibility.</p>
</section>
<section class="panel"><h2>Limits and changes</h2>
<p>Access can be limited or paused when the account is suspended, the email is unverified, the key is revoked, the limit is used up, or a request harms the system. That is not a waiver of every responsibility.</p>
<p>The current text is this page. Support: hello@geoapi.ge. Personal data: <a href="/privacy">privacy policy</a>.</p>
</section>
</article>
