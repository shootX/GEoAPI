<article class="space-y-6 text-sm leading-6">
<section class="panel">
<p class="note" id="updated">Updated 26 September 2026. This text does not mean every process already meets every legal duty.</p>
<h1>Privacy policy</h1>
<p>Operator: LLC Inova Lab, identification code 432291851. Contact: <a href="mailto:hello@geoapi.ge">hello@geoapi.ge</a>. Site: https://geoapi.ge.</p>
</section>
<section class="panel" id="scope"><h2>Who this covers</h2>
<p>Visitors, account holders, their representatives, people who email the contact address, and visitors of a widget embedded on another site.</p>
<p>This is not the terms of use, a catalog license, or a paid-service contract. Reading the page is not consent to every kind of processing or to marketing.</p>
<p>If the widget is on another company's site, that site's accounts and listings are defined by that operator. GeoAPI only receives the widget's catalog request.</p>
</section>
<section class="panel" id="data"><h2>What is processed</h2>
<p>Registration asks for a name, email, and password. A personal number, phone, VIN, plate, or bank card is not requested. The password is stored only as a hash. The database stores account status, email verification time, created time, and last sign-in time.</p>
<p>A failed sign-in stores the email and IP. The session stores only the user id, an auth version, and session times. The full API key is not stored: the secret part is a hash, and the full key is shown once. The widget public key is not a secret.</p>
<p>A project stores a name, the site you enter, status, and allowed origins. Usage is stored as counts by day and route, not as the full request body.</p>
<p>Email verification and password reset links are stored only as hashes. The message contains the recipient address and the link.</p>
</section>
<section class="panel" id="purposes"><h2>Why</h2>
<p>Not every process is based on consent. We do not claim a contract or a legal duty unless that basis is written separately.</p>
<table>
<tr><th>Purpose</th><th>Basis</th></tr>
<tr><td>Creating an account, signing in, and managing projects and keys</td><td>The service you asked for</td></tr>
<tr><td>Email verification and password reset</td><td>Access to the account and its security, as part of that service</td></tr>
<tr><td>Usage counts and quota</td><td>Delivering the service you asked for and keeping the limit</td></tr>
<tr><td>IP, failed sign-in, and the technical log</td><td>Security and abuse prevention. This is a legitimate interest, not consent</td></tr>
<tr><td>Support email</td><td>A reply to your request</td></tr>
</table>
<p>Registration needs a name, email, and password. Without one of them the account is not created. We do not ask for other data for that.</p>
</section>
<section class="panel" id="widget"><h2>Widget</h2>
<p>The visitor's browser sends GeoAPI the public key, the page origin, and list parameters such as a page number or a model id. The request uses <code>credentials: omit</code> and does not send the GeoAPI session cookie. The IP is used for the limit. Chosen ids and names stay on the host page through the event. GeoAPI does not build a lasting profile of that choice.</p>
</section>
<section class="panel" id="cookies"><h2>Cookies</h2>
<p>The account cookie is named geoapi_platform. It is readable only by the site, is sent over HTTPS, and lasts until the browser closes. The session ends after 30 minutes idle or 12 hours. Signing out deletes the cookie. That does not by itself delete every record at the same moment.</p>
<p>There is no analytics script. The font is a file on this site.</p>
</section>
<section class="panel" id="recipients"><h2>Who processes the data</h2>
<p>The GeoAPI server is in Germany. The host is Contabo. That statement is only about this server. Account and catalog records are stored on it. Service email is sent from this server, from hello@geoapi.ge, and then follows the recipient's mail system.</p>
<p>We do not sell the data or pass it to an independent advertiser. A competent authority can request data when the law allows it.</p>
</section>
<section class="panel" id="retention"><h2>How long it is kept</h2>
<p>There is no delete button. A deletion request is sent by email.</p>
<table>
<tr><th>What</th><th>How long</th></tr>
<tr><td>Account, project, usage, revoked key</td><td>While the account exists, unless you ask for deletion or the law requires something else. Disabling an account or revoking a key is not deletion.</td></tr>
<tr><td>Verification link</td><td>The link works for 24 hours. The row is not deleted by itself when it expires.</td></tr>
<tr><td>Password reset link</td><td>The link works for 30 minutes. The row is not deleted at that minute.</td></tr>
<tr><td>Failed sign-in</td><td>A row older than one day is removed when a new failed sign-in is stored.</td></tr>
<tr><td>Short limit window</td><td>Cleared daily.</td></tr>
<tr><td>Session</td><td>Ends after 30 minutes idle or 12 hours. Signing out deletes the cookie.</td></tr>
<tr><td>Site log</td><td>The active file stores IP, time, method, address without parameters, status, size, and duration. It does not store the password, authorization header, cookie, or request body. The active file closes once a week. A closed copy is kept for 8 cycles.</td></tr>
<tr><td>Mail log</td><td>The active file closes once a week. A closed copy is kept for 4 cycles and may contain the recipient address.</td></tr>
<tr><td>Support email</td><td>12 months after the matter ends. There is no automatic deletion.</td></tr>
<tr><td>Backup</td><td>Automatic backups of account data are not set up at this stage.</td></tr>
</table>
</section>
<section class="panel" id="rights"><h2>Your rights</h2>
<p>A data protection officer is not appointed. Write to hello@geoapi.ge about personal data.</p>
<p>You can ask what we hold and for a copy, for a correction, and for processing to stop or for deletion. Blocking and portability are available when the law provides them. If a specific process is based on consent, you can withdraw that consent. We do not use a marketing consent now.</p>
<p>We answer a deletion or stop-processing request within 10 working days, or we send a reasoned refusal and how to appeal, unless the law sets another period. A copy request has the same 10 working days and can be extended by another 10 in a special case, which we tell you at once. A blocking answer is due when the decision is made, and no later than 3 working days.</p>
<p>Send the request to hello@geoapi.ge. The account holder, a site or widget visitor, a person who lost access to the email, and a properly authorized representative can write. A message from the account email is not the only path and is not always enough proof by itself. We may ask for more, based on the risk. We do not ask for a password, a full API key, or an identity document in every case.</p>
<p>You can apply to the State Audit Office of Georgia or to a court. Writing to us first is not a condition.</p>
</section>
<section class="panel"><h2>Security and limits</h2>
<p>The connection is encrypted. The password and the secret part of an API key are hashed. Requests have a rate limit and a monthly limit. The widget does not score credit, health, or personal traits.</p>
<p>Questions: hello@geoapi.ge.</p>
</section>
</article>
