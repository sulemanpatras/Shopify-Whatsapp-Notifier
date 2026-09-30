<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="shopify-api-key" content="{{ $apiKey }}">
<script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
<title>WhatsApp Order Notifier</title>
<style>
  :root{
    --bg:#f1f1f1; --surface:#fff; --text:#303030; --muted:#616161; --line:#e3e3e3;
    --accent:#0b6b58; --accent-ink:#fff; --accent-soft:#e3f4ef;
    --ok:#0c5132; --ok-bg:#cdfee1; --bad:#8e1f0b; --bad-bg:#fed3d1; --warn:#5e4200; --warn-bg:#ffeb78; --idle-bg:#e3e3e3;
    --chat-bg:#efe7dc; --bubble:#d9fdd3;
    --radius:12px;
  }
  @media (prefers-color-scheme: dark){
    :root{--bg:#1a1a1a;--surface:#242424;--text:#e3e3e3;--muted:#a8a8a8;--line:#3a3a3a;--accent:#3fc1a4;--accent-ink:#06211b;--accent-soft:#16342d;
      --ok:#b4fed2;--ok-bg:#0c3b26;--bad:#fdd1cf;--bad-bg:#4a1a14;--warn:#ffeb78;--warn-bg:#4a3700;--idle-bg:#3a3a3a;--chat-bg:#1b1f1d;--bubble:#1d4a3c}
  }
  *{box-sizing:border-box}
  html,body{margin:0}
  body{font:14px/1.45 Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--text);padding-bottom:48px}
  .page{max-width:980px;margin:0 auto;padding:16px}
  h1{font-size:20px;font-weight:650;margin:8px 0 4px}
  h2{font-size:15px;font-weight:650;margin:0 0 4px}
  .sub{color:var(--muted);margin:0 0 16px}
  .tabs{display:flex;gap:4px;margin:12px 0 16px;border-bottom:1px solid var(--line)}
  .tab{appearance:none;background:none;border:0;border-bottom:2px solid transparent;padding:10px 12px;font:inherit;color:var(--muted);cursor:pointer;font-weight:550}
  .tab[aria-selected=true]{color:var(--text);border-bottom-color:var(--accent)}
  .tab:focus-visible,.btn:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,.chip:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
  .card{background:var(--surface);border-radius:var(--radius);padding:16px;margin-bottom:16px;box-shadow:0 1px 0 rgba(26,26,26,.07),0 0 0 1px rgba(26,26,26,.05)}
  .row{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media(max-width:720px){.grid2,.editor{grid-template-columns:1fr!important}}
  label{display:block;font-weight:550;margin-bottom:4px}
  .help{color:var(--muted);font-size:13px;margin-top:4px}
  input[type=text],input[type=tel],input[type=password],select,textarea{width:100%;font:inherit;color:var(--text);background:var(--surface);border:1px solid #8a8a8a;border-radius:8px;padding:8px 10px}
  textarea{min-height:120px;resize:vertical}
  .field{margin-bottom:14px}
  .err{color:var(--bad);font-size:13px;margin-top:4px}
  .btn{appearance:none;font:inherit;font-weight:600;border-radius:8px;padding:8px 14px;border:1px solid var(--line);background:var(--surface);color:var(--text);cursor:pointer}
  .btn:hover{background:var(--bg)}
  .btn.primary{background:var(--accent);border-color:var(--accent);color:var(--accent-ink)}
  .btn.primary:hover{filter:brightness(1.08)}
  .btn.danger{color:var(--bad)}
  .btn[disabled]{opacity:.55;cursor:default}
  .btn.small{padding:4px 10px;font-size:13px}
  .badge{display:inline-block;border-radius:8px;padding:2px 8px;font-size:12px;font-weight:600}
  .b-sent{background:var(--ok-bg);color:var(--ok)} .b-failed{background:var(--bad-bg);color:var(--bad)}
  .b-skipped{background:var(--idle-bg);color:var(--muted)} .b-pending{background:var(--warn-bg);color:var(--warn)}
  .switch{position:relative;width:44px;height:26px;flex:none}
  .switch input{position:absolute;inset:0;opacity:0;margin:0;cursor:pointer;width:100%;height:100%}
  .switch span{position:absolute;inset:0;background:#8a8a8a;border-radius:13px;transition:background .15s}
  .switch span::after{content:"";position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;background:#fff;transition:transform .15s}
  .switch input:checked+span{background:var(--accent)} .switch input:checked+span::after{transform:translateX(18px)}
  .switch input:focus-visible+span{outline:2px solid var(--accent);outline-offset:2px}
  .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
  @media(max-width:560px){.stats{grid-template-columns:repeat(2,1fr)}}
  .stat{background:var(--surface);border-radius:var(--radius);padding:12px 14px;box-shadow:0 0 0 1px rgba(26,26,26,.05)}
  .stat b{display:block;font-size:22px;font-weight:650}
  .stat small{color:var(--muted)}
  .banner{border-radius:10px;padding:10px 12px;margin-bottom:16px;background:var(--warn-bg);color:var(--warn)}
  .banner.bad{background:var(--bad-bg);color:var(--bad)}
  table{width:100%;border-collapse:collapse}
  th,td{text-align:left;padding:10px 8px;border-bottom:1px solid var(--line);vertical-align:top}
  th{font-weight:600;color:var(--muted);font-size:13px}
  .tablewrap{overflow-x:auto}
  .empty{text-align:center;padding:32px 12px;color:var(--muted)}
  .editor{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:16px;align-items:start}
  .tlist{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
  .titem{display:flex;justify-content:space-between;align-items:center;gap:8px;text-align:left;width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--line);background:var(--surface);color:var(--text);font:inherit;cursor:pointer}
  .titem[aria-current=true]{border-color:var(--accent);background:var(--accent-soft)}
  .chips{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0}
  .chip{font:inherit;font-size:12px;border:1px solid var(--line);background:var(--bg);color:var(--text);border-radius:999px;padding:3px 10px;cursor:pointer}
  .chip:hover{border-color:var(--accent)}
  .meta{background:var(--bg);border-radius:8px;padding:10px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px;white-space:pre-wrap;word-break:break-word}
  /* The one distinctive element: the message as the customer will see it. */
  .phone{background:var(--chat-bg);border-radius:18px;padding:14px 12px 18px;min-height:190px;position:sticky;top:12px;box-shadow:0 0 0 1px rgba(26,26,26,.08) inset}
  .phone .who{font-size:12px;color:var(--muted);text-align:center;margin-bottom:12px}
  .bubble{background:var(--bubble);color:var(--text);border-radius:4px 14px 14px 14px;padding:8px 10px 6px;max-width:92%;white-space:pre-wrap;word-break:break-word;box-shadow:0 1px 1px rgba(0,0,0,.12);font-size:14px}
  .bubble time{display:block;text-align:right;font-size:11px;color:var(--muted);margin-top:2px}
  .toast{position:fixed;left:50%;bottom:20px;transform:translateX(-50%);background:#303030;color:#fff;padding:10px 16px;border-radius:10px;z-index:9;max-width:90%}
  [hidden]{display:none!important}
  @media (prefers-reduced-motion: reduce){*{transition:none!important}}
</style>
</head>
<body>
@verbatim
<div class="page">
  <h1>WhatsApp Order Notifier</h1>
  <p class="sub">Send customers a WhatsApp message the moment they place an order.</p>

  <div id="loading" class="card">Loading…</div>
  <div id="fatal" class="banner bad" hidden></div>

  <div id="app" hidden>
    <div id="statusBanner"></div>
    <div class="tabs" role="tablist">
      <button class="tab" role="tab" data-tab="settings" aria-selected="true">Settings</button>
      <button class="tab" role="tab" data-tab="messages" aria-selected="false">Messages</button>
      <button class="tab" role="tab" data-tab="log" aria-selected="false">Message log</button>
    </div>

    <!-- SETTINGS -->
    <section id="tab-settings">
      <div class="card">
        <div class="row">
          <div>
            <h2>Order confirmations</h2>
            <div class="help" id="enabledHelp"></div>
          </div>
          <label class="switch" aria-label="Send order confirmations">
            <input type="checkbox" id="enabled"><span></span>
          </label>
        </div>
      </div>

      <div class="card">
        <h2>WhatsApp connection</h2>
        <p class="help" style="margin-bottom:12px">Copy these from <b>WhatsApp → API Setup</b> in your Meta developer dashboard. Use a permanent (system user) token for production.</p>
        <div class="grid2">
          <div class="field">
            <label for="wa_phone_number_id">Phone number ID</label>
            <input type="text" id="wa_phone_number_id" inputmode="numeric" autocomplete="off">
            <div class="err" id="err-wa_phone_number_id"></div>
          </div>
          <div class="field">
            <label for="wa_access_token">Access token</label>
            <input type="password" id="wa_access_token" autocomplete="off" placeholder="">
            <div class="help" id="tokenHelp"></div>
            <div class="err" id="err-wa_access_token"></div>
          </div>
        </div>
      </div>

      <div class="card">
        <h2>Message details</h2>
        <div class="grid2" style="margin-top:12px">
          <div class="field">
            <label for="store_name">Store name in messages</label>
            <input type="text" id="store_name" maxlength="100">
            <div class="help">Used for the {{shop_name}} variable.</div>
          </div>
          <div class="field">
            <label for="default_country_code">Default country code</label>
            <input type="text" id="default_country_code" inputmode="numeric" maxlength="4">
            <div class="help">Added to local numbers like 0300 1234567. Leave empty to use <span id="fallbackCC"></span>.</div>
            <div class="err" id="err-default_country_code"></div>
          </div>
        </div>
        <div class="field" style="margin-bottom:0">
          <label for="active_template_id">Message to send</label>
          <select id="active_template_id"></select>
          <div class="err" id="err-active_template_id"></div>
        </div>
      </div>

      <div class="row" style="margin-bottom:16px">
        <button class="btn primary" id="saveSettings">Save settings</button>
        <span class="help" id="saveState"></span>
      </div>

      <div class="card">
        <h2>Send a test message</h2>
        <p class="help" style="margin-bottom:12px">Sends the selected message with sample order data. Save your settings first.</p>
        <div class="row" style="align-items:flex-end">
          <div style="flex:1;min-width:220px">
            <label for="testPhone">Your WhatsApp number</label>
            <input type="tel" id="testPhone" placeholder="+92 300 1234567">
          </div>
          <button class="btn" id="sendTest">Send test message</button>
        </div>
        <div class="err" id="testError"></div>
      </div>
    </section>

    <!-- MESSAGES -->
    <section id="tab-messages" hidden>
      <div class="editor">
        <div>
          <div class="tlist" id="tlist"></div>
          <button class="btn" id="newTemplate">Add message</button>
          <div class="card" id="editorCard" style="margin-top:16px" hidden>
            <div class="field">
              <label for="t_name">Name</label>
              <input type="text" id="t_name" maxlength="80">
              <div class="err" id="terr-name"></div>
            </div>
            <div class="grid2">
              <div class="field">
                <label for="t_mode">Type</label>
                <select id="t_mode">
                  <option value="template">Approved WhatsApp template</option>
                  <option value="text">Free text (24-hour window only)</option>
                </select>
              </div>
              <div class="field">
                <label for="t_lang">Language code</label>
                <input type="text" id="t_lang" value="en" maxlength="6">
                <div class="err" id="terr-language"></div>
              </div>
            </div>
            <div class="field" id="t_wa_wrap">
              <label for="t_wa">Template name in WhatsApp Manager</label>
              <input type="text" id="t_wa" placeholder="order_confirmation" autocomplete="off">
              <div class="err" id="terr-wa_template_name"></div>
            </div>
            <div class="field">
              <label for="t_body">Message</label>
              <textarea id="t_body" maxlength="1024"></textarea>
              <div class="chips" id="chips"></div>
              <div class="err" id="terr-body"></div>
            </div>
            <div class="field" id="metaHint">
              <label>Enter this body in WhatsApp Manager</label>
              <div class="meta" id="metaBody"></div>
              <div class="help">WhatsApp numbers the variables in order. The template must be approved, with the same name and language, before it can be sent.</div>
            </div>
            <div class="row">
              <button class="btn primary" id="saveTemplate">Save message</button>
              <button class="btn danger" id="deleteTemplate">Delete</button>
            </div>
          </div>
        </div>
        <aside class="phone" aria-label="Message preview">
          <div class="who">Preview with sample order</div>
          <div class="bubble"><span id="previewText"></span><time>9:41</time></div>
        </aside>
      </div>
    </section>

    <!-- LOG -->
    <section id="tab-log" hidden>
      <div class="stats" id="stats"></div>
      <div class="card">
        <div class="row" style="margin-bottom:8px">
          <h2>Recent orders</h2>
          <select id="logFilter" style="width:auto" aria-label="Filter by status">
            <option value="">All statuses</option><option value="sent">Sent</option><option value="failed">Failed</option>
            <option value="skipped">Skipped</option><option value="pending">Pending</option>
          </select>
        </div>
        <div class="tablewrap"><table>
          <thead><tr><th>Order</th><th>Phone</th><th>Status</th><th>When</th><th></th></tr></thead>
          <tbody id="logBody"></tbody>
        </table></div>
        <div class="empty" id="logEmpty" hidden>No orders yet. New orders will appear here with their delivery status.</div>
      </div>
    </section>
  </div>
</div>
<div class="toast" id="toast" role="status" hidden></div>

<script>
(() => {
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const state = { settings: null, templates: [], logs: [], stats: {}, variables: {}, sample: {}, editing: null };

  // ---------- API ----------
  async function api(path, method = 'GET', body) {
    const send = async () => {
      const token = await shopify.idToken();
      return fetch('/api/admin/' + path, {
        method,
        headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
      });
    };
    let res = await send();
    if (res.status === 401 && res.headers.get('X-Shopify-Retry-Invalid-Session-Request')) res = await send();
    const data = await res.json().catch(() => ({}));
    if (!res.ok) { const e = new Error(data.message || data.error || 'Something went wrong. Try again.'); e.status = res.status; e.errors = data.errors || {}; throw e; }
    return data;
  }

  let toastTimer;
  function toast(msg) {
    try { shopify.toast.show(msg); return; } catch (_) {}
    const t = $('toast'); t.textContent = msg; t.hidden = false; clearTimeout(toastTimer); toastTimer = setTimeout(() => t.hidden = true, 3000);
  }
  function clearErrors(prefix) { document.querySelectorAll(`[id^="${prefix}"]`).forEach(e => e.textContent = ''); }
  function showErrors(prefix, errs) { Object.entries(errs || {}).forEach(([k, v]) => { const el = $(prefix + k); if (el) el.textContent = v[0]; }); }

  // ---------- Tabs ----------
  document.querySelectorAll('.tab').forEach(btn => btn.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(b => b.setAttribute('aria-selected', b === btn));
    ['settings', 'messages', 'log'].forEach(n => $('tab-' + n).hidden = n !== btn.dataset.tab);
    if (btn.dataset.tab === 'log') refreshLogs();
  }));

  // ---------- Render ----------
  function renderStatus() {
    const s = state.settings;
    let html = '';
    if (!s.ready) html = '<div class="banner">Finish setup: add your WhatsApp phone number ID and access token, and choose a message to send.</div>';
    else if (!s.enabled) html = '<div class="banner">Order confirmations are off. Turn them on in Settings to start messaging customers.</div>';
    $('statusBanner').innerHTML = html;
  }

  function renderSettings() {
    const s = state.settings;
    $('enabled').checked = s.enabled;
    $('enabledHelp').textContent = s.enabled ? 'Customers get a WhatsApp message for every new order.' : 'Off. New orders are logged but no messages are sent.';
    $('wa_phone_number_id').value = s.wa_phone_number_id || '';
    $('wa_access_token').value = '';
    $('wa_access_token').placeholder = s.has_token ? '••••••••••••  (saved)' : '';
    $('tokenHelp').textContent = s.has_token ? 'A token is saved. Paste a new one to replace it.' : 'Stored encrypted.';
    $('store_name').value = s.store_name || '';
    $('default_country_code').value = s.default_country_code || '';
    $('fallbackCC').textContent = '+' + s.fallback_country_code;
    const sel = $('active_template_id');
    sel.innerHTML = '<option value="">Choose a message…</option>' + state.templates.map(t => `<option value="${t.id}">${esc(t.name)}</option>`).join('');
    sel.value = s.active_template_id || '';
  }

  function renderTemplates() {
    $('tlist').innerHTML = state.templates.map(t => `
      <button class="titem" data-id="${t.id}" aria-current="${state.editing === t.id}">
        <span>${esc(t.name)}</span>
        <span class="badge ${t.id === state.settings.active_template_id ? 'b-sent' : 'b-skipped'}">${t.id === state.settings.active_template_id ? 'In use' : (t.mode === 'text' ? 'Free text' : 'Template')}</span>
      </button>`).join('');
    $('tlist').querySelectorAll('.titem').forEach(b => b.addEventListener('click', () => editTemplate(Number(b.dataset.id))));
  }

  function varsToMeta(body) {
    const order = [...new Set([...body.matchAll(/\{\{\s*([a-z_]+)\s*\}\}/gi)].map(m => m[1].toLowerCase()))];
    return body.replace(/\{\{\s*([a-z_]+)\s*\}\}/gi, (_, k) => '{{' + (order.indexOf(k.toLowerCase()) + 1) + '}}');
  }
  function renderPreview() {
    const body = $('t_body').value || '';
    $('previewText').textContent = body.replace(/\{\{\s*([a-z_]+)\s*\}\}/gi, (m, k) => state.sample[k.toLowerCase()] ?? m) || 'Your message will appear here.';
    $('metaBody').textContent = varsToMeta(body);
    const isTpl = $('t_mode').value === 'template';
    $('t_wa_wrap').hidden = !isTpl; $('metaHint').hidden = !isTpl;
  }

  function editTemplate(id) {
    state.editing = id;
    const t = id === 'new' ? { name: '', mode: 'template', wa_template_name: '', language: 'en_US', body: '' } : state.templates.find(x => x.id === id);
    $('editorCard').hidden = false;
    $('t_name').value = t.name; $('t_mode').value = t.mode; $('t_lang').value = t.language;
    $('t_wa').value = t.wa_template_name || ''; $('t_body').value = t.body;
    $('deleteTemplate').hidden = id === 'new';
    clearErrors('terr-'); renderTemplates(); renderPreview();
  }

  $('chips').innerHTML = '';
  function renderChips() {
    $('chips').innerHTML = Object.entries(state.variables).map(([k, d]) => `<button type="button" class="chip" data-k="${k}" title="${esc(d)}">${esc(k)}</button>`).join('');
    $('chips').querySelectorAll('.chip').forEach(c => c.addEventListener('click', () => {
      const ta = $('t_body'), token = '{{' + c.dataset.k + '}}';
      const s = ta.selectionStart ?? ta.value.length, e = ta.selectionEnd ?? s;
      ta.value = ta.value.slice(0, s) + token + ta.value.slice(e);
      ta.focus(); ta.selectionStart = ta.selectionEnd = s + token.length; renderPreview();
    }));
  }

  function renderStats() {
    const st = state.stats;
    $('stats').innerHTML = [['sent', 'Sent'], ['failed', 'Failed'], ['skipped', 'Skipped'], ['pending', 'Pending']]
      .map(([k, l]) => `<div class="stat"><b>${st[k] || 0}</b><small>${l}</small></div>`).join('');
  }

  function renderLogs() {
    $('logEmpty').hidden = state.logs.length > 0;
    $('logBody').innerHTML = state.logs.map(l => `
      <tr>
        <td><b>${esc(l.order_name)}</b></td>
        <td>${l.phone ? '+' + esc(l.phone) : '<span class="help">No number</span>'}</td>
        <td><span class="badge b-${esc(l.status)}">${esc(l.status)}</span>${l.error ? `<div class="help">${esc(l.error)}</div>` : ''}</td>
        <td>${new Date(l.sent_at || l.created_at).toLocaleString()}</td>
        <td>${l.phone && l.status !== 'sent' ? `<button class="btn small" data-resend="${l.id}">Send again</button>` : ''}</td>
      </tr>`).join('');
    $('logBody').querySelectorAll('[data-resend]').forEach(b => b.addEventListener('click', async () => {
      b.disabled = true;
      try { const r = await api(`logs/${b.dataset.resend}/resend`, 'POST'); state.stats = r.stats; toast(r.log.status === 'sent' ? 'Message sent' : 'Could not send: ' + (r.log.error || 'unknown error')); await refreshLogs(); }
      catch (e) { toast(e.message); b.disabled = false; }
    }));
  }

  async function refreshLogs() {
    const r = await api('logs' + ($('logFilter').value ? '?status=' + $('logFilter').value : ''));
    state.logs = r.logs; state.stats = r.stats; renderStats(); renderLogs();
  }
  $('logFilter').addEventListener('change', () => refreshLogs().catch(e => toast(e.message)));

  // ---------- Actions ----------
  $('saveSettings').addEventListener('click', async () => {
    clearErrors('err-'); $('saveSettings').disabled = true; $('saveState').textContent = 'Saving…';
    try {
      const payload = {
        enabled: $('enabled').checked,
        store_name: $('store_name').value || null,
        wa_phone_number_id: $('wa_phone_number_id').value || null,
        default_country_code: $('default_country_code').value || null,
        active_template_id: $('active_template_id').value ? Number($('active_template_id').value) : null,
      };
      if ($('wa_access_token').value) payload.wa_access_token = $('wa_access_token').value;
      const r = await api('settings', 'PUT', payload);
      state.settings = r.settings; renderSettings(); renderStatus(); renderTemplates();
      $('saveState').textContent = ''; toast('Settings saved');
    } catch (e) {
      showErrors('err-', e.errors); $('saveState').textContent = ''; toast(e.message);
      if (e.errors && e.errors.enabled) $('enabled').checked = false;
    } finally { $('saveSettings').disabled = false; }
  });

  $('enabled').addEventListener('change', () => {
    $('enabledHelp').textContent = $('enabled').checked ? 'Save settings to start messaging customers.' : 'Save settings to stop messaging customers.';
  });

  $('sendTest').addEventListener('click', async () => {
    $('testError').textContent = ''; $('sendTest').disabled = true;
    try {
      const r = await api('test-message', 'POST', { phone: $('testPhone').value, template_id: $('active_template_id').value ? Number($('active_template_id').value) : null });
      toast('Test message sent to +' + r.to);
    } catch (e) { $('testError').textContent = e.message; }
    finally { $('sendTest').disabled = false; }
  });

  $('newTemplate').addEventListener('click', () => editTemplate('new'));
  ['t_body', 't_mode'].forEach(id => $(id).addEventListener('input', renderPreview));

  $('saveTemplate').addEventListener('click', async () => {
    clearErrors('terr-'); $('saveTemplate').disabled = true;
    const payload = { name: $('t_name').value, mode: $('t_mode').value, language: $('t_lang').value, wa_template_name: $('t_wa').value || null, body: $('t_body').value };
    try {
      if (state.editing === 'new') {
        const r = await api('templates', 'POST', payload); state.templates.push(r.template); state.editing = r.template.id;
      } else {
        const r = await api('templates/' + state.editing, 'PUT', payload);
        state.templates = state.templates.map(t => t.id === r.template.id ? r.template : t);
      }
      renderTemplates(); renderSettings(); toast('Message saved');
    } catch (e) { showErrors('terr-', e.errors); toast(e.message); }
    finally { $('saveTemplate').disabled = false; }
  });

  $('deleteTemplate').addEventListener('click', async () => {
    if (!confirm('Delete this message?')) return;
    try {
      await api('templates/' + state.editing, 'DELETE');
      state.templates = state.templates.filter(t => t.id !== state.editing); state.editing = null;
      $('editorCard').hidden = true; renderTemplates(); renderSettings(); toast('Message deleted');
    } catch (e) { toast(e.message); }
  });

  // ---------- Boot ----------
  (async () => {
    try {
      const d = await api('bootstrap');
      Object.assign(state, { settings: d.settings, templates: d.templates, logs: d.logs, stats: d.stats, variables: d.variables, sample: d.sample });
      renderChips(); renderSettings(); renderStatus(); renderTemplates(); renderStats(); renderLogs();
      if (state.templates.length) editTemplate(state.settings.active_template_id || state.templates[0].id); else renderPreview();
      $('loading').hidden = true; $('app').hidden = false;
    } catch (e) {
      $('loading').hidden = true; $('fatal').hidden = false;
      $('fatal').textContent = 'The app could not load: ' + e.message + ' Reload the page from your Shopify admin.';
    }
  })();
})();
</script>
@endverbatim
</body>
</html>
