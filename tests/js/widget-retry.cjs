// Run: node tests/js/widget-retry.cjs  (exit 0 = pass). Called from tests/Unit/WidgetRetryTest.php.
const path = require('path');
const src = require('fs').readFileSync(path.join(__dirname, '../../public/widget/v1/geoapi-filter.js'), 'utf8');
let Cls;
global.HTMLElement = class {};
global.document = { currentScript: { src: 'https://geoapi.ge/widget/v1/geoapi-filter.js' }, querySelectorAll: () => [], documentElement: { lang: 'ka' }, createElement: () => ({}) };
global.customElements = { define: (n, c) => { Cls = c; }, get: () => undefined };
global.window = global;
global.location = { origin: 'https://example.test' };
global.setTimeout = (f) => f();
eval(src);
function run(statuses) {
  let calls = 0;
  global.fetch = async () => {
    const s = statuses[Math.min(calls++, statuses.length - 1)];
    if (s === 'net') throw new TypeError('offline');
    return { status: s, ok: s === 200, headers: { get: () => null }, json: async () => ({ data: [{ id: '1' }], meta: { total: 1 } }) };
  };
  const self = { seq: 0, status: {}, endpoint: (p) => p, pause: Cls.prototype.pause, fill: (s, r) => { self.filled = r; } };
  return Cls.prototype.fetchRows.call(self, 'brands', {}, {}, 'x').then((ok) => ({ ok, calls, rows: self.filled ? self.filled.length : null, cls: self.status.className }));
}
const assert = require('assert');
(async () => {
  let r = await run([503, 429, 200]);
  assert.deepStrictEqual([r.ok, r.calls, r.rows], [true, 3, 1], 'retries 503/429 twice then succeeds');
  r = await run(['net', 200]);
  assert.deepStrictEqual([r.ok, r.calls, r.rows], [true, 2, 1], 'retries a network error');
  r = await run([503, 503, 503, 200]);
  assert.deepStrictEqual([r.ok, r.calls, r.cls], [false, 3, 'err'], 'stops after two retries and shows an error');
  console.log('ok');
})().catch((e) => { console.error(e.message); process.exit(1); });
