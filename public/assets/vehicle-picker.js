(function () {
  var root = document.getElementById('vehicle-picker');
  if (!root) return;
  var status = document.getElementById('vp-status');
  var card = document.getElementById('vp-card');
  var photo = document.getElementById('vp-img');
  var empty = document.getElementById('vp-empty');
  var frame = document.getElementById('vp-frame');
  var shield = document.getElementById('vp-shield');
  var flag = document.getElementById('vp-flag');
  var flagNote = document.getElementById('vp-flag-note');
  var specs = document.getElementById('vp-specs');
  var order = ['brand', 'model', 'year', 'generation', 'modification'];
  var copyNode = document.getElementById('vp-copy');
  var copy = {};
  if (copyNode) {
    try { copy = JSON.parse(copyNode.textContent); } catch (e) { copy = {}; }
  }
  var placeholders = {
    brand: copy.chooseBrand || 'აირჩიეთ მარკა',
    model: copy.chooseModel || 'აირჩიეთ მოდელი',
    year: copy.chooseYear || 'აირჩიეთ წელი',
    generation: copy.chooseGeneration || 'აირჩიეთ თაობა',
    modification: copy.chooseModification || 'აირჩიეთ მოდიფიკაცია'
  };
  var fields = {};
  var rows = {};
  var picked = {};
  var openStep = null;
  var active = -1;
  var shown = [];
  var token = 0;

  order.forEach(function (step) {
    var node = root.querySelector('[data-step="' + step + '"]');
    fields[step] = {
      node: node,
      trigger: node.querySelector('[data-vp-trigger]'),
      label: node.querySelector('[data-vp-label]'),
      pop: node.querySelector('[data-vp-pop]'),
      query: node.querySelector('[data-vp-q]'),
      hint: node.querySelector('[data-vp-hint]'),
      list: node.querySelector('[data-vp-list]')
    };
    rows[step] = [];
  });

  function setStatus(text) {
    status.textContent = text || '';
  }

  function setLabel(step, text, chosen) {
    var field = fields[step];
    field.label.textContent = text;
    field.label.classList.toggle('is-empty', !chosen);
  }

  function resetFrom(step) {
    var start = order.indexOf(step) + 1;
    for (var i = start; i < order.length; i++) {
      var name = order[i];
      picked[name] = null;
      rows[name] = [];
      setLabel(name, placeholders[name], false);
      fields[name].trigger.disabled = true;
      close(name);
    }
    hideCard();
  }

  function resetFlag() {
    if (!flag) return;
    flag.hidden = true;
    flag.disabled = false;
    flag.setAttribute('aria-label', 'ფოტო არასწორია');
    delete flag.dataset.model;
    if (flagNote) flagNote.hidden = true;
  }

  function setShield(on) {
    if (shield) shield.hidden = !on;
  }

  function hideCard() {
    card.hidden = true;
    photo.hidden = true;
    photo.removeAttribute('src');
    empty.hidden = false;
    setShield(false);
    resetFlag();
    var response = document.getElementById('vp-response');
    if (response) response.hidden = true;
  }

  function close(step) {
    var field = fields[step];
    if (!field) return;
    field.pop.hidden = true;
    field.node.classList.remove('open');
    field.trigger.setAttribute('aria-expanded', 'false');
    if (openStep === step) openStep = null;
  }

  function closeAll() {
    order.forEach(close);
  }

  function pages(path) {
    var all = [];
    var page = 1;
    function next() {
      var join = path.indexOf('?') === -1 ? '?' : '&';
      return fetch(path + join + 'page=' + page + '&per_page=100', { headers: { Accept: 'application/json' } })
        .then(function (response) {
          if (!response.ok) throw new Error('fail');
          return response.json();
        })
        .then(function (payload) {
          all = all.concat(payload.data || []);
          var total = payload.meta ? payload.meta.total : all.length;
          page += 1;
          if (all.length < total && page <= 40) return next();
          return all;
        });
    }
    return next();
  }

  function paint(step) {
    var field = fields[step];
    var query = field.query.value.trim().toLowerCase();
    var source = rows[step];
    var need = step === 'model' ? 1 : 3;
    var filtering = query.length >= need;
    shown = filtering
      ? source.filter(function (row) {
          return String(row.label || row.name || '').toLowerCase().indexOf(query) !== -1;
        })
      : source.slice();
    var capped = shown.slice(0, 80);
    field.hint.hidden = false;
    if (query.length > 0 && query.length < need) {
      field.hint.textContent = 'ძებნა იწყება 3 ასოდან';
    } else if (filtering && !capped.length) {
      field.hint.textContent = 'ვერ მოიძებნა';
    } else if (!filtering && source.length > 80) {
      field.hint.textContent = 'ნაჩვენებია 80 / ' + source.length + (need === 1 ? '' : '. ძებნა იწყება 3 ასოდან');
    } else {
      field.hint.hidden = true;
      field.hint.textContent = '';
    }
    field.list.innerHTML = '';
    active = capped.length ? 0 : -1;
    capped.forEach(function (row, index) {
      var li = document.createElement('li');
      var button = document.createElement('button');
      button.type = 'button';
      button.className = index === 0 ? 'on' : '';
      button.textContent = row.label || row.name || String(row.id);
      button.addEventListener('mousedown', function (event) {
        event.preventDefault();
        choose(step, row);
      });
      li.appendChild(button);
      field.list.appendChild(li);
    });
    shown = capped;
  }

  function move(step) {
    var buttons = fields[step].list.querySelectorAll('button');
    if (!buttons.length) return;
    active = Math.max(0, Math.min(buttons.length - 1, active));
    buttons.forEach(function (button, index) {
      button.classList.toggle('on', index === active);
    });
    buttons[active].scrollIntoView({ block: 'nearest' });
  }

  function open(step) {
    if (fields[step].trigger.disabled) return;
    closeAll();
    openStep = step;
    var field = fields[step];
    field.pop.hidden = false;
    field.node.classList.add('open');
    field.trigger.setAttribute('aria-expanded', 'true');
    field.query.value = '';
    paint(step);
    field.query.focus();
  }

  function showJson(payload) {
    var response = document.getElementById('vp-response');
    var json = document.getElementById('vp-json');
    if (!response || !json || !payload) return;
    json.textContent = JSON.stringify(payload, null, 2);
    response.hidden = false;
  }

  function choose(step, row) {
    picked[step] = row;
    setLabel(step, row.label || row.name || String(row.id), true);
    close(step);
    resetFrom(step);
    showJson({ data: row });
    var current = ++token;
    if (step === 'brand') return load('model', current);
    if (step === 'model') return load('year', current);
    if (step === 'year') return load('generation', current);
    if (step === 'generation') return load('modification', current);
    loadDetail(current);
  }

  function enable(step, list, emptyText) {
    rows[step] = list;
    fields[step].trigger.disabled = list.length === 0;
    setLabel(step, list.length ? placeholders[step] : emptyText, false);
    setStatus(list.length ? '' : emptyText);
  }

  function load(step, current) {
    setStatus('იტვირთება');
    var path = '';
    if (step === 'model') path = '/api/picker/brands/' + encodeURIComponent(picked.brand.id) + '/models';
    if (step === 'year') path = '/api/picker/models/' + encodeURIComponent(picked.model.id) + '/years';
    if (step === 'generation') path = '/api/picker/models/' + encodeURIComponent(picked.model.id) + '/generations?year=' + encodeURIComponent(picked.year.id);
    if (step === 'modification') path = '/api/picker/generations/' + encodeURIComponent(picked.generation.id) + '/modifications?year=' + encodeURIComponent(picked.year.id);
    pages(path).then(function (list) {
      if (current !== token) return;
      var empty = {
        model: 'ამ მარკას მოდელი არ აქვს',
        year: 'ამ მოდელს წელი არ აქვს',
        generation: 'ამ წელს თაობა არ აქვს',
        modification: 'ამ თაობას მოდიფიკაცია არ აქვს'
      };
      enable(step, list, empty[step]);
    }).catch(function () {
      if (current === token) setStatus('კატალოგი დროებით მიუწვდომელია');
    });
  }

  function line(label, value) {
    if (value === null || value === undefined || value === '') return;
    var dt = document.createElement('dt');
    dt.className = '';
    dt.textContent = label;
    var dd = document.createElement('dd');
    dd.className = 'min-w-0';
    dd.textContent = String(value);
    specs.appendChild(dt);
    specs.appendChild(dd);
  }

  function liters(cc) {
    return cc ? (Number(cc) / 1000).toFixed(1) + ' ლ' : '';
  }

  function years(start, end) {
    if (start == null && end == null) return '';
    if (start == null) return String(end);
    if (end == null) return start + '–';
    return start + '–' + end;
  }

  function pair(front, rear, unit) {
    var parts = [];
    if (front != null && front !== '') parts.push('წინ ' + front + (unit || ''));
    if (rear != null && rear !== '') parts.push('უკან ' + rear + (unit || ''));
    return parts.join(' · ');
  }

  function showEquipment(groups) {
    ['standard', 'optional', 'not_available'].forEach(function (key) {
      var host = root.querySelector('[data-eq="' + key + '"]');
      host.innerHTML = '';
      var items = (groups && groups[key]) || [];
      if (!items.length) {
        var none = document.createElement('p');
        none.className = 'vp-muted';
        none.textContent = 'არ არის';
        host.appendChild(none);
        return;
      }
      var grouped = {};
      var names = [];
      items.forEach(function (item) {
        var section = item.section || 'სხვა';
        if (!grouped[section]) {
          grouped[section] = [];
          names.push(section);
        }
        grouped[section].push(item);
      });
      names.forEach(function (section) {
        var group = grouped[section];
        if (group.length === 1 && group[0].name === section) {
          var single = document.createElement('p');
          single.className = 'py-0.5';
          single.textContent = group[0].value ? group[0].name + ' — ' + group[0].value : group[0].name;
          host.appendChild(single);
          return;
        }
        var heading = document.createElement('p');
        heading.className = 'vp-strong';
        heading.textContent = section;
        host.appendChild(heading);
        group.forEach(function (item) {
          var row = document.createElement('p');
          row.className = 'vp-muted';
          row.textContent = item.value ? item.name + ' — ' + item.value : item.name;
          host.appendChild(row);
        });
      });
    });
  }

  function showCard(data) {
    var brandName = data.brand ? data.brand.name : '';
    var modelName = data.model ? data.model.name : '';
    var generationName = data.generation ? [data.generation.name, data.generation.code].filter(Boolean).join(' · ') : '';
    document.getElementById('vp-title').textContent = [brandName, modelName, generationName].filter(Boolean).join(' · ') || 'ავტომობილი';
    specs.innerHTML = '';
    var engine = [liters(data.engine && data.engine.volume_cc), data.engine && data.engine.type].filter(Boolean).join(' · ');
    var power = data.power_hp != null ? data.power_hp + ' ცხ.ძ.' : '';
    if (data.power_kw != null) power += (power ? ' (' + data.power_kw + ' კვტ)' : data.power_kw + ' კვტ');
    var use = data.consumption || {};
    var consumption = [];
    if (use.city_l != null) consumption.push('ქალაქი ' + use.city_l);
    if (use.highway_l != null) consumption.push('ტრასა ' + use.highway_l);
    if (use.combined_l != null) consumption.push('შერეული ' + use.combined_l);
    line('მარკა', brandName);
    line('მოდელი', modelName);
    line('თაობა', generationName);
    line('წლები', years(data.year_start, data.year_end));
    line('ძარა', data.body_type);
    line('ძრავი', engine);
    line('სიმძლავრე', power);
    line('ბრუნვა', data.torque_nm == null ? '' : data.torque_nm + ' ნმ');
    line('საწვავი', data.fuel);
    line('ავზი', data.tank_l == null ? '' : data.tank_l + ' ლ');
    line('კოლოფი', data.transmission);
    line('წამყვანი', data.drive);
    line('ხარჯი', consumption.length ? consumption.join(' · ') + ' ლ/100 კმ' : '');
    line('სიჩქარე', data.max_speed_kmh == null ? '' : data.max_speed_kmh + ' კმ/სთ');
    var size = data.dimensions_mm || {};
    var dimensions = [size.length, size.width, size.height].filter(function (part) { return part != null; });
    line('ზომები', dimensions.length ? dimensions.join(' × ') + ' მმ' : '');
    line('კლირენსი', data.clearance_mm == null ? '' : data.clearance_mm + ' მმ');
    line('წონა', data.weight_kg == null ? '' : data.weight_kg + ' კგ');
    line('ადგილები', data.seats);
    line('საბარგული', data.trunk_l == null ? '' : data.trunk_l + ' ლ');
    line('მუხრუჭები', pair(data.front_brakes, data.rear_brakes));
    line('საკიდარი', pair(data.front_suspension, data.rear_suspension));
    line('საბურავები', pair(data.front_tires, data.rear_tires));
    showEquipment(data.equipment);
    if (data.has_photo && data.model && data.model.id) {
      empty.hidden = true;
      photo.hidden = false;
      photo.alt = brandName + ' ' + modelName;
      photo.onerror = function () {
        photo.hidden = true;
        empty.hidden = false;
        setShield(false);
        resetFlag();
      };
      setShield(true);
      photo.src = '/api/models/' + encodeURIComponent(data.model.id) + '/photo?v=20261020j';
      if (flag) {
        flag.hidden = false;
        flag.disabled = false;
        flag.setAttribute('aria-label', 'ფოტო არასწორია');
        flag.dataset.model = String(data.model.id);
        if (flagNote) flagNote.hidden = true;
      }
    } else {
      photo.hidden = true;
      photo.removeAttribute('src');
      empty.hidden = false;
      setShield(false);
      resetFlag();
    }
    card.hidden = false;
  }

  function loadDetail(current) {
    setStatus('იტვირთება');
    fetch('/api/picker/modifications/' + encodeURIComponent(picked.modification.id), { headers: { Accept: 'application/json' } })
      .then(function (response) {
        if (!response.ok) throw new Error('fail');
        return response.json();
      })
      .then(function (payload) {
        if (current !== token) return;
        showCard(payload.data || {});
        var response = document.getElementById('vp-response');
        var json = document.getElementById('vp-json');
        if (response && json) {
          json.textContent = JSON.stringify(payload, null, 2);
          response.hidden = false;
        }
        setStatus('');
      })
      .catch(function () {
        if (current === token) setStatus('კატალოგი დროებით მიუწვდომელია');
      });
  }

  order.forEach(function (step) {
    var field = fields[step];
    field.trigger.addEventListener('click', function () {
      if (openStep === step) close(step);
      else open(step);
    });
    field.query.addEventListener('input', function () { paint(step); });
    field.query.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        active += 1;
        move(step);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active -= 1;
        move(step);
      } else if (event.key === 'Enter') {
        event.preventDefault();
        if (shown[active]) choose(step, shown[active]);
      } else if (event.key === 'Escape') {
        close(step);
        field.trigger.focus();
      }
    });
  });

  if (frame) {
    frame.addEventListener('contextmenu', function (event) { event.preventDefault(); });
    frame.addEventListener('dragstart', function (event) { event.preventDefault(); });
  }
  photo.draggable = false;
  photo.addEventListener('dragstart', function (event) { event.preventDefault(); });

  document.addEventListener('click', function (event) {
    if (!root.contains(event.target)) closeAll();
  });

  if (flag) {
    flag.addEventListener('click', function () {
      var modelId = flag.dataset.model;
      if (!modelId || flag.disabled) return;
      flag.disabled = true;
      fetch('/api/models/' + encodeURIComponent(modelId) + '/photo/report', {
        method: 'POST',
        headers: { 'Accept': 'application/json' }
      }).then(function (response) {
        if (!response.ok) throw new Error('report');
        if (flagNote) flagNote.hidden = false;
        flag.setAttribute('aria-label', 'შეტყობინება მიღებულია');
      }).catch(function () {
        flag.disabled = false;
        setStatus('შეტყობინება ვერ გაიგზავნა');
      });
    });
  }

  setStatus('იტვირთება მარკები');
  pages('/api/picker/brands').then(function (list) {
    enable('brand', list, 'მარკა ვერ მოიძებნა');
  }).catch(function () {
    setStatus('კატალოგი დროებით მიუწვდომელია');
  });
})();
