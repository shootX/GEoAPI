import { chromium } from 'playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const appPort = 18120;
const hostPort = 18121;
execFileSync('php', [join(root, 'tests/browser/bootstrap.php')], { cwd: root, stdio: 'inherit' });
const fx = JSON.parse((await import('node:fs')).readFileSync('/tmp/carapi-browser-fixture.json', 'utf8'));
const children = [];
function serve(port, rootDir, router, extra) {
  const child = spawn('php', router ? ['-S', `127.0.0.1:${port}`, '-t', rootDir, router] : ['-S', `127.0.0.1:${port}`, '-t', rootDir], {
    cwd: root,
    stdio: 'ignore',
    env: {
      ...process.env,
      CARAPI_WIDGET_ENABLED: '1',
      CARAPI_CATALOG_ENABLED: '1',
      CARAPI_PLATFORM_ENABLED: '1',
      CARAPI_CATALOG_HOST: '127.0.0.1',
      CARAPI_CATALOG_PORT: '1',
      CARAPI_CATALOG_CONNECT_TIMEOUT: '1',
      CARAPI_CATALOG_DATABASE: 'carbase',
      CARAPI_CATALOG_USER: fx.catalog_user,
      CARAPI_CATALOG_PASSWORD: fx.catalog_secret,
      CARAPI_PLATFORM_HOST: '127.0.0.1',
      CARAPI_PLATFORM_PORT: '5432',
      CARAPI_PLATFORM_DATABASE: 'carapi_widget_browser',
      CARAPI_PLATFORM_USER: 'carapi_platform_app',
      CARAPI_PLATFORM_PASSWORD: fx.platform_secret,
      CARAPI_IP_PER_MINUTE: '1000',
      ...extra,
    },
  });
  children.push(child);
}
mkdirSync('/tmp/carapi-503-host', { recursive: true });
writeFileSync('/tmp/carapi-503-host/index.html', `<!doctype html><meta charset="utf-8"><script src="http://127.0.0.1:${appPort}/widget/v1/carapi-filter.js"></script><carapi-filter public-key="${fx.public_key}"></carapi-filter>`);
const origins = JSON.stringify([`http://127.0.0.1:${hostPort}`, `http://127.0.0.1:${appPort}`]);
execFileSync('php', ['-r', `$db=new PDO('pgsql:host=127.0.0.1;dbname=carapi_widget_browser','postgres',''); $db->prepare('UPDATE platform.widgets SET allowed_origins = :o::jsonb')->execute(['o'=>'${origins}']);`]);
serve(appPort, join(root, 'public'), join(root, 'public/index.php'));
serve(hostPort, '/tmp/carapi-503-host', null);
await new Promise((r) => setTimeout(r, 400));
const before = execFileSync('php', ['-r', `$db=new PDO('pgsql:host=127.0.0.1;dbname=carapi_widget_browser','postgres',''); echo (int)$db->query('SELECT coalesce(sum(used_count),0) FROM platform.monthly_usage')->fetchColumn();`]).toString().trim();
const browser = await chromium.launch({ headless: true });
let posts = 0;
try {
  const page = await browser.newPage();
  page.on('response', (res) => {
    if (res.request().method() === 'POST' && res.url().includes('/widget/v1/')) posts += 1;
  });
  await page.goto(`http://127.0.0.1:${hostPort}/`);
  await page.waitForTimeout(2500);
  const text = await page.locator('carapi-filter p').innerText();
  const status = await page.evaluate(async ({ key, port }) => {
    const response = await fetch(`http://127.0.0.1:${port}/widget/v1/${key}/brands`, {
      method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json' }, body: '{}',
    });
    return response.status;
  }, { key: fx.public_key, port: appPort });
  console.log(text.includes('დროებით მიუწვდომელია') ? 'ok widget 503 message' : 'FAIL widget 503 message');
  console.log(status === 503 ? 'ok browser read 503' : 'FAIL browser read 503 ' + status);
  console.log(posts <= 2 ? 'ok no retry loop ' + posts : 'FAIL retry loop ' + posts);
} finally {
  await browser.close();
  for (const child of children) child.kill();
}
const after = execFileSync('php', ['-r', `$db=new PDO('pgsql:host=127.0.0.1;dbname=carapi_widget_browser','postgres',''); echo (int)$db->query('SELECT coalesce(sum(used_count),0) FROM platform.monthly_usage')->fetchColumn();`]).toString().trim();
console.log(before === after ? 'ok quota unchanged ' + after : 'FAIL quota changed ' + before + ' ' + after);
execFileSync('php', ['-r', "(new PDO('pgsql:host=127.0.0.1;dbname=postgres','postgres',''))->exec('DROP DATABASE IF EXISTS carapi_widget_browser WITH (FORCE)');"]);
rmSync('/tmp/carapi-browser-fixture.json', { force: true });
rmSync('/tmp/carapi-503-host', { recursive: true, force: true });
