import { chromium } from 'playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, rmSync } from 'node:fs';

const root = new URL('../../', import.meta.url).pathname;
const shots = '/tmp/carapi-site-shots';
mkdirSync(shots, { recursive: true });
const mail = '/tmp/carapi-site-mail.log';
rmSync(mail, { force: true });
const catalog = Object.fromEntries(
  readFileSync('/www/server/carapi/catalog.env', 'utf8')
    .split('\n')
    .filter((line) => line.includes('='))
    .map((line) => {
      const i = line.indexOf('=');
      return [line.slice(0, i), line.slice(i + 1)];
    })
);
const platformPassword = readFileSync('/www/server/carapi/platform.env', 'utf8')
  .split('\n')
  .find((line) => line.startsWith('CARAPI_PLATFORM_PASSWORD='))
  .slice('CARAPI_PLATFORM_PASSWORD='.length);

function start(port, env) {
  const child = spawn(process.env.PHP_BINARY || 'php', ['-S', `127.0.0.1:${port}`, '-t', `${root}public`, `${root}public/index.php`], {
    cwd: root,
    env: { ...process.env, ...env },
    stdio: 'ignore',
  });
  return child;
}

async function wait(port) {
  for (let i = 0; i < 40; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/`);
      if (res.status) return;
    } catch {}
    await new Promise((r) => setTimeout(r, 100));
  }
  throw new Error('server down ' + port);
}

const failures = [];
const check = (ok, name) => {
  console.log((ok ? 'ok ' : 'FAIL ') + name);
  if (!ok) failures.push(name);
};

execFileSync('php', [`${root}tests/browser/site-prepare.php`], { stdio: 'inherit' });
const closed = start(18121, {
  CARAPI_PLATFORM_ENABLED: '0',
  CARAPI_CATALOG_ENABLED: '0',
  CARAPI_WIDGET_ENABLED: '0',
});
const open = start(18122, {
  ...catalog,
  CARAPI_CATALOG_ENABLED: '1',
  CARAPI_PLATFORM_ENABLED: '1',
  CARAPI_WIDGET_ENABLED: '1',
  CARAPI_PLATFORM_HOST: '127.0.0.1',
  CARAPI_PLATFORM_PORT: '5432',
  CARAPI_PLATFORM_DATABASE: 'carapi_site_browser',
  CARAPI_PLATFORM_USER: 'carapi_platform_app',
  CARAPI_PLATFORM_PASSWORD: platformPassword,
  CARAPI_PLATFORM_SESSION_PATH: '/tmp/carapi-site-sessions',
  CARAPI_MAIL_TRANSPORT: 'fake',
  CARAPI_MAIL_FAKE_PATH: mail,
  CARAPI_APP_URL: 'http://127.0.0.1:18122',
});
const broken = start(18123, {
  CARAPI_PLATFORM_ENABLED: '1',
  CARAPI_CATALOG_ENABLED: '1',
  CARAPI_WIDGET_ENABLED: '0',
  CARAPI_CATALOG_HOST: '127.0.0.1',
  CARAPI_CATALOG_PORT: '1',
  CARAPI_CATALOG_DATABASE: 'carbase',
  CARAPI_CATALOG_USER: 'carapi_catalog_reader',
  CARAPI_CATALOG_PASSWORD: 'invalid',
});

const browser = await chromium.launch({ headless: true });
try {
  await wait(18121);
  await wait(18122);
  await wait(18123);
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  await page.goto('http://127.0.0.1:18121/');
  const home = await page.content();
  check(home.includes('მალე გაიხსნება'), 'closed status');
  check(!home.includes('პირადი ნომერი') && !home.includes('Wikimedia') && !home.includes('No API key'), 'no old promises');
  check(!home.includes('href="/register"'), 'closed home has no register link');
  await page.screenshot({ path: `${shots}/desktop.png` });
  await page.setViewportSize({ width: 390, height: 800 });
  await page.screenshot({ path: `${shots}/mobile.png` });
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.click('a[href="/guide"]');
  check(page.url().endsWith('/guide'), 'guide from menu');
  const guide = await page.content();
  check(guide.includes('მაგალითი') && guide.includes('SERVER_API_KEY') && guide.includes('carapi:change') && !guide.includes('ერთი მოთხოვნით'), 'guide examples');
  await page.goto('http://127.0.0.1:18121/register');
  check((await page.content()).includes('საჯარო გაშვება ჯერ არ დაწყებულა') && !(await page.content()).includes('პირადი ნომერი'), 'register stays closed');

  await page.goto('http://127.0.0.1:18122/');
  const counts = await page.locator('#counts').textContent();
  check(/\d/.test(counts) && !counts.includes('მიუწვდომელია'), 'live counts');
  rmSync(`${root}cache/public-counts.json`, { force: true });
  const brokenPage = await browser.newPage();
  await brokenPage.goto('http://127.0.0.1:18123/', { waitUntil: 'domcontentloaded', timeout: 10000 });
  check((await brokenPage.locator('#counts').textContent()).includes('მიუწვდომელია'), 'catalog error is not a fake number');
  await brokenPage.close();
  const flow = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  const reg = await flow.goto('http://127.0.0.1:18122/register', { waitUntil: 'domcontentloaded', timeout: 10000 });
  const regHtml = await flow.content();
  if (!regHtml.includes('name="email"')) {
    console.log('register-url', flow.url(), 'status', reg ? reg.status() : 0);
    console.log(regHtml.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 400));
    throw new Error('register form missing');
  }
  await flow.fill('input[name="first_name"]', 'ტესტ');
  await flow.fill('input[name="last_name"]', 'მომხმარე');
  await flow.fill('input[name="email"]', 'site-flow@example.com');
  await flow.fill('input[name="password"]', 'Carapi-test-pass-1');
  await flow.fill('input[name="password_confirmation"]', 'Carapi-test-pass-1');
  await flow.check('input[name="accept_terms"]');
  await flow.click('button[type="submit"]');
  await flow.waitForURL('**/account');
  const letter = readFileSync(mail, 'utf8');
  const token = letter.match(/token=([^"\\]+)/)[1];
  await flow.goto('http://127.0.0.1:18122/email/verify?token=' + token);
  await flow.click('button[type="submit"]');
  await flow.goto('http://127.0.0.1:18122/account');
  await flow.fill('input[name="name"]', 'სატესტო პროექტი');
  await flow.click('text=პროექტის შექმნა');
  await flow.click('a[href^="/account/projects/"]');
  await flow.screenshot({ path: `${shots}/cabinet.png`, fullPage: true });
  check((await flow.locator('#embed-code').inputValue()).includes('carapi-filter'), 'embed code');
  check((await flow.content()).includes('მოთხოვნები ამ თვეში'), 'usage from platform');
  await flow.click('#copy-embed');
  const origin = 'http://127.0.0.1:18122';
  await flow.fill('textarea[name="allowed_origins"]', origin);
  await flow.locator('textarea[name="allowed_origins"]').locator('xpath=ancestor::form').locator('button').click();
  await flow.locator('input[name="action"][value="set_widget"]').locator('xpath=ancestor::form').locator('button').click();
  await flow.click('text=წინასწარი ნახვა');
  check(flow.url().includes('/preview'), 'preview');
  await flow.waitForTimeout(500);
  check((await flow.locator('carapi-filter').count()) === 1, 'preview widget');
} finally {
  await browser.close();
  closed.kill();
  open.kill();
  broken.kill();
  execFileSync('php', ['-r', 'new PDO("pgsql:host=127.0.0.1;dbname=postgres","postgres","",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION])->exec("DROP DATABASE IF EXISTS carapi_site_browser WITH (FORCE)");'], { stdio: 'ignore' });
}
if (failures.length) {
  console.log('FAILED ' + failures.join(', '));
  process.exit(1);
}
console.log('all passed');
