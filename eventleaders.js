/* PYM — Event leaders picker (Secretariat). Loaded after servers.js on index.php. */
(function () {
  const $ = id => document.getElementById(id);
  const E = s => escapeHtml(s);
  async function el(a, p) {
    try {
      const r = await fetch('api_eventleaders.php?action=' + a, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(p || {}) });
      return await r.json();
    } catch (e) { return { success: false, message: 'Network error. Please check your connection.' }; }
  }
  let USERS = null;
  async function users() {
    if (USERS) return USERS;
    const r = await el('users');
    USERS = r.success ? r.data : [];
    return USERS;
  }

  const PICK = {};
  let CUR = null;

  function mount(form) {
    const box = document.createElement('div');
    box.className = 'srv-box';
    box.innerHTML = `
      <div class="srv-check" style="cursor:default"><i class="fas fa-user-shield" style="color:var(--amber)"></i> <span>Event leaders</span></div>
      <div class="srv-panel">
        <p class="srv-note">Pick users with an account. They can mark attendance for this event only, until the event is done. Their saved attendance stays afterwards. Secretariat is never limited.</p>
        <div class="srv-search"><i class="fas fa-user-plus"></i><input type="text" class="ldr-input" placeholder="Search an account..." autocomplete="off"/><div class="srv-list hidden"></div></div>
        <div class="srv-chips"></div>
      </div>`;
    form.querySelector('.form-actions').before(box);
    const inp = box.querySelector('.ldr-input'), list = box.querySelector('.srv-list'), chips = box.querySelector('.srv-chips');
    const sel = new Map();
    const draw = () => {
      chips.innerHTML = sel.size
        ? [...sel].map(([id, n]) => `<span class="srv-chip">${E(n)}<button type="button" data-rm="${id}" aria-label="Remove">&times;</button></span>`).join('')
        : '<span class="srv-empty">No event leaders yet.</span>';
    };
    const show = async () => {
      const q = inp.value.trim().toLowerCase();
      const all = await users();
      const items = all.filter(u => !sel.has(String(u.id)) && (!q || u.full_name.toLowerCase().includes(q))).slice(0, 40);
      list.innerHTML = items.length
        ? items.map(u => `<div class="srv-opt" data-id="${u.id}" data-name="${E(u.full_name)}">${E(u.full_name)}<small style="color:var(--text-soft);margin-left:6px">${u.role === 'secretariat' ? 'Secretariat' : 'Leader'}</small></div>`).join('')
        : '<div class="srv-none">No match</div>';
      list.classList.remove('hidden');
    };
    const add = o => { sel.set(String(o.dataset.id), o.dataset.name); inp.value = ''; list.classList.add('hidden'); draw(); inp.focus(); };
    inp.addEventListener('input', show);
    inp.addEventListener('focus', show);
    inp.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); const o = list.querySelector('.srv-opt'); if (o) add(o); }
      else if (e.key === 'Escape') list.classList.add('hidden');
    });
    list.addEventListener('mousedown', e => { const o = e.target.closest('.srv-opt'); if (o) { e.preventDefault(); add(o); } });
    chips.addEventListener('click', e => { const b = e.target.closest('[data-rm]'); if (b) { sel.delete(b.dataset.rm); draw(); } });
    document.addEventListener('mousedown', e => { if (!box.contains(e.target)) list.classList.add('hidden'); });

    const api = {
      get() { return [...sel.keys()]; },
      async set(ids) {
        sel.clear();
        const all = await users();
        (ids || []).forEach(id => { const u = all.find(x => String(x.id) === String(id)); if (u) sel.set(String(u.id), u.full_name); });
        draw();
      },
      reset() { sel.clear(); inp.value = ''; list.classList.add('hidden'); draw(); }
    };
    form.addEventListener('reset', () => setTimeout(api.reset, 0));
    draw();
    PICK[form.getAttribute('id')] = api;
    return api;
  }

  async function loadFor(formId, eventId) {
    const p = PICK[formId];
    if (!p) return;
    p.reset();
    const r = await el('get', { event_id: eventId });
    if (r.success) p.set(r.data);
  }

  document.addEventListener('DOMContentLoaded', async () => {
    if (typeof apiCall !== 'function') return;
    const s = await apiCall('check_session');
    if (!(s.success && s.data.role === 'secretariat')) return;

    ['adminEventForm', 'categoryEventForm'].forEach(id => { const f = $(id); if (f) mount(f); });

    document.addEventListener('submit', e => {
      const p = PICK[e.target.getAttribute && e.target.getAttribute('id')];
      CUR = p ? p.get() : null;
    }, true);

    document.addEventListener('click', e => {
      const b = e.target.closest('.cat-ev-edit');
      if (b) { loadFor('categoryEventForm', b.dataset.id); return; }
      const a = e.target.closest('#adminEventsTableBody .edit-btn');
      if (a) loadFor('adminEventForm', a.dataset.id);
    });
    const nb = $('adminShowEventFormBtn');
    if (nb) nb.addEventListener('click', () => PICK['adminEventForm'] && PICK['adminEventForm'].reset());
    document.addEventListener('click', e => { if (e.target.closest('.cat-add-event') && PICK['categoryEventForm']) PICK['categoryEventForm'].reset(); });
  });

  const _api = apiCall;
  apiCall = async function (action, payload) {
    const r = await _api(action, payload);
    try {
      if (action === 'save_category_event' && r && r.success && CUR !== null) {
        await el('set', { event_id: payload.id || 0, category_id: payload.category_id, title: payload.title, event_date: payload.event_date, user_ids: CUR });
      }
    } catch (e) {}
    return r;
  };
})();
