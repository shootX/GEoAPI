import { chromium } from 'playwright';
import { spawn } from 'node:child_process';

const root = new URL('../../', import.meta.url).pathname;
const child = spawn('php', ['-S', '127.0.0.1:18141', '-t', `${root}public`, `${root}public/index.php`], {
  env: { ...process.env, CARAPI_PLATFORM_ENABLED: '1', CARAPI_CATALOG_ENABLED: '0', CARAPI_WIDGET_ENABLED: '0' },
  stdio: 'ignore',
});
const browser = await chromium.launch({ headless: true });
const failures = [];
try {
  for (let i = 0; i < 30; i++) {
    try { if ((await fetch('http://127.0.0.1:18141/')).ok) break; } catch {}
    await new Promise((r) => setTimeout(r, 100));
  }
  for (const path of ['/', '/guide', '/privacy', '/register']) {
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
    const hits = [];
    page.on('request', (req) => {
      const url = req.url();
      if (url.includes('fonts.googleapis.com') || url.includes('fonts.gstatic.com')) hits.push(url);
    });
    await page.goto('http://127.0.0.1:18141' + path, { waitUntil: 'networkidle' });
    const sample = await page.evaluate(() => {
      const el = document.querySelector('h1') || document.body;
      const font = getComputedStyle(el).fontFamily;
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      ctx.font = '32px "Noto Sans Georgian"';
      return { font, width: ctx.measureText('ქართული').width };
    });
    const ok = hits.length === 0 && sample.width > 20;
    console.log((ok ? 'ok ' : 'FAIL ') + path + ' hits=' + hits.length + ' width=' + Math.round(sample.width));
    if (!ok) failures.push(path);
    await page.close();
  }
  const mobile = await browser.newPage({ viewport: { width: 390, height: 800 } });
  await mobile.goto('http://127.0.0.1:18141/privacy', { waitUntil: 'networkidle' });
  await mobile.screenshot({ path: '/tmp/carapi-privacy-shots/mobile.png', fullPage: false });
  const desk = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await desk.goto('http://127.0.0.1:18141/privacy', { waitUntil: 'networkidle' });
  await desk.screenshot({ path: '/tmp/carapi-privacy-shots/desktop.png', fullPage: false });
  console.log('shots ok');
} finally {
  await browser.close();
  child.kill();
}
if (failures.length) process.exit(1);
