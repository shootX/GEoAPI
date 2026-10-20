import { chromium } from 'playwright';
import { spawn } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const root = new URL('../../', import.meta.url).pathname;
const shots = '/tmp/carapi-privacy-shots';
mkdirSync(shots, { recursive: true });
const child = spawn('php', ['-S', '127.0.0.1:18131', '-t', `${root}public`, `${root}public/index.php`], {
  env: { ...process.env, CARAPI_PLATFORM_ENABLED: '1', CARAPI_CATALOG_ENABLED: '0', CARAPI_WIDGET_ENABLED: '0' },
  stdio: 'ignore',
});
const failures = [];
const check = (ok, name) => {
  console.log((ok ? 'ok ' : 'FAIL ') + name);
  if (!ok) failures.push(name);
};
const browser = await chromium.launch({ headless: true });
try {
  for (let i = 0; i < 30; i++) {
    try {
      if ((await fetch('http://127.0.0.1:18131/privacy')).status) break;
    } catch {}
    await new Promise((r) => setTimeout(r, 100));
  }
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page.goto('http://127.0.0.1:18131/privacy');
  const text = await page.locator('body').innerText();
  check(text.includes('მონაცემთა დაცვის ოფიცერი დანიშნული არ არის'), 'no officer');
  check(text.includes('არაუგვიანეს 10 სამუშაო დღისა'), 'ten working days');
  check(!text.includes('ასლი არ რჩება') && !text.includes('fonts.googleapis'), 'no overclaim');
  check(text.includes('ელფოსტაზე წვდომა დაკარგა'), 'not only account email');
  check(!text.includes('შიდა დანართი') && !text.includes('რედაქტორის შენიშვნა'), 'no internal notes');
  check(text.includes('432291851') && text.includes('info@socialsave.cc'), 'company details');
  await page.screenshot({ path: `${shots}/desktop.png`, fullPage: true });
  await page.setViewportSize({ width: 390, height: 800 });
  await page.screenshot({ path: `${shots}/mobile.png`, fullPage: true });
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto('http://127.0.0.1:18131/');
  await page.click('footer a[href="/privacy"]');
  check(page.url().endsWith('/privacy'), 'footer link');
  await page.goto('http://127.0.0.1:18131/register');
  const register = await page.locator('body').innerText();
  const linkPos = register.indexOf('კონფიდენციალურობის პოლიტიკა');
  const fieldPos = register.indexOf('სახელი');
  check(linkPos > -1 && fieldPos > linkPos, 'link before registration fields');
  check(register.includes('არ არის თანხმობა'), 'not a blanket consent');
} finally {
  await browser.close();
  child.kill();
}
if (failures.length) {
  console.log('FAILED ' + failures.join(', '));
  process.exit(1);
}
console.log('all passed');
