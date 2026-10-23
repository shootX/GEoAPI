(function () {
  var header = document.querySelector('header.nav');
  var menu = document.querySelector('.burger');
  if (header && menu) {
    menu.addEventListener('click', function () {
      var open = header.classList.toggle('open');
      menu.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && header.classList.contains('open')) {
        header.classList.remove('open');
        menu.setAttribute('aria-expanded', 'false');
      }
    });
  }

  document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      var root = document.documentElement;
      var next = root.dataset.theme === 'light' ? '' : 'light';
      if (next) root.dataset.theme = next;
      else delete root.dataset.theme;
      try {
        if (next) localStorage.setItem('geoapi-theme', next);
        else localStorage.removeItem('geoapi-theme');
      } catch (e) {}
    });
  });

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    var area = document.createElement('textarea');
    area.value = text;
    document.body.appendChild(area);
    area.select();
    document.execCommand('copy');
    area.remove();
    return Promise.resolve();
  }

  function flashCopied(button) {
    var live = document.getElementById('copy-status');
    if (!live) {
      live = document.createElement('div');
      live.id = 'copy-status';
      live.setAttribute('role', 'status');
      live.style.position = 'absolute';
      live.style.width = '1px';
      live.style.height = '1px';
      live.style.overflow = 'hidden';
      document.body.appendChild(live);
    }
    live.textContent = 'ტექსტი დაკოპირებულია.';
    if (!button) return;
    var textNode = null;
    Array.prototype.forEach.call(button.childNodes, function (node) {
      if (node.nodeType === 3 && node.textContent.trim()) textNode = node;
    });
    if (!textNode) {
      button.setAttribute('title', 'ტექსტი დაკოპირებულია.');
      return;
    }
    var idle = textNode.textContent;
    textNode.textContent = ' ტექსტი დაკოპირებულია.';
    window.setTimeout(function () { textNode.textContent = idle; }, 1600);
  }

  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var id = button.getAttribute('data-copy');
      var node = document.getElementById(id);
      var text = node ? (node.value || node.textContent || '') : '';
      copyText(text).then(function () { flashCopied(button); });
    });
  });
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-copy-text]');
    if (!button) return;
    copyText(button.getAttribute('data-copy-text') || '').then(function () { flashCopied(button); });
  });

  document.querySelectorAll('.codeb').forEach(function (block) {
    var tabs = Array.prototype.slice.call(block.querySelectorAll('[data-tab]'));
    var panels = Array.prototype.slice.call(block.querySelectorAll('[data-tab-panel]'));
    function show(name) {
      tabs.forEach(function (tab) {
        tab.classList.toggle('on', tab.getAttribute('data-tab') === name);
      });
      panels.forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-tab-panel') !== name;
      });
    }
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () { show(tab.getAttribute('data-tab')); });
    });
    var copy = block.querySelector('[data-copy-block]');
    if (copy) {
      copy.addEventListener('click', function () {
        var panel = block.querySelector('[data-tab-panel]:not([hidden])') || panels[0] || block.querySelector('pre');
        if (panel) copyText(panel.textContent || '').then(function () { flashCopied(copy); });
      });
    }
  });

  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    if (input.closest('.pass-wrap')) return;
    var wrap = document.createElement('div');
    wrap.className = 'pass-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'pass-toggle';
    toggle.setAttribute('aria-label', 'პაროლის ჩვენება');
    toggle.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>';
    toggle.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      toggle.setAttribute('aria-label', show ? 'პაროლის დამალვა' : 'პაროლის ჩვენება');
    });
    wrap.appendChild(toggle);
  });

  var pass = document.querySelector('input[name="password"][autocomplete="new-password"]');
  if (pass) {
    var meter = document.createElement('div');
    meter.className = 'meter';
    meter.innerHTML = '<i></i><i></i><i></i><i></i>';
    var hint = document.createElement('p');
    hint.className = 'hint';
    pass.insertAdjacentElement('afterend', hint);
    pass.insertAdjacentElement('afterend', meter);
    pass.addEventListener('input', function () {
      var value = pass.value;
      var score = 0;
      if (value.length >= 15) score++;
      if (/[a-zა-ჰ]/.test(value) && /[A-Zა-ჰ]/.test(value)) score++;
      if (/\d/.test(value)) score++;
      if (/[^A-Za-z0-9ა-ჰ]/.test(value)) score++;
      Array.prototype.forEach.call(meter.children, function (bar, index) {
        bar.classList.toggle('on', index < score);
      });
      hint.textContent = value.length >= 15 ? 'სიგრძე საკმარისია.' : 'პაროლი უნდა შეიცავდეს მინიმუმ 15 სიმბოლოს.';
    });
  }

  var search = document.querySelector('[data-doc-search]');
  if (search) {
    search.addEventListener('input', function () {
      var query = search.value.trim().toLowerCase();
      document.querySelectorAll('.side a').forEach(function (link) {
        link.hidden = query !== '' && link.textContent.toLowerCase().indexOf(query) === -1;
      });
    });
  }

  var tocLinks = Array.prototype.slice.call(document.querySelectorAll('.toc a'));
  if (tocLinks.length) {
    var sections = tocLinks.map(function (link) {
      return document.querySelector(link.getAttribute('href'));
    }).filter(Boolean);
    function mark() {
      var current = sections[0];
      sections.forEach(function (section) {
        if (section.getBoundingClientRect().top < 120) current = section;
      });
      tocLinks.forEach(function (link) {
        link.classList.toggle('on', current && link.getAttribute('href') === '#' + current.id);
      });
    }
    document.addEventListener('scroll', mark, { passive: true });
    mark();
  }

  var demo = document.getElementById('demo');
  if (!demo) return;
  var steps = {
    brand: demo.querySelector('[data-step="brand"]'),
    model: demo.querySelector('[data-step="model"]'),
    year: demo.querySelector('[data-step="year"]'),
    body: demo.querySelector('[data-step="body"]')
  };
  var json = document.getElementById('demo-json');
  var chain = document.getElementById('demo-chain');
  var picker = document.getElementById('demo-picker');
  var query = document.getElementById('demo-q');
  var list = document.getElementById('demo-list');
  var marquee = document.getElementById('brand-marquee');
  var state = { brand: null, model: null, year: null, body: null, generation: null };
  var cache = { brands: null, models: {}, years: {}, generations: {} };
  var openStep = null;
  var rows = [];
  var active = -1;
  var BODY = { sedan: 'სედანი', hatchback: 'ჰეჩბეკი', liftback: 'ლიფტბეკი', wagon: 'უნივერსალი', coupe: 'კუპე', minivan: 'მინივენი', van: 'ფურგონი', pickup: 'პიკაპი' };

  function post(path) {
    var rowsAll = [];
    var page = 1;
    function next() {
      return fetch('/widget/v1/demo/' + path, {
        method: 'POST',
        credentials: 'omit',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ page: page, per_page: 100 })
      }).then(function (response) {
        if (!response.ok) throw new Error('fail');
        return response.json();
      }).then(function (payload) {
        rowsAll = rowsAll.concat(payload.data || []);
        var total = payload.meta ? payload.meta.total : rowsAll.length;
        page += 1;
        if (rowsAll.length < total && page < 30) return next();
        return rowsAll;
      });
    }
    return next();
  }

  function esc(value) {
    return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function paint(value) {
    var text = JSON.stringify(value, null, 2);
    return esc(text)
      .replace(/(&quot;(?:\\.|[^&]|&(?!quot;))*?&quot;)(?=\s*:)/g, '<span class="k">$1</span>')
      .replace(/(:\s*)(&quot;(?:\\.|[^&]|&(?!quot;))*?&quot;)/g, '$1<span class="s">$2</span>')
      .replace(/(:\s*)(-?\d+)/g, '$1<span class="n">$2</span>')
      .replace(/[{}\[\],]/g, '<span class="p">$&</span>');
  }

  function label(step, text, on) {
    var node = steps[step].querySelector('b');
    node.textContent = text;
    node.classList.toggle('ph', !on);
    steps[step].classList.toggle('on', !!on);
  }

  function render() {
    var payload = {
      brand: state.brand ? { id: state.brand.id, name: state.brand.name } : null,
      model: state.model ? { id: state.model.id, name: state.model.name } : null,
      year: state.year ? state.year.id : null,
      body_type: state.body ? state.body.id : null,
      generation: state.generation
    };
    json.innerHTML = paint(payload);
    var bits = [];
    if (state.brand) bits.push('<code>brand ' + esc(state.brand.id) + '</code>');
    if (state.model) bits.push('<code>model ' + esc(state.model.id) + '</code>');
    if (state.generation) bits.push('<code class="on">generation ' + esc(state.generation.id) + '</code>');
    chain.innerHTML = bits.length ? bits.join('<i>›</i>') : '<code>brand</code><i>›</i><code>model</code><i>›</i><code>generation</code>';
  }

  function setList(items, empty) {
    rows = items;
    active = items.length ? 0 : -1;
    list.innerHTML = '';
    if (!items.length) {
      list.innerHTML = '<li class="note">' + esc(empty) + '</li>';
      return;
    }
    items.forEach(function (item, index) {
      var li = document.createElement('li');
      li.textContent = item.name;
      if (index === 0) li.classList.add('on');
      li.addEventListener('mousedown', function (event) {
        event.preventDefault();
        choose(item);
      });
      list.appendChild(li);
    });
  }

  function move(step) {
    var nodes = list.querySelectorAll('li:not(.note)');
    if (!nodes.length) return;
    active = Math.max(0, Math.min(nodes.length - 1, active + step));
    nodes.forEach(function (node, index) { node.classList.toggle('on', index === active); });
    nodes[active].scrollIntoView({ block: 'nearest' });
  }

  function choose(item) {
    if (openStep === 'brand') {
      state.brand = item;
      state.model = state.year = state.body = state.generation = null;
      label('brand', item.name, true);
      label('model', 'აირჩიეთ მოდელი', false);
      label('year', 'აირჩიეთ წელი', false);
      label('body', 'აირჩიეთ ძარის ტიპი', false);
    } else if (openStep === 'model') {
      state.model = item;
      state.year = state.body = state.generation = null;
      label('model', item.name, true);
      label('year', 'აირჩიეთ წელი', false);
      label('body', 'აირჩიეთ ძარის ტიპი', false);
    } else if (openStep === 'year') {
      state.year = item;
      state.body = state.generation = null;
      label('year', item.name, true);
      label('body', 'აირჩიეთ ძარის ტიპი', false);
    } else if (openStep === 'body') {
      state.body = item;
      label('body', item.name, true);
      matchGeneration();
    }
    closePicker();
    render();
  }

  function matchGeneration() {
    if (!state.model || !state.year) {
      state.generation = null;
      return;
    }
    var year = Number(state.year.id);
    var body = state.body ? state.body.id : null;
    var found = (cache.generations[state.model.id] || []).find(function (row) {
      var start = Number(row.year_start);
      var end = row.year_end == null ? year : Number(row.year_end);
      var type = (row.body_type || '').toLowerCase();
      return start <= year && year <= end && (!body || !type || type === body);
    });
    state.generation = found ? {
      id: found.id,
      name: found.chassis_code || found.name || found.display_label,
      years: (found.year_start || '') + '–' + (found.year_end || '')
    } : null;
  }

  function closePicker() {
    openStep = null;
    picker.hidden = true;
  }

  function showBlocked(message) {
    openStep = null;
    picker.hidden = false;
    query.value = '';
    setList([], message);
  }

  function open(step) {
    if (step === 'model' && !state.brand) {
      showBlocked('ჯერ აირჩიეთ ბრენდი');
      return;
    }
    if ((step === 'year' || step === 'body') && !state.model) {
      showBlocked('ჯერ აირჩიეთ მოდელი');
      return;
    }
    if (step === 'body' && !state.year) {
      showBlocked('ჯერ აირჩიეთ გამოშვების წელი');
      return;
    }
    openStep = step;
    picker.hidden = false;
    query.value = '';
    query.focus();
    loadStep(step, '');
  }

  function loadStep(step, text) {
    var needle = text.trim().toLowerCase();
    function show(items, empty) {
      var filtered = needle === '' ? items : items.filter(function (item) {
        return item.name.toLowerCase().indexOf(needle) !== -1;
      });
      setList(filtered.slice(0, 80), filtered.length ? '' : (empty || 'მითითებული პირობებით მონაცემები ვერ მოიძებნა.'));
    }
    if (step === 'brand') {
      var ready = cache.brands ? Promise.resolve(cache.brands) : post('brands').then(function (data) {
        cache.brands = data.map(function (row) { return { id: String(row.id), name: row.name, slug: row.slug }; });
        if (marquee && cache.brands.length) fillMarquee(cache.brands);
        return cache.brands;
      });
      ready.then(function (items) { show(items, 'მითითებული პირობებით ბრენდი ვერ მოიძებნა.'); }).catch(function () { setList([], 'კატალოგი დროებით მიუწვდომელია.'); });
    } else if (step === 'model') {
      var key = state.brand.slug;
      var readyM = cache.models[key] ? Promise.resolve(cache.models[key]) : post('brands/' + encodeURIComponent(key) + '/models').then(function (data) {
        cache.models[key] = data.map(function (row) { return { id: String(row.id), name: row.name }; });
        return cache.models[key];
      });
      readyM.then(function (items) { show(items, 'მითითებული პირობებით მოდელი ვერ მოიძებნა.'); }).catch(function () { setList([], 'კატალოგი დროებით მიუწვდომელია.'); });
    } else if (step === 'year') {
      var id = state.model.id;
      var readyY = cache.years[id] ? Promise.resolve(cache.years[id]) : post('models/' + encodeURIComponent(id) + '/years').then(function (data) {
        cache.years[id] = data.map(function (row) { return { id: String(row.id), name: row.name, bodies: row.bodies || [] }; });
        return cache.years[id];
      });
      if (!cache.generations[id]) {
        post('models/' + encodeURIComponent(id) + '/generations').then(function (data) {
          cache.generations[id] = data;
          matchGeneration();
          render();
        }).catch(function () { cache.generations[id] = []; });
      }
      readyY.then(function (items) { show(items, 'ამ მოდელისთვის გამოშვების წლები ჯერ არ არის.'); }).catch(function () { setList([], 'კატალოგი დროებით მიუწვდომელია.'); });
    } else {
      var bodies = (state.year.bodies || []).map(function (code) {
        return { id: String(code), name: BODY[code] || String(code) };
      });
      show(bodies, 'ამ გამოშვების წლისთვის ძარის ტიპი მითითებული არ არის.');
    }
  }

  function fillMarquee(items) {
    var names = items.slice(0, 16).map(function (item) { return item.name; });
    if (names.length < 2) return;
    var html = names.concat(names).map(function (name, index) {
      return '<span' + (index % names.length === 0 ? ' class="on"' : '') + '>' + esc(name) + '</span>';
    }).join('');
    marquee.innerHTML = html;
  }

  Object.keys(steps).forEach(function (step) {
    steps[step].addEventListener('click', function () { open(step); });
  });
  query.addEventListener('input', function () { if (openStep) loadStep(openStep, query.value); });
  query.addEventListener('keydown', function (event) {
    if (event.key === 'ArrowDown') { event.preventDefault(); move(1); }
    else if (event.key === 'ArrowUp') { event.preventDefault(); move(-1); }
    else if (event.key === 'Enter') {
      event.preventDefault();
      if (rows[active]) choose(rows[active]);
    } else if (event.key === 'Escape') closePicker();
  });
  document.addEventListener('click', function (event) {
    if (!picker.hidden && !picker.contains(event.target) && !event.target.closest('.cmd .st')) closePicker();
  });
  var copyInstall = document.getElementById('copy-install');
  if (copyInstall) {
    copyInstall.addEventListener('click', function () {
      var node = document.getElementById('install-snippet');
      if (node) copyText(node.textContent || '').then(function () { flashCopied(copyInstall); });
    });
  }
  render();
  post('brands').then(function (data) {
    cache.brands = data.map(function (row) { return { id: String(row.id), name: row.name, slug: row.slug }; });
    if (marquee) fillMarquee(cache.brands);
  }).catch(function () {});
})();
