import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const source = readFileSync(join(root, 'src/Platform/View.php'), 'utf8');
const start = source.indexOf('<script>\n(function () {\n  var state = {brand:');
const end = source.indexOf('</script>', start);
if (start < 0 || end < 0) {
  console.error('FAIL explorer script not found');
  process.exit(1);
}
const script = source.slice(start + '<script>\n'.length, end);
const failures = [];
const check = (ok, name) => {
  console.log((ok ? 'ok ' : 'FAIL ') + name);
  if (!ok) failures.push(name);
};
const pending = [];
let rejectOnAbort = true;

function response(status, payload) {
  return { status, json: async () => payload };
}
function catalog(data, total = data.length) {
  return response(200, { body: { data, meta: { total } } });
}
function failed(status) {
  return response(status, { ok: false, error: { message: 'failed' } });
}

class El {
  constructor(tag, id = '') {
    this.tag = tag;
    this.id = id;
    this.hidden = false;
    this.disabled = false;
    this.textContent = '';
    this.value = '';
    this.name = '';
    this.action = '';
    this.children = [];
    this.listeners = {};
  }
  get options() { return this.children; }
  get length() { return this.children.length; }
  get firstChild() { return this.children[0] || null; }
  appendChild(child) {
    this.children.push(child);
    return child;
  }
  removeChild(child) {
    const index = this.children.indexOf(child);
    if (index >= 0) this.children.splice(index, 1);
    return child;
  }
  remove(index) { this.children.splice(index, 1); }
  addEventListener(type, fn) {
    (this.listeners[type] ||= []).push(fn);
  }
  dispatch(type) {
    for (const fn of this.listeners[type] || []) fn({ type });
  }
  querySelector(selector) {
    if (selector === '[name=_csrf]') return this.children.find((child) => child.name === '_csrf') || null;
    return null;
  }
}

const ids = {};
function byId(id) {
  return ids[id] || null;
}
function make(tag, id, extra = {}) {
  const el = new El(tag, id);
  Object.assign(el, extra);
  if (id) ids[id] = el;
  return el;
}
function option(value, text) {
  const el = new El('option');
  el.value = value;
  el.textContent = text;
  return el;
}
function buildDom() {
  for (const key of Object.keys(ids)) delete ids[key];
  const form = make('form', 'explorer-form', { action: '/account/projects/1/playground/run' });
  const csrf = new El('input');
  csrf.name = '_csrf';
  csrf.value = 'csrf-token';
  form.appendChild(csrf);
  for (const name of ['brand', 'model', 'generation', 'modification']) {
    const select = make('select', 'explorer-' + name, { disabled: true, value: '' });
    select.appendChild(option('', name === 'brand' ? 'აირჩიეთ ბრენდი' : 'ჯერ არ არის ხელმისაწვდომი'));
  }
  make('p', 'explorer-brand-help');
  make('p', 'explorer-model-help', { textContent: 'ჯერ აირჩიეთ ბრენდი.' });
  make('p', 'explorer-generation-help', { textContent: 'ჯერ აირჩიეთ მოდელი.' });
  make('p', 'explorer-modification-help', { textContent: 'ჯერ აირჩიეთ თაობა.' });
  make('p', 'explorer-status');
  make('p', 'explorer-model-status');
  make('p', 'explorer-generation-status');
  make('p', 'explorer-modification-status');
  make('button', 'explorer-more', { hidden: true, disabled: true, textContent: 'მეტი ბრენდის ჩატვირთვა' });
  make('button', 'explorer-more-models', { hidden: true, disabled: true, textContent: 'მეტი მოდელის ჩატვირთვა' });
  make('button', 'explorer-more-generations', { hidden: true, disabled: true, textContent: 'მეტი თაობის ჩატვირთვა' });
  make('button', 'explorer-more-modifications', { hidden: true, disabled: true, textContent: 'მეტი მოდიფიკაციის ჩატვირთვა' });
  make('div', 'explorer-detail', { hidden: true });
  make('dl', 'explorer-detail-list');
  globalThis.document = { getElementById: byId, createElement: (tag) => new El(tag) };
}
function installFetch() {
  pending.length = 0;
  globalThis.fetch = (_url, opts = {}) => new Promise((resolve, reject) => {
    const req = { fields: {}, aborted: false, settled: false, opts };
    if (opts.body && typeof opts.body.entries === 'function') {
      for (const [key, value] of opts.body.entries()) req.fields[key] = String(value);
    }
    const settle = (fn, arg) => {
      if (req.settled) return;
      req.settled = true;
      fn(arg);
    };
    req.resolve = (value) => settle(resolve, value);
    req.reject = (error) => settle(reject, error);
    if (opts.signal) {
      opts.signal.addEventListener('abort', () => {
        req.aborted = true;
        if (rejectOnAbort) req.reject(new DOMException('aborted', 'AbortError'));
      });
    }
    pending.push(req);
  });
}
const tick = () => new Promise((resolve) => setTimeout(resolve, 0));
function values(select) {
  return select.children.slice(1).map((item) => item.value);
}
function texts(select) {
  return select.children.slice(1).map((item) => item.textContent);
}
function choose(select, value) {
  select.value = value;
  select.dispatch('change');
}
function latest(operation) {
  return [...pending].reverse().find((req) => req.fields.operation === operation);
}
async function boot() {
  buildDom();
  installFetch();
  new Function(script)();
  await tick();
}
async function brands(rows = [{ slug: 'alfa', name: 'Alfa' }, { slug: 'bmw', name: 'BMW' }, { slug: 'opel', name: 'Opel' }], total = rows.length) {
  await boot();
  const req = latest('/api/v1/brands');
  req.resolve(catalog(rows, total));
  await tick();
  return req;
}
async function modelsFor(slug, rows, total = rows ? rows.length : 0) {
  choose(byId('explorer-brand'), slug);
  await tick();
  const req = latest('/api/v1/brands/{slug}/models');
  if (rows) {
    req.resolve(catalog(rows, total));
    await tick();
  }
  return req;
}

rejectOnAbort = true;
await brands();
check(byId('explorer-model-help').textContent === 'ჯერ აირჩიეთ ბრენდი.' && byId('explorer-generation-help').textContent === 'ჯერ აირჩიეთ მოდელი.' && byId('explorer-modification-help').textContent === 'ჯერ აირჩიეთ თაობა.', 'downstream selectors explain why they are disabled');
const modelsA = await modelsFor('alfa');
check(byId('explorer-model-help').textContent === '' && byId('explorer-model-status').textContent === 'მოდელები იტვირთება.' && byId('explorer-generation-help').textContent === 'ჯერ აირჩიეთ მოდელი.', 'brand selection announces model loading without a stale brand requirement');
check(modelsA.fields.slug === 'alfa' && modelsA.fields.page === '1' && modelsA.fields._csrf === 'csrf-token', 'brand A starts the models request');
choose(byId('explorer-brand'), 'bmw');
await tick();
const modelsB = latest('/api/v1/brands/{slug}/models');
check(modelsA.aborted && modelsA.settled && modelsB !== modelsA && modelsB.fields.slug === 'bmw' && modelsB.fields.page === '1', 'brand B cancels A and starts immediately');
modelsA.resolve(catalog([{ id: '11', name: 'Stale' }]));
await tick();
check(!texts(byId('explorer-model')).includes('Stale') && byId('explorer-model-status').textContent !== 'ქსელის შეცდომა.' && byId('explorer-model-help').textContent !== 'ქსელის შეცდომა.', 'cancelled models request does not show a network error');
byId('explorer-more-models').dispatch('click');
await tick();
check(latest('/api/v1/brands/{slug}/models') === modelsB, 'stale finally does not release the newer models request');
modelsB.resolve(catalog([{ id: '20', name: 'Three' }]));
await tick();
check(values(byId('explorer-model')).join() === '20' && texts(byId('explorer-model')).join() === 'Three' && byId('explorer-model-help').textContent === '' && byId('explorer-model-status').textContent === 'ჩატვირთულია 1 / 1', 'brand B models populate');

rejectOnAbort = false;
await brands();
const lateA = await modelsFor('alfa');
choose(byId('explorer-brand'), 'bmw');
await tick();
const lateB = latest('/api/v1/brands/{slug}/models');
lateA.resolve(catalog([{ id: '11', name: 'Stale' }], 1));
await tick();
check(values(byId('explorer-model')).length === 0, 'late models response is ignored');
lateB.resolve(catalog([{ id: '20', name: 'Three' }], 1));
await tick();
check(values(byId('explorer-model')).join() === '20', 'current models response still populates');
rejectOnAbort = true;

await brands();
const firstPage = await modelsFor('alfa', [{ id: '20', name: 'Three' }], 60);
check(!byId('explorer-more-models').hidden, 'model load-more is visible');
byId('explorer-more-models').dispatch('click');
await tick();
const secondPage = latest('/api/v1/brands/{slug}/models');
check(secondPage !== firstPage && secondPage.fields.page === '2' && secondPage.fields.slug === 'alfa', 'model pagination requests the next page');
choose(byId('explorer-brand'), 'bmw');
await tick();
const switched = latest('/api/v1/brands/{slug}/models');
secondPage.resolve(catalog([{ id: '99', name: 'Late page' }], 60));
await tick();
check(secondPage.aborted && switched.fields.slug === 'bmw' && switched.fields.page === '1' && !values(byId('explorer-model')).includes('99'), 'cancelled model page does not append or advance the new brand');
switched.resolve(catalog([{ id: '30', name: 'Five' }], 1));
await tick();
check(values(byId('explorer-model')).join() === '30', 'new brand keeps its own first page');

await brands();
await modelsFor('alfa');
await modelsFor('bmw');
const rapid = [];
for (const slug of ['alfa', 'bmw', 'opel', 'alfa', 'opel']) {
  choose(byId('explorer-brand'), slug);
  await tick();
  rapid.push(latest('/api/v1/brands/{slug}/models'));
}
check(rapid.slice(0, -1).every((req) => req.aborted) && !rapid.at(-1).aborted && rapid.at(-1).fields.slug === 'opel', 'rapid brand changes keep only the latest models request');
rapid.at(-1).resolve(catalog([{ id: '40', name: 'Corsa' }], 1));
await tick();
check(values(byId('explorer-model')).join() === '40', 'latest rapid brand populates');

await brands();
const failedModels = await modelsFor('alfa');
failedModels.resolve(failed(500));
await tick();
check(byId('explorer-more-models').hidden === false && byId('explorer-more-models').textContent === 'თავიდან ცდა' && byId('explorer-more-models').disabled === false && byId('explorer-model-help').textContent === 'თავიდან ცდა ხელმისაწვდომია.' && byId('explorer-model-status').textContent === 'სერვერის შეცდომა.', 'failed models request can be retried');
const beforeRetry = pending.length;
byId('explorer-more-models').dispatch('click');
byId('explorer-more-models').dispatch('click');
await tick();
const retried = latest('/api/v1/brands/{slug}/models');
check(pending.length === beforeRetry + 1 && retried.fields.slug === 'alfa' && retried.fields.page === '1', 'model retry is single and does not loop');
retried.resolve(catalog([{ id: '20', name: 'Three' }], 1));
await tick();
check(values(byId('explorer-model')).join() === '20' && byId('explorer-more-models').hidden === true, 'model retry populates once');

await boot();
latest('/api/v1/brands').resolve(failed(503));
await tick();
check(byId('explorer-more').hidden === false && byId('explorer-more').textContent === 'თავიდან ცდა' && byId('explorer-status').textContent === 'სერვერის შეცდომა.' && byId('explorer-brand-help').textContent === 'თავიდან ცდა ხელმისაწვდომია.', 'initial brand failure shows retry');
const brandCount = pending.filter((req) => req.fields.operation === '/api/v1/brands').length;
await tick();
check(pending.filter((req) => req.fields.operation === '/api/v1/brands').length === brandCount, 'brand retry is not automatic');
byId('explorer-more').dispatch('click');
byId('explorer-more').dispatch('click');
await tick();
check(pending.filter((req) => req.fields.operation === '/api/v1/brands').length === brandCount + 1, 'brand retry does not double submit');
latest('/api/v1/brands').resolve(catalog([{ slug: 'alfa', name: 'Alfa' }, { slug: 'alfa', name: 'Alfa again' }], 1));
await tick();
check(values(byId('explorer-brand')).join() === 'alfa' && byId('explorer-more').hidden === true, 'successful brand retry has no duplicate options');

await brands([{ slug: 'alfa', name: 'Alfa' }], 2);
check(byId('explorer-more').textContent === 'მეტი ბრენდის ჩატვირთვა' && byId('explorer-more').hidden === false, 'loaded brands keep the next-page button');
byId('explorer-more').dispatch('click');
await tick();
latest('/api/v1/brands').resolve(failed(500));
await tick();
check(values(byId('explorer-brand')).join() === 'alfa' && byId('explorer-more').textContent === 'მეტი ბრენდის ჩატვირთვა', 'failed later brand page keeps loaded brands');
byId('explorer-more').dispatch('click');
await tick();
check(latest('/api/v1/brands').fields.page === '2', 'brand page retry continues from the failed page');

await brands();
await modelsFor('alfa', [{ id: '20', name: 'Three' }, { id: '21', name: 'Five' }]);
choose(byId('explorer-model'), '20');
await tick();
const generationsA = latest('/api/v1/models/{id}/generations');
check(generationsA.fields.id === '20' && generationsA.fields.page === '1' && byId('explorer-generation-help').textContent === '' && byId('explorer-generation-status').textContent === 'თაობები იტვირთება.' && byId('explorer-modification-help').textContent === 'ჯერ აირჩიეთ თაობა.', 'model A starts generation loading');
choose(byId('explorer-model'), '21');
await tick();
const generationsB = latest('/api/v1/models/{id}/generations');
check(generationsA.aborted && generationsB.fields.id === '21' && generationsB.fields.page === '1', 'model change cancels generations and starts the new model');
generationsA.resolve(catalog([{ id: '7', display_label: 'Old gen' }], 1));
await tick();
check(!texts(byId('explorer-generation')).includes('Old gen'), 'stale generations are ignored');
generationsB.resolve(catalog([{ id: '8', display_label: 'New gen' }], 1));
await tick();
check(values(byId('explorer-generation')).join() === '8', 'current model generations populate');

await brands();
await modelsFor('alfa', [{ id: '20', name: 'Three' }]);
choose(byId('explorer-model'), '20');
await tick();
latest('/api/v1/models/{id}/generations').resolve(catalog([{ id: '8', display_label: 'Gen' }, { id: '9', display_label: 'Gen 2' }], 60));
await tick();
byId('explorer-more-generations').dispatch('click');
await tick();
const generationPage = latest('/api/v1/models/{id}/generations');
check(generationPage.fields.page === '2', 'generation pagination requests the next page');
choose(byId('explorer-model'), '20');
generationPage.resolve(catalog([{ id: '77', display_label: 'Late gen' }], 60));
await tick();
check(!values(byId('explorer-generation')).includes('77'), 'cancelled generation page is ignored');

await brands();
await modelsFor('alfa', [{ id: '20', name: 'Three' }]);
choose(byId('explorer-model'), '20');
await tick();
latest('/api/v1/models/{id}/generations').resolve(catalog([{ id: '8', display_label: 'Gen' }, { id: '9', display_label: 'Gen 2' }]));
await tick();
choose(byId('explorer-generation'), '8');
await tick();
const modificationsA = latest('/api/v1/generations/{id}/modifications');
check(modificationsA.fields.id === '8' && modificationsA.fields.page === '1' && byId('explorer-modification-help').textContent === '' && byId('explorer-modification-status').textContent === 'მოდიფიკაციები იტვირთება.', 'generation A starts modification loading');
choose(byId('explorer-generation'), '9');
await tick();
const modificationsB = latest('/api/v1/generations/{id}/modifications');
check(modificationsA.aborted && modificationsB.fields.id === '9', 'generation change cancels modifications and starts the new generation');
modificationsA.resolve(catalog([{ id: '40', name: 'Old mod' }], 1));
await tick();
check(!texts(byId('explorer-modification')).some((text) => text.includes('Old mod')), 'stale modifications are ignored');
modificationsB.resolve(catalog([{ id: '41', name: '2.5' }], 1));
await tick();
check(values(byId('explorer-modification')).join() === '41', 'current generation modifications populate');

await brands();
await modelsFor('alfa', [{ id: '20', name: 'Three' }]);
choose(byId('explorer-model'), '20');
await tick();
latest('/api/v1/models/{id}/generations').resolve(catalog([{ id: '8', display_label: 'Gen' }]));
await tick();
choose(byId('explorer-generation'), '8');
await tick();
latest('/api/v1/generations/{id}/modifications').resolve(catalog([{ id: '40', name: '2.5' }, { id: '41', name: '3.0' }]));
await tick();
choose(byId('explorer-modification'), '40');
await tick();
const detailA = latest('/api/v1/modifications/{id}');
check(detailA.fields.id === '40', 'modification A starts detail loading');
choose(byId('explorer-modification'), '41');
await tick();
const detailB = latest('/api/v1/modifications/{id}');
check(detailA.aborted && byId('explorer-detail').hidden === true && byId('explorer-detail-list').children.length === 0 && detailB.fields.id === '41', 'modification change clears old details and starts the new detail');
detailA.resolve(response(200, { body: { data: { id: '40', name: 'OLD' } } }));
await tick();
check(!byId('explorer-detail-list').children.some((child) => child.textContent === 'OLD'), 'stale detail cannot reappear');
detailB.resolve(response(200, { body: { data: { id: '41', name: '3.0', engine_volume_cc: 3000, transmission: 'manual', drive_type: 'awd', body_type: 'sedan', year_start: 2018, year_end: 2020, brand: { name: 'Alfa' }, model: { name: 'Giulia' }, generation: { id: '8', name: 'Gen' } } } }));
await tick();
const detailText = byId('explorer-detail-list').children.map((child) => child.textContent).join('|');
check(byId('explorer-detail').hidden === false && byId('explorer-modification-status').textContent === 'მოდიფიკაცია არჩეულია.' && detailText.includes('სახელი') && detailText.includes('3.0') && detailText.includes('8') && !detailText.includes('OLD'), 'current detail populates from the approved fields');

await brands();
await modelsFor('alfa', [{ id: '20', name: 'Three' }, { id: '21', name: 'Five' }]);
choose(byId('explorer-model'), '20');
await tick();
latest('/api/v1/models/{id}/generations').resolve(catalog([{ id: '8', display_label: 'Gen' }]));
await tick();
choose(byId('explorer-generation'), '8');
await tick();
const openModifications = latest('/api/v1/generations/{id}/modifications');
choose(byId('explorer-model'), '21');
await tick();
check(openModifications.aborted && latest('/api/v1/models/{id}/generations').fields.id === '21', 'model change cancels modifications and loads the new generations');

await brands();
await modelsFor('alfa', [{ id: '20', name: 'Three' }]);
choose(byId('explorer-model'), '20');
await tick();
latest('/api/v1/models/{id}/generations').resolve(catalog([{ id: '8', display_label: 'Gen' }]));
await tick();
choose(byId('explorer-generation'), '8');
await tick();
latest('/api/v1/generations/{id}/modifications').resolve(catalog([{ id: '40', name: '2.5' }]));
await tick();
choose(byId('explorer-modification'), '40');
await tick();
const cascadeDetail = latest('/api/v1/modifications/{id}');
choose(byId('explorer-model'), '20');
await tick();
latest('/api/v1/models/{id}/generations').resolve(catalog([{ id: '8', display_label: 'Gen' }]));
await tick();
choose(byId('explorer-generation'), '8');
await tick();
const cascadeModifications = latest('/api/v1/generations/{id}/modifications');
choose(byId('explorer-brand'), 'bmw');
await tick();
check(cascadeDetail.aborted && cascadeModifications.aborted && latest('/api/v1/brands/{slug}/models').fields.slug === 'bmw' && byId('explorer-detail-list').children.length === 0, 'brand change cancels modifications and detail and loads the new models');

await brands();
await modelsFor('bmw', [{ id: '30', name: 'Five' }]);
const openModels = latest('/api/v1/brands/{slug}/models');
choose(byId('explorer-brand'), 'alfa');
await tick();
check(openModels.settled && latest('/api/v1/brands/{slug}/models').fields.slug === 'alfa' && byId('explorer-detail').hidden === true, 'brand change starts the new models request with a clear detail panel');

if (failures.length) {
  console.error(failures.join('\n'));
  process.exit(1);
}
console.log('all passed');
