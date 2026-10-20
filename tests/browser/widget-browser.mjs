import { chromium } from 'playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, rmSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const appPort = 18110;
const hostPort = 18111;
const workerPort = 18112;
const failures = [];
const notes = [];
function check(ok, name) {
  console.log((ok ? 'ok ' : 'FAIL ') + name);
  if (!ok) failures.push(name);
}

execFileSync('php', [join(root, 'tests/browser/bootstrap.php')], { cwd: root, stdio: 'inherit' });
const fx = JSON.parse(readFileSync('/tmp/carapi-browser-fixture.json', 'utf8'));
const children = [];

function phpServer(port, docroot, router) {
  const env = {
    ...process.env,
    CARAPI_WIDGET_ENABLED: '1',
    CARAPI_CATALOG_ENABLED: '1',
    CARAPI_PLATFORM_ENABLED: '1',
    CARAPI_CATALOG_HOST: '127.0.0.1',
    CARAPI_CATALOG_PORT: '5432',
    CARAPI_CATALOG_DATABASE: 'carbase',
    CARAPI_CATALOG_USER: fx.catalog_user,
    CARAPI_CATALOG_PASSWORD: fx.catalog_secret,
    CARAPI_PLATFORM_HOST: '127.0.0.1',
    CARAPI_PLATFORM_PORT: '5432',
    CARAPI_PLATFORM_DATABASE: 'carapi_widget_browser',
    CARAPI_PLATFORM_USER: 'carapi_platform_app',
    CARAPI_PLATFORM_PASSWORD: fx.platform_secret,
    CARAPI_PLATFORM_SESSION_PATH: '/tmp/carapi-browser-sessions',
    CARAPI_IP_PER_MINUTE: '1000',
    CARAPI_APP_URL: `http://127.0.0.1:${appPort}`,
    CARAPI_MAIL_TRANSPORT: 'fake',
  };
  const args = ['-S', `127.0.0.1:${port}`, '-t', docroot];
  if (router) args.push(router);
  const child = spawn('php', args, { cwd: root, env, stdio: 'ignore' });
  children.push(child);
}

mkdirSync('/tmp/carapi-browser-sessions', { recursive: true });
mkdirSync('/tmp/carapi-browser-host', { recursive: true });
writeFileSync('/tmp/carapi-browser-host/index.html', `<!doctype html><html lang="ka"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>
<script src="http://127.0.0.1:${appPort}/widget/v1/carapi-filter.js"></script>
<carapi-filter id="a" public-key="${fx.public_key}"></carapi-filter>
<carapi-filter id="b" public-key="${fx.public_key}"></carapi-filter>
<pre id="out"></pre>
<script>
document.getElementById('a').addEventListener('carapi:change', (e) => { document.getElementById('out').textContent = 'A ' + JSON.stringify(e.detail); });
document.getElementById('b').addEventListener('carapi:change', (e) => { document.getElementById('out').textContent += '\\nB ' + JSON.stringify(e.detail); });
</script></body></html>`);

phpServer(appPort, join(root, 'public'), join(root, 'public/index.php'));
phpServer(workerPort, join(root, 'public'), join(root, 'public/index.php'));
phpServer(hostPort, '/tmp/carapi-browser-host', null);
await new Promise((r) => setTimeout(r, 400));

const browser = await chromium.launch({ headless: true });
const browserName = browser.version();
notes.push('chromium ' + browserName);

async function widget(page, id) {
  return page.locator(id).locator('select');
}

try {
  const host = await browser.newPage();
  const requests = [];
  host.on('request', (req) => requests.push(req.url()));
  await host.setViewportSize({ width: 390, height: 844 });
  await host.goto(`http://127.0.0.1:${hostPort}/`);
  const brand = host.locator('#a').locator('select').nth(0);
  await host.waitForFunction(() => document.querySelector('#a').shadowRoot.querySelectorAll('select')[0].options.length > 101, null, { timeout: 20000 });
  const optionCount = await brand.locator('option').count();
  check(optionCount > 101, 'pagination loads past first page');
  const box = await brand.boundingBox();
  check(box && box.width < 390, 'mobile width fits');
  await brand.focus();
  await host.keyboard.press('ArrowDown');
  await host.keyboard.press('Enter');
  check(requests.some((url) => url.startsWith(`http://127.0.0.1:${appPort}/widget/v1/`)), 'requests go to app api');
  check(requests.every((url) => !url.includes(String(hostPort) + '/widget/v1/')), 'requests do not hit host origin api');
  check(requests.every((url) => !url.includes('carapi_sk_') && !url.includes('/getImage')), 'no server key or photo request');
  const html = await host.content();
  check(!html.includes('carapi_sk_'), 'embed html has no server key');
  const js = await (await fetch(`http://127.0.0.1:${appPort}/widget/v1/carapi-filter.js`)).text();
  check(!js.includes('carapi_sk_'), 'widget js has no server key');

  const model = host.locator('#a').locator('select').nth(1);
  await host.locator('#a').locator('select').nth(0).selectOption({ index: 1 });
  await host.waitForFunction(() => document.querySelector('#a').shadowRoot.querySelectorAll('select')[1].options.length > 1, null, { timeout: 20000 });
  await model.selectOption({ index: 1 });
  const generation = host.locator('#a').locator('select').nth(2);
  await host.waitForTimeout(1500);
  const detailText = await host.locator('#out').innerText();
  check(detailText.includes('"modification_id":null') && detailText.includes('"model_id":"'), 'change event has string ids');

  await host.locator('#a').locator('select').nth(0).selectOption({ index: 2 });
  const modelValue = await model.inputValue();
  const generationValue = await generation.inputValue();
  check(modelValue === '' && generationValue === '', 'brand change clears model and generation');

  const other = await host.locator('#b').locator('select').nth(0).inputValue();
  check(other === '', 'second widget stays independent');

  await host.route('**/models', async (route) => {
    await new Promise((r) => setTimeout(r, 800));
    await route.continue();
  });
  const firstSlug = await host.locator('#a').locator('select').nth(0).locator('option').nth(1).getAttribute('data-slug');
  await host.locator('#a').locator('select').nth(0).selectOption({ index: 1 });
  await host.locator('#a').locator('select').nth(0).selectOption({ index: 3 });
  await host.waitForTimeout(1200);
  const selectedSlug = await host.locator('#a').locator('select').nth(0).locator('option:checked').getAttribute('data-slug');
  const modelNames = await host.locator('#a').locator('select').nth(1).locator('option').allTextContents();
  check(selectedSlug !== firstSlug, 'fast switch keeps the later brand');
  notes.push('fast-switch later slug ' + selectedSlug + ' options ' + modelNames.length);

  await host.unroute('**/models');
  const emptyBrand = fx.empty_brand_slug;
  await host.locator('#a').locator('select').nth(0).selectOption({ label: undefined }).catch(() => {});
  const brandOptions = host.locator('#a').locator('select').nth(0).locator('option');
  const count = await brandOptions.count();
  let emptyIndex = -1;
  for (let i = 0; i < count; i++) {
    const slug = await brandOptions.nth(i).getAttribute('data-slug');
    if (slug === emptyBrand) emptyIndex = i;
  }
  check(emptyIndex > 0, 'empty-generation brand is listed');
  if (emptyIndex > 0) {
    await host.locator('#a').locator('select').nth(0).selectOption({ index: emptyIndex });
    await host.waitForFunction(() => document.querySelector('#a').shadowRoot.querySelectorAll('select')[1].options.length > 1, null, { timeout: 60000 });
    const modelOptions = host.locator('#a').locator('select').nth(1).locator('option');
    const modelCount = await modelOptions.count();
    let modelIndex = -1;
    for (let i = 0; i < modelCount; i++) {
      const value = await modelOptions.nth(i).getAttribute('value');
      if (value === fx.empty_model_id) modelIndex = i;
    }
    if (modelIndex > 0) {
      await host.locator('#a').locator('select').nth(1).selectOption({ index: modelIndex });
      await host.waitForTimeout(500);
      const status = await host.locator('#a').locator('p').innerText();
      check(status.includes('წლები ჯერ არ არის'), 'empty years message');
    } else {
      check(false, 'empty generations message');
    }
  }

  const preview = await browser.newPage();
  await preview.goto(`http://127.0.0.1:${appPort}/login`);
  await preview.fill('input[name="email"]', fx.email);
  await preview.fill('input[name="password"]', fx.password);
  await preview.click('button[type="submit"]');
  await preview.goto(`http://127.0.0.1:${appPort}/account/projects/${fx.project_id}/preview`);
  await preview.waitForFunction(() => document.querySelector('carapi-filter').shadowRoot.querySelector('select').options.length > 1, null, { timeout: 20000 });
  check((await preview.locator('carapi-filter select').nth(0).locator('option').count()) > 1, 'same-origin preview loads brands');

  const blocked = await browser.newPage();
  await blocked.setContent(`<script src="http://127.0.0.1:${appPort}/widget/v1/carapi-filter.js"></script><carapi-filter public-key="${fx.public_key}"></carapi-filter><p id="s"></p>`);
  await blocked.waitForTimeout(1500);
  const blockedText = await blocked.locator('carapi-filter p').innerText();
  check(blockedText.includes('ვერ ჩაიტვირთა') || blockedText.includes('ქსელ'), 'disallowed origin does not yield data');

  execFileSync('php', ['-r', `$db=new PDO('pgsql:host=127.0.0.1;dbname=carapi_widget_browser','postgres',''); $db->exec("INSERT INTO platform.monthly_usage (project_id, period, used_count) VALUES (${fx.project_id}, date_trunc('month', now() AT TIME ZONE 'utc')::date, 1) ON CONFLICT (project_id, period) DO UPDATE SET used_count = 1"); $db->exec('UPDATE platform.project_limits SET monthly_quota = 1 WHERE project_id = ${fx.project_id}');`]);
  const limited = await browser.newPage();
  await limited.goto(`http://127.0.0.1:${hostPort}/`);
  await limited.waitForTimeout(1500);
  const limitedText = await limited.locator('#a p').innerText();
  check(limitedText.includes('ლიმიტი ამოიწურა'), 'real 429 message in widget');
  execFileSync('php', ['-r', `$db=new PDO('pgsql:host=127.0.0.1;dbname=carapi_widget_browser','postgres',''); $db->exec('UPDATE platform.project_limits SET monthly_quota = 100000 WHERE project_id = ${fx.project_id}');`]);

  await host.route('**/widget/v1/**', (route) => route.abort('failed'));
  const net = await browser.newPage();
  await net.route('**/widget/v1/**', (route) => {
    if (route.request().method() === 'POST') {
      return route.abort('failed');
    }
    return route.continue();
  });
  await net.goto(`http://127.0.0.1:${hostPort}/`);
  await net.waitForTimeout(800);
  const netText = await net.locator('#a p').innerText();
  const calls = [];
  net.on('request', (req) => { if (req.url().includes('/widget/v1/') && req.method() === 'POST') calls.push(req.url()); });
  await net.waitForTimeout(1500);
  check(netText.includes('ქსელის შეცდომა'), 'artificial network failure shows message');
  notes.push('503/network path was Playwright route.abort, not a real backend 503');
  await host.unroute('**/widget/v1/**');

  execFileSync('php', ['-r', `$db=new PDO('pgsql:host=127.0.0.1;dbname=carapi_widget_browser','postgres',''); $db->exec("UPDATE platform.widgets SET enabled = false WHERE project_id = ${fx.project_id}");`]);
  const off = await browser.newPage();
  await off.goto(`http://127.0.0.1:${hostPort}/`);
  await off.waitForTimeout(1000);
  check((await off.locator('#a p').innerText()).includes('ვერ ჩაიტვირთა'), 'disabled widget rejected in browser');
  execFileSync('php', ['-r', `$db=new PDO('pgsql:host=127.0.0.1;dbname=carapi_widget_browser','postgres',''); $db->exec("UPDATE platform.widgets SET enabled = true WHERE project_id = ${fx.project_id}"); $db->exec("UPDATE platform.users SET status = 'suspended'");`]);
  const sus = await browser.newPage();
  await sus.goto(`http://127.0.0.1:${hostPort}/`);
  await sus.waitForTimeout(1000);
  check((await sus.locator('#a p').innerText()).includes('ვერ ჩაიტვირთა'), 'suspended owner rejected in browser');
  execFileSync('php', ['-r', `$db=new PDO('pgsql:host=127.0.0.1;dbname=carapi_widget_browser','postgres',''); $db->exec("UPDATE platform.users SET status = 'active'"); $db->exec("UPDATE platform.widgets SET public_key = 'carapi_pk_' || md5(random()::text) WHERE project_id = ${fx.project_id}");`]);
  const rotated = await browser.newPage();
  await rotated.goto(`http://127.0.0.1:${hostPort}/`);
  await rotated.waitForTimeout(1000);
  const rotatedText = await rotated.locator('#a p').innerText();
  check(rotatedText.includes('ვერ ჩაიტვირთა') || rotatedText.includes('ქსელის შეცდომა'), 'rotated public key rejected in browser');

  execFileSync('php', ['-r', `$db=new PDO('pgsql:host=127.0.0.1;dbname=carapi_widget_browser','postgres',''); $db->exec('DELETE FROM platform.monthly_usage'); $db->exec('DELETE FROM platform.daily_usage'); $db->exec('UPDATE platform.project_limits SET monthly_quota = 1, per_minute = 1000 WHERE project_id = ${fx.project_id}'); $db->exec("UPDATE platform.widgets SET public_key = '${fx.public_key}' WHERE project_id = ${fx.project_id}");`]);
  const start = Date.now() + 250;
  const both = await Promise.all([
    delayed(appPort, start, 'api'),
    delayed(workerPort, start, 'widget'),
  ]);
  const codes = both.map((item) => item.status);
  check(codes.filter((code) => code === 200).length === 1 && codes.includes(429), 'cross-channel parallel one 200 and one 429');
  const loser = both.find((item) => item.status === 429);
  check(Boolean(loser && loser.body.includes('MONTHLY_QUOTA_EXCEEDED') && !loser.body.includes('"slug"')), 'loser has no catalog rows');
  const counts = execFileSync('php', ['-r', `$db=new PDO('pgsql:host=127.0.0.1;dbname=carapi_widget_browser','postgres',''); echo (int)$db->query('SELECT coalesce(sum(used_count),0) FROM platform.monthly_usage')->fetchColumn(), ' ', (int)$db->query('SELECT coalesce(sum(used_count),0) FROM platform.daily_usage')->fetchColumn();`]).toString().trim();
  check(counts === '1 1', 'monthly and daily incremented once');
  notes.push('parallel statuses ' + codes.join(','));
} finally {
  if (browser) await browser.close();
  for (const child of children) child.kill();
  execFileSync('php', ['-r', "(new PDO('pgsql:host=127.0.0.1;dbname=postgres','postgres',''))->exec('DROP DATABASE IF EXISTS carapi_widget_browser WITH (FORCE)');"]);
  rmSync('/tmp/carapi-browser-fixture.json', { force: true });
  rmSync('/tmp/carapi-browser-host', { recursive: true, force: true });
}

if (failures.length) {
  console.log(failures.length + ' failed');
  process.exit(1);
}
console.log('all passed');
console.log(notes.join('\n'));

function delayed(port, at, kind) {
  return new Promise((resolve) => {
    const wait = Math.max(0, at - Date.now());
    setTimeout(async () => {
      const url = kind === 'api'
        ? `http://127.0.0.1:${port}/api/v1/brands?per_page=1`
        : `http://127.0.0.1:${port}/widget/v1/${fx.public_key}/brands`;
      const response = await fetch(url, kind === 'api'
        ? { headers: { Authorization: 'Bearer ' + fx.server_key } }
        : { method: 'POST', headers: { Origin: `http://127.0.0.1:${hostPort}`, 'Content-Type': 'application/json' }, body: '{}' });
      resolve({ status: response.status, body: await response.text() });
    }, wait);
  });
}
