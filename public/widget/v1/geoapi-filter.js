(function () {
  var script = document.currentScript;
  var apiBase = script && script.src ? new URL(script.src).origin : '';
  var LEVELS = ['brand', 'model', 'year', 'body', 'generation'];
  var english = (document.documentElement.lang || '').toLowerCase().indexOf('en') === 0;
  var LABELS = english
    ? { brand: 'Brand', model: 'Model', year: 'Year', body: 'Body', generation: 'Generation' }
    : { brand: 'ბრენდი', model: 'მოდელი', year: 'გამოშვების წელი', body: 'ძარის ტიპი', generation: 'თაობა' };
  var PLACEHOLDER = english
    ? { brand: 'Choose a brand', model: 'Choose a model', year: 'Choose a year', body: 'Choose a body', generation: 'Choose a generation' }
    : { brand: 'აირჩიეთ ბრენდი', model: 'აირჩიეთ მოდელი', year: 'აირჩიეთ წელი', body: 'აირჩიეთ ძარის ტიპი', generation: 'აირჩიეთ თაობა' };
  var WAIT = english
    ? { model: 'Choose a brand first', year: 'Choose a model first', body: 'Choose a model first', generation: 'Choose a model first' }
    : { model: 'ჯერ აირჩიეთ ბრენდი', year: 'ჯერ აირჩიეთ მოდელი', body: 'ჯერ აირჩიეთ მოდელი', generation: 'ჯერ აირჩიეთ მოდელი' };
  var BODY_LABEL = {
    sedan: 'სედანი',
    hatchback: 'ჰეჩბეკი',
    liftback: 'ლიფტბეკი',
    wagon: 'უნივერსალი',
    coupe: 'კუპე',
    minivan: 'მინივენი',
    van: 'ფურგონი',
    pickup: 'პიკაპი'
  };
  var MIN_QUERY = 2;
  var LAYOUT = {
    'ქ': 'q', 'წ': 'w', 'ე': 'e', 'რ': 'r', 'ტ': 't', 'ყ': 'y', 'უ': 'u', 'ი': 'i', 'ო': 'o', 'პ': 'p',
    'ა': 'a', 'ს': 's', 'დ': 'd', 'ფ': 'f', 'გ': 'g', 'ჰ': 'h', 'ჯ': 'j', 'კ': 'k', 'ლ': 'l',
    'ზ': 'z', 'ხ': 'x', 'ც': 'c', 'ვ': 'v', 'ბ': 'b', 'ნ': 'n', 'მ': 'm',
    'ჭ': 'w', 'ღ': 'r', 'თ': 't', 'შ': 's', 'ჟ': 'j', 'ძ': 'z', 'ჩ': 'c'
  };

  function normalize(value) {
    var lower = String(value || '').toLowerCase();
    var out = '';
    for (var i = 0; i < lower.length; i++) {
      out += LAYOUT[lower[i]] || lower[i];
    }
    return out;
  }

  function field(level) {
    return '<div class="field">' +
      '<label for="geoapi-' + level + '-input">' + LABELS[level] + '</label>' +
      '<div class="box">' +
      '<input id="geoapi-' + level + '-input" data-input="' + level + '" type="text" role="combobox" autocomplete="off" spellcheck="false"' +
      ' aria-autocomplete="list" aria-expanded="false" aria-controls="geoapi-' + level + '-list">' +
      '<button type="button" class="toggle" data-toggle="' + level + '" tabindex="-1" aria-hidden="true">' +
      '<svg width="12" height="12" viewBox="0 0 12 12"><path d="M2 4.5 6 8l4-3.5" fill="none" stroke="currentColor" stroke-width="1.6"/></svg></button>' +
      '<ul id="geoapi-' + level + '-list" data-list="' + level + '" role="listbox" aria-label="' + LABELS[level] + '" hidden></ul>' +
      '</div>' +
      '<select data-level="' + level + '" tabindex="-1" aria-hidden="true"></select>' +
      '</div>';
  }

  class GeoapiFilter extends HTMLElement {
    constructor() {
      super();
      this.attachShadow({ mode: 'open' });
      this.state = { brand: null, model: null, year: null, body: null, generation: null };
      this.names = { brand: null, model: null, year: null, body: null, generation: null };
      this.seq = 0;
      this.shadowRoot.innerHTML = '<style>' +
        ':host{display:block;font:var(--geoapi-font,var(--carapi-font,16px/1.4 sans-serif);color:var(--geoapi-ink,var(--carapi-ink,#141719)}' +
        '.field{position:relative;margin:0 0 12px}' +
        'label{display:block;font-weight:600}' +
        '.box{position:relative;margin-top:6px}' +
        'input{display:block;box-sizing:border-box;width:100%;margin:0;padding:10px 40px 10px 10px;min-height:var(--geoapi-control,var(--carapi-control,auto);border:1px solid var(--geoapi-line,var(--carapi-line,#e1e4e5);border-radius:var(--geoapi-radius,var(--carapi-radius,8px);font:inherit;color:inherit;background:#fff}' +
        'input:focus{outline:2px solid var(--geoapi-accent,var(--carapi-accent,#193f68);outline-offset:1px}' +
        'input:disabled{background:#f4f5f6;color:var(--geoapi-muted,var(--carapi-muted,#5c6163);cursor:not-allowed}' +
        'input::placeholder{color:var(--geoapi-muted,var(--carapi-muted,#5c6163);opacity:1}' +
        '.toggle{position:absolute;top:50%;right:4px;display:grid;place-items:center;width:32px;height:32px;margin-top:-16px;padding:0;border:0;border-radius:6px;background:none;color:var(--geoapi-muted,var(--carapi-muted,#5c6163);cursor:pointer}' +
        'input:disabled+.toggle{cursor:not-allowed;opacity:.5}' +
        'input[aria-expanded="true"]+.toggle svg{transform:rotate(180deg)}' +
        'ul{position:absolute;z-index:10;top:100%;left:0;right:0;box-sizing:border-box;max-height:264px;margin:4px 0 0;padding:4px;overflow:auto;list-style:none;background:#fff;border:1px solid var(--geoapi-line,var(--carapi-line,#e1e4e5);border-radius:var(--geoapi-radius,var(--carapi-radius,8px);box-shadow:0 14px 32px rgba(0,0,0,.14)}' +
        'li{padding:8px 10px;border-radius:6px;cursor:pointer;overflow-wrap:anywhere}' +
        'li.active{background:#eef0f3;background:color-mix(in srgb,var(--geoapi-accent,var(--carapi-accent,#193f68) 12%,#fff)}' +
        'li[aria-selected="true"]{font-weight:600}' +
        'li.note{color:var(--geoapi-muted,var(--carapi-muted,#5c6163);font-size:.9em;cursor:default}' +
        'mark{background:none;color:var(--geoapi-accent,var(--carapi-accent,#193f68);font-weight:700}' +
        'select{position:absolute;left:0;bottom:0;width:1px;height:1px;margin:0;padding:0;border:0;opacity:0;pointer-events:none}' +
        'p{margin:8px 0;color:var(--geoapi-muted,var(--carapi-muted,#5c6163)}' +
        '.err{color:#9b2c2c}' +
        '</style>' +
        LEVELS.map(field).join('') +
        '<p data-status></p>';
      this.brand = this.shadowRoot.querySelector('[data-level="brand"]');
      this.model = this.shadowRoot.querySelector('[data-level="model"]');
      this.year = this.shadowRoot.querySelector('[data-level="year"]');
      this.body = this.shadowRoot.querySelector('[data-level="body"]');
      this.generation = this.shadowRoot.querySelector('[data-level="generation"]');
      this.status = this.shadowRoot.querySelector('[data-status]');
      this.brand.addEventListener('change', () => { this.onBrand(); this.syncAll(); });
      this.model.addEventListener('change', () => { this.onModel(); this.syncAll(); });
      this.year.addEventListener('change', () => { this.onYear(); this.syncAll(); });
      this.body.addEventListener('change', () => { this.onBody(); this.syncAll(); });
      this.generation.addEventListener('change', () => { this.onGeneration(); this.syncAll(); });
      this.combos = LEVELS.map((level) => this.combo(level));
    }

    connectedCallback() {
      this.resetSelect(this.brand, PLACEHOLDER.brand);
      this.resetSelect(this.model, WAIT.model);
      this.resetSelect(this.year, WAIT.year);
      this.resetSelect(this.body, WAIT.body);
      this.resetSelect(this.generation, WAIT.generation);
      this.model.disabled = true;
      this.year.disabled = true;
      this.body.disabled = true;
      this.generation.disabled = true;
      this.syncAll();
      this.load('brands', {}, this.brand, PLACEHOLDER.brand);
    }

    key() {
      return this.getAttribute('public-key') || '';
    }

    endpoint(path) {
      if (this.key() === 'demo') {
        return '/widget/v1/demo/' + path;
      }
      return '/widget/v1/' + encodeURIComponent(this.key()) + '/' + path;
    }

    async load(path, body, select, placeholder) {
      var applied = await this.fetchRows(path, body, select, placeholder);
      this.syncAll();
      return applied;
    }

    async fetchRows(path, body, select, placeholder) {
      var seq = ++this.seq;
      this.status.textContent = 'იტვირთება…';
      this.status.className = '';
      var rows = [];
      var page = 1;
      var total = 0;
      try {
        do {
          var payload = Object.assign({ page: page, per_page: 100 }, body);
          var response = await fetch(apiBase + this.endpoint(path), {
            method: 'POST',
            credentials: 'omit',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          if (seq !== this.seq) {
            return false;
          }
          if (response.status === 429 || response.status === 503) {
            var retry = response.headers.get('Retry-After') || '';
            this.status.textContent = response.status === 429
              ? 'მოთხოვნების ლიმიტი მიღწეულია. ლიმიტი ამოიწურა. სცადეთ ' + (retry || 'მოგვიანებით') + ' წამში.'
              : 'სერვისი დროებით მიუწვდომელია. სცადეთ მოგვიანებით.';
            this.status.className = 'err';
            return false;
          }
          if (!response.ok) {
            this.status.textContent = 'მონაცემები ვერ ჩაიტვირთა. სცადეთ ხელახლა.';
            this.status.className = 'err';
            return false;
          }
          var json = await response.json();
          rows = rows.concat(json.data || []);
          total = json.meta ? json.meta.total : rows.length;
          page += 1;
        } while (rows.length < total && page < 1000);
      } catch (e) {
        this.status.textContent = 'ქსელის შეცდომა. სერვერთან დაკავშირება ვერ მოხერხდა. შეამოწმეთ ინტერნეტკავშირი და სცადეთ ხელახლა.';
        this.status.className = 'err';
        return false;
      }
      if (seq !== this.seq) {
        return false;
      }
      this.fill(select, rows, placeholder);
      this.status.textContent = rows.length ? '' : 'მითითებული პირობებით მონაცემები ვერ მოიძებნა.';
      return true;
    }

    resetSelect(select, placeholder) {
      select.replaceChildren();
      var empty = document.createElement('option');
      empty.value = '';
      empty.textContent = placeholder;
      select.appendChild(empty);
    }

    onBrand() {
      var option = this.brand.selectedOptions[0];
      this.state.brand = this.brand.value || null;
      this.names.brand = this.state.brand ? option.dataset.name : null;
      this.state.model = null;
      this.state.year = null;
      this.state.body = null;
      this.state.generation = null;
      this.names.model = null;
      this.names.year = null;
      this.names.body = null;
      this.names.generation = null;
      this.resetSelect(this.model, PLACEHOLDER.model);
      this.resetSelect(this.year, WAIT.year);
      this.resetSelect(this.body, WAIT.body);
      this.resetSelect(this.generation, WAIT.generation);
      this.year.disabled = true;
      this.body.disabled = true;
      this.generation.disabled = true;
      this.emit();
      if (!this.state.brand) {
        this.model.disabled = true;
        return;
      }
      this.load('brands/' + encodeURIComponent(this.slugOf(option)) + '/models', {}, this.model, PLACEHOLDER.model);
    }

    slugOf(option) {
      return option && option.dataset.slug ? option.dataset.slug : '';
    }

    fill(select, rows, placeholder) {
      select.replaceChildren();
      var empty = document.createElement('option');
      empty.value = '';
      empty.textContent = placeholder;
      select.appendChild(empty);
      rows.forEach(function (row) {
        var option = document.createElement('option');
        option.value = String(row.id);
        option.dataset.slug = row.slug || '';
        option.dataset.name = row.display_label || row.name || '';
        option.dataset.bodies = JSON.stringify(row.bodies || []);
        option.textContent = option.dataset.name;
        select.appendChild(option);
      });
      select.disabled = false;
    }

    onModel() {
      var option = this.model.selectedOptions[0];
      this.state.model = this.model.value || null;
      this.names.model = this.state.model ? option.dataset.name : null;
      this.state.year = null;
      this.state.body = null;
      this.state.generation = null;
      this.names.year = null;
      this.names.body = null;
      this.names.generation = null;
      this.resetSelect(this.year, PLACEHOLDER.year);
      this.resetSelect(this.body, WAIT.body);
      this.resetSelect(this.generation, WAIT.generation);
      this.body.disabled = true;
      this.generation.disabled = true;
      this.emit();
      if (!this.state.model) {
        this.year.disabled = true;
        return;
      }
      this.load('models/' + encodeURIComponent(this.state.model) + '/years', {}, this.year, PLACEHOLDER.year).then((applied) => {
        if (!applied) {
          return;
        }
        if (this.year.options.length <= 1) {
          this.status.textContent = 'ამ მოდელისთვის გამოშვების წლები ჯერ არ არის.';
        }
      });
    }

    onYear() {
      var option = this.year.selectedOptions[0];
      this.state.year = this.year.value || null;
      this.names.year = this.state.year ? option.dataset.name : null;
      this.state.body = null;
      this.state.generation = null;
      this.names.body = null;
      this.names.generation = null;
      this.fillBodies(option);
      this.resetSelect(this.generation, this.state.model ? PLACEHOLDER.generation : WAIT.generation);
      this.generation.disabled = true;
      this.emit();
      if (this.state.model) {
        this.load('models/' + encodeURIComponent(this.state.model) + '/generations', {}, this.generation, PLACEHOLDER.generation);
      }
    }

    onGeneration() {
      var option = this.generation.selectedOptions[0];
      this.state.generation = this.generation.value || null;
      this.names.generation = this.state.generation ? option.dataset.name : null;
      this.emit();
    }

    fillBodies(option) {
      var bodies = [];
      if (option && option.value) {
        try {
          bodies = JSON.parse(option.dataset.bodies || '[]');
        } catch (e) {
          bodies = [];
        }
      }
      if (!Array.isArray(bodies)) {
        bodies = [];
      }
      this.resetSelect(this.body, this.state.year && !bodies.length ? 'ძარის ტიპი მითითებული არ არის' : (this.state.year ? PLACEHOLDER.body : 'ჯერ აირჩიეთ გამოშვების წელი'));
      bodies.forEach((code) => {
        var item = document.createElement('option');
        item.value = String(code);
        item.dataset.name = BODY_LABEL[code] || String(code);
        item.textContent = item.dataset.name;
        this.body.appendChild(item);
      });
      this.body.disabled = !this.state.year || bodies.length === 0;
      if (this.state.year && bodies.length === 0) {
        this.status.textContent = 'ამ გამოშვების წლისთვის ძარის ტიპი მითითებული არ არის.';
      } else if (bodies.length) {
        this.status.textContent = '';
      }
    }

    onBody() {
      var option = this.body.selectedOptions[0];
      this.state.body = this.body.value || null;
      this.names.body = this.state.body ? option.dataset.name : null;
      this.emit();
    }

    emit() {
      var detail = {
        brand_id: this.state.brand,
        model_id: this.state.model,
        year: this.state.year,
        body_type: this.state.body,
        generation_id: this.state.generation,
        modification_id: null,
        brand_name: this.names.brand,
        model_name: this.names.model,
        year_name: this.names.year,
        body_name: this.names.body,
        generation_name: this.names.generation
      };
      var self = this;
      ['geoapi:change', 'carapi:change'].forEach(function (name) {
        self.dispatchEvent(new CustomEvent(name, { bubbles: true, composed: true, detail: detail }));
      });
    }

    combo(level) {
      var root = this.shadowRoot;
      var combo = {
        level: level,
        select: root.querySelector('select[data-level="' + level + '"]'),
        input: root.querySelector('[data-input="' + level + '"]'),
        list: root.querySelector('[data-list="' + level + '"]'),
        toggle: root.querySelector('[data-toggle="' + level + '"]'),
        items: [],
        active: -1
      };
      combo.input.addEventListener('focus', () => {
        combo.input.select();
        this.open(combo, '');
      });
      combo.input.addEventListener('click', () => {
        if (combo.list.hidden) {
          this.open(combo, '');
        }
      });
      combo.input.addEventListener('input', () => this.open(combo, combo.input.value));
      combo.input.addEventListener('keydown', (event) => this.onKey(combo, event));
      combo.input.addEventListener('blur', () => {
        this.close(combo);
        this.restore(combo);
      });
      combo.toggle.addEventListener('mousedown', (event) => event.preventDefault());
      combo.toggle.addEventListener('click', () => {
        if (combo.input.disabled) {
          return;
        }
        if (!combo.list.hidden) {
          this.close(combo);
          return;
        }
        if (root.activeElement === combo.input) {
          this.open(combo, '');
        } else {
          combo.input.focus();
        }
      });
      combo.list.addEventListener('mousedown', (event) => event.preventDefault());
      combo.list.addEventListener('click', (event) => {
        var li = event.target.closest('li[role="option"]');
        if (li) {
          this.choose(combo, combo.items[Number(li.dataset.index)]);
        }
      });
      return combo;
    }

    optionsOf(combo) {
      var rows = [];
      Array.prototype.forEach.call(combo.select.options, function (option) {
        if (option.value === '') {
          return;
        }
        var name = option.dataset.name || option.textContent;
        rows.push({ value: option.value, name: name, key: normalize(name), slug: normalize((option.dataset.slug || '').replace(/-/g, ' ')) });
      });
      return rows;
    }

    rank(rows, query) {
      var ranked = [];
      rows.forEach(function (row) {
        var index = row.key.indexOf(query);
        var score;
        if (index === 0) {
          score = 0;
        } else if (index > 0 && /[\s\-(/]/.test(row.key[index - 1])) {
          score = 1;
        } else if (index > 0) {
          score = 2;
        } else if (row.slug.indexOf(query) >= 0) {
          score = 3;
        } else {
          return;
        }
        ranked.push({ row: row, score: score, index: index });
      });
      ranked.sort(function (a, b) {
        return a.score - b.score || a.row.name.localeCompare(b.row.name);
      });
      return ranked;
    }

    open(combo, text) {
      if (combo.input.disabled) {
        return;
      }
      var query = normalize(text.trim());
      var rows = this.optionsOf(combo);
      var ranked = query.length >= MIN_QUERY
        ? this.rank(rows, query)
        : rows.map(function (row) { return { row: row, score: 0, index: -1 }; });
      combo.items = ranked.map(function (entry) { return entry.row; });
      combo.list.replaceChildren();
      if (query.length > 0 && query.length < MIN_QUERY) {
        combo.list.appendChild(this.note('ძებნისთვის ჩაწერეთ მინიმუმ ' + MIN_QUERY + ' სიმბოლო.'));
      }
      if (!rows.length) {
        combo.list.appendChild(this.note('სია ჯერ არ ჩატვირთულა.'));
      } else if (!ranked.length) {
        combo.list.appendChild(this.note('მითითებული პირობებით მონაცემები ვერ მოიძებნა.'));
      }
      ranked.forEach((entry, index) => {
        var li = document.createElement('li');
        li.id = 'geoapi-' + combo.level + '-opt-' + index;
        li.setAttribute('role', 'option');
        li.dataset.index = String(index);
        li.setAttribute('aria-selected', entry.row.value === combo.select.value ? 'true' : 'false');
        var length = query.length;
        if (entry.index >= 0 && length && entry.row.key.length === entry.row.name.length) {
          var mark = document.createElement('mark');
          mark.textContent = entry.row.name.slice(entry.index, entry.index + length);
          li.append(entry.row.name.slice(0, entry.index), mark, entry.row.name.slice(entry.index + length));
        } else {
          li.textContent = entry.row.name;
        }
        combo.list.appendChild(li);
      });
      combo.list.hidden = false;
      combo.input.setAttribute('aria-expanded', 'true');
      var selected = combo.items.findIndex(function (row) { return row.value === combo.select.value; });
      this.activate(combo, query.length >= MIN_QUERY ? (combo.items.length ? 0 : -1) : selected);
    }

    note(text) {
      var li = document.createElement('li');
      li.className = 'note';
      li.setAttribute('role', 'presentation');
      li.textContent = text;
      return li;
    }

    activate(combo, index) {
      var previous = combo.list.querySelector('li.active');
      if (previous) {
        previous.classList.remove('active');
      }
      combo.active = index;
      if (index < 0) {
        combo.input.removeAttribute('aria-activedescendant');
        return;
      }
      var li = combo.list.querySelector('#geoapi-' + combo.level + '-opt-' + index);
      if (li) {
        li.classList.add('active');
        combo.input.setAttribute('aria-activedescendant', li.id);
        li.scrollIntoView({ block: 'nearest' });
      }
    }

    close(combo) {
      combo.list.hidden = true;
      combo.input.setAttribute('aria-expanded', 'false');
      combo.input.removeAttribute('aria-activedescendant');
      combo.active = -1;
    }

    onKey(combo, event) {
      var open = !combo.list.hidden;
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (!open) {
          this.open(combo, '');
          return;
        }
        if (!combo.items.length) {
          return;
        }
        var step = event.key === 'ArrowDown' ? 1 : -1;
        var next = combo.active < 0 ? (step > 0 ? 0 : combo.items.length - 1) : combo.active + step;
        this.activate(combo, Math.max(0, Math.min(combo.items.length - 1, next)));
      } else if (event.key === 'Enter') {
        if (open && combo.active >= 0) {
          event.preventDefault();
          this.choose(combo, combo.items[combo.active]);
        }
      } else if (event.key === 'Escape') {
        if (open) {
          event.preventDefault();
          this.close(combo);
          this.restore(combo);
          combo.input.select();
        }
      } else if (event.key === 'Tab') {
        this.close(combo);
      }
    }

    choose(combo, row) {
      if (!row) {
        return;
      }
      this.close(combo);
      combo.input.value = row.name;
      if (combo.select.value !== row.value) {
        combo.select.value = row.value;
        combo.select.dispatchEvent(new Event('change'));
      }
    }

    restore(combo) {
      if (combo.input.value.trim() === '' && combo.select.value !== '') {
        combo.select.value = '';
        combo.select.dispatchEvent(new Event('change'));
        return;
      }
      this.syncCombo(combo);
    }

    syncCombo(combo) {
      var select = combo.select;
      var option = select.selectedOptions[0];
      var typing = this.shadowRoot.activeElement === combo.input && !combo.list.hidden;
      combo.input.disabled = select.disabled;
      combo.input.placeholder = select.disabled
        ? (select.options[0] ? select.options[0].textContent : '')
        : (PLACEHOLDER[combo.level] || 'აირჩიეთ');
      if (select.disabled) {
        this.close(combo);
      }
      if (!typing) {
        combo.input.value = option && option.value !== '' ? (option.dataset.name || option.textContent) : '';
      } else {
        this.open(combo, combo.input.value);
      }
    }

    syncAll() {
      this.combos.forEach((combo) => this.syncCombo(combo));
    }
  }

  if (!customElements.get('geoapi-filter')) {
    customElements.define('geoapi-filter', GeoapiFilter);
  }
  if (!customElements.get('carapi-filter')) {
    customElements.define('carapi-filter', GeoapiFilter);
  }
})();
