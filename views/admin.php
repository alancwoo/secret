<?php $layout = true; ?>
<!-- admin-v3-<?php echo filemtime(__FILE__); ?> -->

<img src="/images/header.png" alt="Secret" class="header-img header-img-light">
<img src="/images/header-dark.png" alt="Secret" class="header-img header-img-dark">

<div id="admin-auth">
  <p class="form-hint mb-lg">Enter your password to manage secrets and links.</p>
  <form id="auth-form" onsubmit="return handleAuth(event)">
    <div class="form-group">
      <label for="admin-password">Password</label>
      <input id="admin-password" type="password" placeholder="******************" required autofocus>
    </div>
    <button class="btn" type="submit">Access Admin</button>
  </form>
  <div id="auth-error" class="hidden mt-lg msg-error"></div>
</div>

<div id="admin-panel" class="hidden">

  <div class="admin-toolbar">
    <button class="btn btn-sm" onclick="loadData()">Refresh</button>
  </div>

  <div class="admin-section">
    <div class="admin-section-head">
      <span class="admin-section-title">Secrets</span>
      <span class="admin-count" id="count-secrets"></span>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Type</th><th>Created</th><th>Expires</th><th>Views</th><th></th></tr></thead>
        <tbody id="tbody-secrets"></tbody>
      </table>
      <div id="empty-secrets" class="admin-empty hidden">No active secrets.</div>
    </div>
  </div>

  <div class="admin-section">
    <div class="admin-section-head">
      <span class="admin-section-title">Shortened Links</span>
      <span class="admin-count" id="count-links"></span>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>ID</th><th>Destination</th><th>Created</th><th>Expires</th><th>Uses</th><th></th></tr></thead>
        <tbody id="tbody-links"></tbody>
      </table>
      <div id="empty-links" class="admin-empty hidden">No active links.</div>
    </div>
  </div>

  <div class="admin-section">
    <div class="admin-section-head">
      <span class="admin-section-title">Requests</span>
      <span class="admin-count" id="count-requests"></span>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Label</th><th>Status</th><th>Created</th><th>Expires</th><th></th></tr></thead>
        <tbody id="tbody-requests"></tbody>
      </table>
      <div id="empty-requests" class="admin-empty hidden">No active requests.</div>
    </div>
  </div>

</div>

<script>
(function () {

  var adminPassword = '';

  var VIEW_OPTS = [
    { v: '1', l: '1 view' }, { v: '3', l: '3 views' }, { v: '5', l: '5 views' },
    { v: '10', l: '10 views' }, { v: '0', l: 'Unlimited' }
  ];

  // ── Utilities ──────────────────────────────────────────────────────────────

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function relTime(isoStr) {
    var ms = new Date(isoStr) - Date.now(), abs = Math.abs(ms) / 1000, past = ms < 0;
    var val, unit;
    if      (abs < 60)    { val = Math.round(abs);         unit = 'second'; }
    else if (abs < 3600)  { val = Math.round(abs / 60);    unit = 'minute'; }
    else if (abs < 86400) { val = Math.round(abs / 3600);  unit = 'hour'; }
    else                  { val = Math.round(abs / 86400); unit = 'day'; }
    var str = val + ' ' + unit + (val !== 1 ? 's' : '');
    return past ? str + ' ago' : 'in ' + str;
  }

  function toLocalDT(isoStr) {
    var d = new Date(isoStr);
    var pad = function (n) { return ('0' + n).slice(-2); };
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
           'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function fromLocalDT(val) {
    return new Date(val).toISOString().replace(/\.\d{3}Z$/, 'Z');
  }

  function viewsText(views, maxViews) {
    return views + (parseInt(maxViews, 10) > 0 ? '/' + maxViews : '');
  }

  function btn(text, cls) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = cls; b.textContent = text;
    return b;
  }

  function sel(opts, cur) {
    var s = document.createElement('select');
    opts.forEach(function (o) {
      var op = document.createElement('option');
      op.value = o.v; op.textContent = o.l;
      if (String(o.v) === String(cur)) op.selected = true;
      s.appendChild(op);
    });
    return s;
  }

  function formGroup(labelText, input) {
    var g = document.createElement('div');
    g.className = 'edit-form-group';
    var l = document.createElement('label');
    l.textContent = labelText;
    g.appendChild(l);
    g.appendChild(input);
    return g;
  }

  // ── Row builders ───────────────────────────────────────────────────────────

  function renderSecret(s) {
    var typeText = s.is_file ? 'File' + (s.filename ? ': ' + esc(s.filename) : '') : 'Text';

    // Data row
    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td>' + typeText + '</td>' +
      '<td>' + relTime(s.created_at) + '</td>' +
      '<td>' + relTime(s.expires) + '</td>' +
      '<td>' + viewsText(s.views, s.max_views) + '</td>';

    var tdExp   = tr.cells[2];
    var tdViews = tr.cells[3];
    var tdAct   = document.createElement('td');
    tr.appendChild(tdAct);

    // Edit row
    var editTr = document.createElement('tr');
    editTr.className = 'edit-row hidden';
    var editTd = document.createElement('td');
    editTd.colSpan = 5;
    editTr.appendChild(editTd);

    var expiresIn   = document.createElement('input');
    expiresIn.type  = 'datetime-local';
    expiresIn.value = toLocalDT(s.expires);

    var maxViewsSel = sel(VIEW_OPTS, s.max_views);
    var saveBtn     = btn('Save', 'btn btn-sm');
    var cancelBtn   = btn('Cancel', 'edit-link');
    var errSpan     = document.createElement('span');
    errSpan.className = 'msg-error';

    var form = document.createElement('div');
    form.className = 'edit-form';
    form.appendChild(formGroup('Expires', expiresIn));
    form.appendChild(formGroup('Max Views', maxViewsSel));
    var acts = document.createElement('div');
    acts.className = 'edit-form-actions';
    acts.appendChild(saveBtn); acts.appendChild(cancelBtn); acts.appendChild(errSpan);
    form.appendChild(acts);
    editTd.appendChild(form);

    saveBtn.addEventListener('click', function () {
      saveBtn.disabled = true; saveBtn.textContent = '…'; errSpan.textContent = '';
      fetch('/api/secret/' + encodeURIComponent(s.id), {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ expires: fromLocalDT(expiresIn.value), maxViews: parseInt(maxViewsSel.value, 10), password: adminPassword })
      })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.updated) {
          s.expires = fromLocalDT(expiresIn.value); s.max_views = parseInt(maxViewsSel.value, 10);
          tdExp.textContent = relTime(s.expires); tdViews.textContent = viewsText(s.views, s.max_views);
          editTr.classList.add('hidden');
        } else { errSpan.textContent = j.error || 'Save failed'; }
        saveBtn.disabled = false; saveBtn.textContent = 'Save';
      })
      .catch(function () { errSpan.textContent = 'Network error'; saveBtn.disabled = false; saveBtn.textContent = 'Save'; });
    });

    cancelBtn.addEventListener('click', function () { editTr.classList.add('hidden'); });

    var editBtn = btn('Edit', 'edit-link');
    editBtn.addEventListener('click', function () {
      expiresIn.value = toLocalDT(s.expires); maxViewsSel.value = String(s.max_views);
      editTr.classList.toggle('hidden');
    });

    var delBtn = btn('Delete', 'delete-link');
    delBtn.addEventListener('click', function () {
      delBtn.disabled = true; delBtn.textContent = '…';
      fetch('/api/secret/' + encodeURIComponent(s.id), { method: 'DELETE' })
        .then(function () { tr.remove(); editTr.remove(); })
        .catch(function () { delBtn.disabled = false; delBtn.textContent = 'Delete'; });
    });

    tdAct.appendChild(editBtn);
    tdAct.appendChild(document.createTextNode(' · '));
    tdAct.appendChild(delBtn);

    return [tr, editTr];
  }

  function renderLink(l) {
    var shortUrl = location.origin + '/l/' + l.id;

    // Data row
    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td class="col-mono"><a href="' + esc(shortUrl) + '" target="_blank" rel="noopener">' + esc(l.id) + '</a></td>' +
      '<td class="col-url" title="' + esc(l.url) + '">' + esc(l.url) + '</td>' +
      '<td>' + relTime(l.created_at) + '</td>' +
      '<td>' + relTime(l.expires) + '</td>' +
      '<td>' + viewsText(l.views, l.max_views) + '</td>';

    var tdUrl   = tr.cells[1];
    var tdExp   = tr.cells[3];
    var tdUses  = tr.cells[4];
    var tdAct   = document.createElement('td');
    tr.appendChild(tdAct);

    // Edit row
    var editTr = document.createElement('tr');
    editTr.className = 'edit-row hidden';
    var editTd = document.createElement('td');
    editTd.colSpan = 6;
    editTr.appendChild(editTd);

    var urlIn       = document.createElement('input');
    urlIn.type      = 'url'; urlIn.value = l.url; urlIn.placeholder = 'https://';

    var expiresIn   = document.createElement('input');
    expiresIn.type  = 'datetime-local'; expiresIn.value = toLocalDT(l.expires);

    var maxViewsSel = sel(VIEW_OPTS, l.max_views);
    var saveBtn     = btn('Save', 'btn btn-sm');
    var cancelBtn   = btn('Cancel', 'edit-link');
    var errSpan     = document.createElement('span');
    errSpan.className = 'msg-error';

    var urlGroup = formGroup('URL', urlIn);
    urlGroup.classList.add('edit-form-full');

    var form = document.createElement('div');
    form.className = 'edit-form';
    form.appendChild(urlGroup);
    form.appendChild(formGroup('Expires', expiresIn));
    form.appendChild(formGroup('Max Redirects', maxViewsSel));
    var acts = document.createElement('div');
    acts.className = 'edit-form-actions';
    acts.appendChild(saveBtn); acts.appendChild(cancelBtn); acts.appendChild(errSpan);
    form.appendChild(acts);
    editTd.appendChild(form);

    saveBtn.addEventListener('click', function () {
      var newUrl = urlIn.value.trim();
      if (!newUrl) return;
      saveBtn.disabled = true; saveBtn.textContent = '…'; errSpan.textContent = '';
      fetch('/api/link/' + encodeURIComponent(l.id), {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url: newUrl, expires: fromLocalDT(expiresIn.value), maxViews: parseInt(maxViewsSel.value, 10), password: adminPassword })
      })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.updated) {
          l.url = newUrl; l.expires = fromLocalDT(expiresIn.value); l.max_views = parseInt(maxViewsSel.value, 10);
          tdUrl.textContent = l.url; tdUrl.title = l.url;
          tdExp.textContent = relTime(l.expires); tdUses.textContent = viewsText(l.views, l.max_views);
          editTr.classList.add('hidden');
        } else { errSpan.textContent = j.error || 'Save failed'; }
        saveBtn.disabled = false; saveBtn.textContent = 'Save';
      })
      .catch(function () { errSpan.textContent = 'Network error'; saveBtn.disabled = false; saveBtn.textContent = 'Save'; });
    });

    cancelBtn.addEventListener('click', function () { editTr.classList.add('hidden'); });

    var editBtn = btn('Edit', 'edit-link');
    editBtn.addEventListener('click', function () {
      urlIn.value = l.url; expiresIn.value = toLocalDT(l.expires); maxViewsSel.value = String(l.max_views);
      editTr.classList.toggle('hidden');
    });

    var delBtn = btn('Delete', 'delete-link');
    delBtn.addEventListener('click', function () {
      delBtn.disabled = true; delBtn.textContent = '…';
      fetch('/api/link/' + encodeURIComponent(l.id), { method: 'DELETE' })
        .then(function () { tr.remove(); editTr.remove(); })
        .catch(function () { delBtn.disabled = false; delBtn.textContent = 'Delete'; });
    });

    tdAct.appendChild(editBtn);
    tdAct.appendChild(document.createTextNode(' · '));
    tdAct.appendChild(delBtn);

    return [tr, editTr];
  }

  function renderRequest(r) {
    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td>' + (r.label ? esc(r.label) : '<em>No label</em>') + '</td>' +
      '<td>' + (r.used ? 'Used' : 'Pending') + '</td>' +
      '<td>' + relTime(r.created_at) + '</td>' +
      '<td>' + relTime(r.expires) + '</td>';
    var tdAct = document.createElement('td');
    tr.appendChild(tdAct);

    var delBtn = btn('Delete', 'delete-link');
    delBtn.addEventListener('click', function () {
      delBtn.disabled = true; delBtn.textContent = '…';
      fetch('/api/request/' + encodeURIComponent(r.id), { method: 'DELETE' })
        .then(function () { tr.remove(); })
        .catch(function () { delBtn.disabled = false; delBtn.textContent = 'Delete'; });
    });
    tdAct.appendChild(delBtn);
    return tr;
  }

  // ── Section rendering ──────────────────────────────────────────────────────

  function setSection(type, items, renderFn) {
    var tbody = document.getElementById('tbody-' + type);
    var empty = document.getElementById('empty-' + type);
    var count = document.getElementById('count-' + type);
    tbody.innerHTML = '';
    if (!items.length) { empty.classList.remove('hidden'); count.textContent = ''; return; }
    empty.classList.add('hidden');
    count.textContent = items.length + ' item' + (items.length !== 1 ? 's' : '');
    items.forEach(function (item) {
      var result = renderFn(item);
      if (Array.isArray(result)) {
        result.forEach(function (row) { tbody.appendChild(row); });
      } else {
        tbody.appendChild(result);
      }
    });
  }

  // ── Data loading ───────────────────────────────────────────────────────────

  async function loadData() {
    var res  = await fetch('/api/admin', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ password: adminPassword })
    });
    var json = await res.json();
    if (res.status === 200) {
      setSection('secrets',  json.secrets  || [], renderSecret);
      setSection('links',    json.links    || [], renderLink);
      setSection('requests', json.requests || [], renderRequest);
    }
  }

  window.loadData = loadData;

  // ── Auth ───────────────────────────────────────────────────────────────────

  window.handleAuth = function (e) {
    e.preventDefault();
    var errEl = document.getElementById('auth-error');
    errEl.classList.add('hidden');
    var pw = document.getElementById('admin-password').value;

    fetch('/api/admin', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ password: pw })
    })
    .then(function (res) { return res.json().then(function (j) { return { status: res.status, body: j }; }); })
    .then(function (r) {
      if (r.status === 200) {
        adminPassword = pw;
        document.getElementById('admin-auth').classList.add('hidden');
        document.getElementById('admin-panel').classList.remove('hidden');
        setSection('secrets',  r.body.secrets  || [], renderSecret);
        setSection('links',    r.body.links    || [], renderLink);
        setSection('requests', r.body.requests || [], renderRequest);
      } else {
        errEl.textContent = r.body.error || 'An error occurred';
        errEl.classList.remove('hidden');
        document.getElementById('admin-password').value = '';
        document.getElementById('admin-password').focus();
      }
    })
    .catch(function (err) {
      errEl.textContent = err.message || 'An unexpected error occurred';
      errEl.classList.remove('hidden');
    });
    return false;
  };

})();
</script>
