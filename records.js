/* ============================================================
   PYM — Attendance Records (filter + review + Excel export)
   Load AFTER app.js and coy.js. Requires SheetJS (xlsx) from CDN.
   ============================================================ */

(function injectRecordStyles() {
  const css = `
  .rec-card{border-top:3px solid var(--gold-mid);margin-bottom:20px}
  .rec-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;align-items:end;padding:18px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--radius);margin-bottom:14px}
  .rec-filters .form-field{margin-bottom:0}
  .rec-quick{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 16px}
  .rec-quick button{border:1.5px solid var(--border);background:var(--surface);color:var(--text-mid);font-size:11.5px;font-weight:700;padding:6px 12px;border-radius:99px;transition:all var(--dur) var(--ease)}
  .rec-quick button:hover{background:var(--ink);color:#fff;border-color:var(--ink)}
  .rec-label{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:11px;font-weight:800;color:var(--text-soft);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px}
  .rec-label small{font-weight:700;text-transform:none;letter-spacing:0;color:var(--text-faint)}
  .rec-link{border:none;background:none;color:var(--sapphire-mid);font-size:11.5px;font-weight:700;padding:0 0 0 10px}
  .rec-link:hover{color:var(--sapphire);text-decoration:underline}
  .rec-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
  .rec-chip{display:inline-flex;align-items:center;gap:7px;border:1.5px solid var(--border);background:var(--surface);color:var(--text-mid);font-size:12.5px;font-weight:700;padding:8px 14px;border-radius:99px;transition:all var(--dur) var(--ease)}
  .rec-chip i{font-size:10px;opacity:0;width:0;transition:all var(--dur) var(--ease)}
  .rec-chip:hover{border-color:var(--sapphire-glow)}
  .rec-chip.active{background:var(--sapphire-pale);border-color:var(--sapphire-glow);color:var(--sapphire)}
  .rec-chip.active i{opacity:1;width:10px}
  .rec-chip .rec-sys{font-size:9px;font-weight:800;background:var(--gold-pale);color:var(--amber);padding:2px 7px;border-radius:99px;text-transform:uppercase;letter-spacing:.4px}
  .rec-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
  .rec-excel-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border:none;border-radius:var(--radius-sm);background:linear-gradient(135deg,var(--emerald),#047857);color:#fff;font-size:13px;font-weight:700;box-shadow:var(--shadow-green);transition:all var(--dur) var(--ease)}
  .rec-excel-btn:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(5,150,105,.38)}
  .rec-excel-btn:disabled{opacity:.45;cursor:not-allowed;transform:none;box-shadow:none}
  .rec-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:18px}
  .rec-stat{background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);padding:14px 16px;border-top-width:3px}
  .rec-stat b{display:block;font-family:'Plus Jakarta Sans',sans-serif;font-size:26px;font-weight:800;letter-spacing:-.8px;line-height:1.1}
  .rec-stat span{font-size:10.5px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:.7px}
  .rec-stat.blue{border-top-color:var(--sapphire-glow)}.rec-stat.green{border-top-color:var(--emerald-mid)}
  .rec-stat.amber{border-top-color:var(--amber)}.rec-stat.red{border-top-color:var(--crimson-mid)}.rec-stat.gold{border-top-color:var(--gold-mid)}
  .rec-summary-line{font-size:12.5px;color:var(--text-soft);margin:0 0 14px}
  .rec-summary-line strong{color:var(--text-primary)}
  .rec-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
  .rec-tabs{display:flex;gap:4px;background:var(--surface-2);border:1px solid var(--border);border-radius:10px;padding:4px}
  .rec-tab{border:none;background:none;padding:8px 14px;font-size:12.5px;font-weight:700;color:var(--text-soft);border-radius:7px;transition:all var(--dur) var(--ease)}
  .rec-tab:hover{color:var(--text-primary)}
  .rec-tab.active{background:var(--ink);color:#fff;box-shadow:var(--shadow-sm)}
  .rec-tools{display:flex;gap:8px;flex-wrap:wrap}
  .rec-search{position:relative}
  .rec-search i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:12px}
  .rec-search input{padding:9px 12px 9px 32px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:13px;outline:none;width:210px}
  .rec-tools select{border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:9px 30px 9px 12px;font-size:13px;background-color:var(--surface);outline:none}
  .rec-search input:focus,.rec-tools select:focus{border-color:var(--sapphire-glow);box-shadow:0 0 0 3px rgba(59,130,246,.10)}
  .rec-rate{display:inline-flex;border-radius:99px;padding:3px 10px;font-size:11px;font-weight:800}
  .rec-rate.hi{background:var(--emerald-pale);color:var(--emerald)}.rec-rate.mid{background:var(--amber-pale);color:var(--amber)}.rec-rate.lo{background:var(--crimson-pale);color:var(--crimson)}
  .rec-pager{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:14px;font-size:12.5px;color:var(--text-soft)}
  .rec-pager div{display:flex;gap:6px;align-items:center}
  .rec-pager button{min-width:34px;height:34px;border:1.5px solid var(--border);background:var(--surface);border-radius:8px;font-size:12px;font-weight:700;color:var(--text-mid)}
  .rec-pager button:hover:not(:disabled){background:var(--ink);color:#fff;border-color:var(--ink)}
  .rec-pager button:disabled{opacity:.4;cursor:not-allowed}
  .rec-members{border:1.5px solid var(--border);border-radius:var(--radius);padding:12px;margin-bottom:18px;background:var(--surface)}
  .rec-mem-top{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px}
  .rec-mem-top .rec-search{flex:1;min-width:200px}.rec-mem-top .rec-search input{width:100%}
  .rec-mem-selected{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}
  .rec-mem-selected:empty{display:none}
  .rec-mem-tag{display:inline-flex;align-items:center;gap:6px;background:var(--sapphire-pale);color:var(--sapphire);border:1.5px solid var(--sapphire-glow);border-radius:99px;padding:4px 6px 4px 11px;font-size:12px;font-weight:700}
  .rec-mem-tag button{border:none;background:rgba(0,0,0,.08);width:18px;height:18px;border-radius:50%;font-size:10px;line-height:1;color:inherit}
  .rec-mem-list{max-height:190px;overflow:auto;border:1px solid var(--border);border-radius:var(--radius-sm)}
  .rec-mem-item{display:flex;align-items:center;gap:10px;padding:8px 12px;font-size:13px;cursor:pointer;border-bottom:1px solid var(--border)}
  .rec-mem-item:last-child{border-bottom:none}
  .rec-mem-item:hover{background:var(--surface-2)}
  .rec-mem-item small{margin-left:auto;color:var(--text-faint);font-weight:600}
  .rec-placeholder{text-align:center;padding:46px 20px;color:var(--text-faint);font-size:13px}
  .rec-placeholder i{display:block;font-size:30px;opacity:.35;margin-bottom:10px}
  @media (max-width:640px){.rec-search input{width:100%}.rec-search{flex:1}.rec-tabs{width:100%}.rec-tab{flex:1;padding:8px 6px}}
  `;
  const el = document.createElement('style');
  el.textContent = css;
  document.head.appendChild(el);
})();

const REC = { cats: [], selected: new Set(), rows: [], filtered: [], view: 'records', page: 1, per: 50, meta: null, catsLoaded: false, members: [], memSel: new Set(), memLoaded: false };

async function recApi(action, payload = null) {
  try {
    const res = await fetch(`api_records.php?action=${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload || {})
    });
    return await res.json();
  } catch (err) {
    return { success: false, message: 'Network error. Please check your connection.' };
  }
}

const recEl = (id) => document.getElementById(id);
const recYmd = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
const recPct = (p, t) => (t > 0 ? Math.round((p / t) * 100) : 0);
const recRateCls = (v) => (v >= 80 ? 'hi' : v >= 50 ? 'mid' : 'lo');
const recTime = (t) => (t ? formatTime(String(t).substring(0, 5)) : '—');

/* ---------- Aggregations ---------- */
function recStats(rows) {
  let present = 0, excused = 0;
  const members = new Set(), events = new Set();
  rows.forEach(r => {
    if (r.status === 'present') present++;
    else if (r.status === 'excused') excused++;
    members.add(r.member);
    events.add(r.date + '|' + r.category + '|' + r.event);
  });
  const total = rows.length;
  return { total, present, absent: total - present, excused, rate: recPct(present, total), members: members.size, events: events.size };
}

function recGroup(rows, keyFn, baseFn) {
  const map = new Map();
  rows.forEach(r => {
    const k = keyFn(r);
    if (!map.has(k)) map.set(k, Object.assign(baseFn(r), { present: 0, absent: 0, excused: 0, total: 0 }));
    const g = map.get(k);
    g.total++;
    if (r.status === 'present') g.present++; else { g.absent++; if (r.status === 'excused') g.excused++; }
  });
  return Array.from(map.values()).map(g => Object.assign(g, { rate: recPct(g.present, g.total) }));
}

const recByMember = (rows) => recGroup(rows, r => r.member, r => ({ member: r.member, slot: r.slot || '—' }))
  .sort((a, b) => a.member.localeCompare(b.member));
const recByEvent = (rows) => recGroup(rows, r => r.date + '|' + r.category + '|' + r.event,
  r => ({ date: r.date, category: r.category, event: r.event, time: r.event_time }));
const recByCategory = (rows) => recGroup(rows, r => r.category, r => ({ category: r.category }))
  .map(c => Object.assign(c, { events: new Set(rows.filter(r => r.category === c.category).map(r => r.date + '|' + r.event)).size }))
  .sort((a, b) => a.category.localeCompare(b.category));

/* ---------- Page lifecycle ---------- */
window.loadRecordsPage = async function () {
  recEl('recFrom').min = recEl('recTo').min = '2026-10-01';
  if (!recEl('recFrom').value || !recEl('recTo').value) recSetRange('month');
  if (!REC.catsLoaded) await loadRecCats();
  if (!REC.memLoaded) await loadRecMembers();
};

function recSetRange(kind) {
  const now = new Date();
  let from, to = now;
  if (kind === 'month') { from = new Date(now.getFullYear(), now.getMonth(), 1); to = new Date(now.getFullYear(), now.getMonth() + 1, 0); }
  else if (kind === 'lastmonth') { from = new Date(now.getFullYear(), now.getMonth() - 1, 1); to = new Date(now.getFullYear(), now.getMonth(), 0); }
  else if (kind === '30') from = new Date(now.getTime() - 29 * 86400000);
  else if (kind === '90') from = new Date(now.getTime() - 89 * 86400000);
  else if (kind === 'year') { from = new Date(now.getFullYear(), 0, 1); to = new Date(now.getFullYear(), 11, 31); }
  if (from < new Date(2026, 9, 1)) from = new Date(2026, 9, 1);
  if (to < from) to = from;
  recEl('recFrom').value = recYmd(from);
  recEl('recTo').value = recYmd(to);
}

async function loadRecCats() {
  const box = recEl('recCatChips');
  box.innerHTML = '<span class="coy-empty">Loading categories...</span>';
  const res = await recApi('get_record_categories');
  if (!res.success) { box.innerHTML = `<span class="coy-empty">${escapeHtml(res.message)}</span>`; return; }
  REC.cats = res.data;
  REC.selected = new Set(REC.cats.map(c => String(c.id)));
  REC.catsLoaded = true;
  renderRecChips();
}

function renderRecChips() {
  const box = recEl('recCatChips');
  if (!REC.cats.length) { box.innerHTML = '<span class="coy-empty">No categories yet. Add them in Admin Panel → Event Categories.</span>'; return; }
  box.innerHTML = REC.cats.map(c => `
    <button type="button" class="rec-chip ${REC.selected.has(String(c.id)) ? 'active' : ''}" data-id="${c.id}">
      <i class="fas fa-check"></i>${escapeHtml(c.name)}${Number(c.is_system) === 1 ? '<span class="rec-sys">Default</span>' : ''}
    </button>`).join('');
  recEl('recCatCount').textContent = `${REC.selected.size} of ${REC.cats.length} selected`;
}

/* ---------- Member picker ---------- */
async function loadRecMembers() {
  const res = await recApi('get_record_members');
  if (!res.success) { recEl('recMemList').innerHTML = `<div class="coy-empty" style="padding:12px">${escapeHtml(res.message)}</div>`; return; }
  REC.members = res.data;
  REC.memLoaded = true;
  renderRecMembers();
}

function renderRecMembers() {
  const q = recEl('recMemSearch').value.trim().toLowerCase();
  const list = REC.members.filter(m => !q || m.full_name.toLowerCase().includes(q));
  recEl('recMemList').innerHTML = list.length
    ? list.map(m => `<label class="rec-mem-item"><input type="checkbox" data-id="${m.id}" ${REC.memSel.has(String(m.id)) ? 'checked' : ''}/>
        <span>${escapeHtml(m.full_name)}</span><small>${escapeHtml(m.slot || 'No slot')}${m.status === 'inactive' ? ' · inactive' : ''}</small></label>`).join('')
    : '<div class="coy-empty" style="padding:12px">No members found.</div>';
  const byId = {}; REC.members.forEach(m => byId[String(m.id)] = m);
  recEl('recMemSelected').innerHTML = Array.from(REC.memSel).map(id =>
    `<span class="rec-mem-tag">${escapeHtml(byId[id] ? byId[id].full_name : id)}<button type="button" data-rm="${id}">&times;</button></span>`).join('');
  recEl('recMemCount').textContent = REC.memSel.size ? `${REC.memSel.size} member(s) selected` : 'All members';
}

/* ---------- Generate ---------- */
async function recGenerate() {
  const from = recEl('recFrom').value, to = recEl('recTo').value;
  if (!from || !to) { alert('Please choose a start and end date.'); return; }
  if (from > to) { alert('The start date must be before the end date.'); return; }
  if (!REC.selected.size) { alert('Select at least one category.'); return; }

  const btn = recEl('recGenerateBtn');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
  const res = await recApi('get_records', { from, to, category_ids: Array.from(REC.selected), member_ids: Array.from(REC.memSel) });
  btn.disabled = false;
  btn.innerHTML = orig;

  if (!res.success) { alert(res.message); return; }
  REC.rows = res.data.rows;
  REC.meta = { from, to, categories: res.data.categories, memberLabel: REC.memSel.size ? REC.members.filter(m => REC.memSel.has(String(m.id))).map(m => m.full_name).join(', ') : 'All members' };
  recEl('recResults').classList.remove('hidden');
  recApplyFilters();
  recEl('recResults').scrollIntoView({ behavior: 'smooth' });
}

function recApplyFilters() {
  const status = recEl('recStatus').value;
  const q = recEl('recSearch').value.trim().toLowerCase();
  REC.filtered = REC.rows.filter(r => {
    if (status === 'absent' && r.status === 'present') return false;
    if (status && status !== 'absent' && r.status !== status) return false;
    if (q && !(`${r.member} ${r.event} ${r.category} ${r.slot || ''} ${r.reason || ''}`).toLowerCase().includes(q)) return false;
    return true;
  });
  REC.page = 1;
  recRender();
}

/* ---------- Render ---------- */
function recRender() {
  const s = recStats(REC.filtered);
  recEl('recStatTotal').textContent = s.total.toLocaleString();
  recEl('recStatPresent').textContent = s.present.toLocaleString();
  recEl('recStatAbsent').textContent = s.absent.toLocaleString();
  recEl('recStatExcused').textContent = s.excused.toLocaleString();
  recEl('recStatRate').textContent = s.rate + '%';
  recEl('recSummaryLine').innerHTML =
    `<strong>${formatDate(REC.meta.from)}</strong> to <strong>${formatDate(REC.meta.to)}</strong> · ` +
    `${escapeHtml(REC.meta.categories.join(', '))} · ${s.events} event(s) · ${s.members} member(s)`;
  recEl('recDownloadBtn').disabled = !s.total;

  document.querySelectorAll('.rec-tab').forEach(t => t.classList.toggle('active', t.dataset.view === REC.view));

  let head, rows;
  if (REC.view === 'members') {
    head = ['Member', 'Slot', 'Present', 'Absent (incl. Excused)', 'of which Excused', 'Total', 'Attendance'];
    rows = recByMember(REC.filtered).map(m => [
      escapeHtml(m.member), escapeHtml(m.slot), m.present, m.absent, m.excused, m.total,
      `<span class="rec-rate ${recRateCls(m.rate)}">${m.rate}%</span>`]);
  } else if (REC.view === 'events') {
    head = ['Date', 'Category', 'Event', 'Time', 'Present', 'Absent (incl. Excused)', 'of which Excused', 'Total', 'Attendance'];
    rows = recByEvent(REC.filtered).map(e => [
      formatDate(e.date), escapeHtml(e.category), escapeHtml(e.event), recTime(e.time), e.present, e.absent, e.excused, e.total,
      `<span class="rec-rate ${recRateCls(e.rate)}">${e.rate}%</span>`]);
  } else {
    head = ['Date', 'Category', 'Event', 'Member', 'Slot', 'Status', 'Time In', 'Reason'];
    rows = REC.filtered.map(r => [
      formatDate(r.date), escapeHtml(r.category), escapeHtml(r.event), escapeHtml(r.member), escapeHtml(r.slot || '—'),
      `<span class="status-pill ${r.status === 'present' ? 'active' : r.status === 'excused' ? 'excused' : 'inactive'}">${r.status}</span>`, recTime(r.time_in), escapeHtml(r.reason || '—')]);
  }

  const pages = Math.max(1, Math.ceil(rows.length / REC.per));
  if (REC.page > pages) REC.page = pages;
  const slice = rows.slice((REC.page - 1) * REC.per, REC.page * REC.per);

  recEl('recTableHead').innerHTML = '<tr>' + head.map(h => `<th>${h}</th>`).join('') + '</tr>';
  recEl('recTableBody').innerHTML = slice.length
    ? slice.map(r => '<tr>' + r.map(c => `<td>${c}</td>`).join('') + '</tr>').join('')
    : `<tr><td colspan="${head.length}"><div class="rec-placeholder"><i class="fas fa-folder-open"></i>No attendance records match your filters.</div></td></tr>`;

  const from = rows.length ? (REC.page - 1) * REC.per + 1 : 0;
  const to = Math.min(REC.page * REC.per, rows.length);
  recEl('recPager').innerHTML = `
    <span>Showing ${from}–${to} of ${rows.length.toLocaleString()}</span>
    <div>
      <button type="button" data-pg="prev" ${REC.page <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>
      <span>Page ${REC.page} / ${pages}</span>
      <button type="button" data-pg="next" ${REC.page >= pages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>
    </div>`;
}

/* ---------- Excel export ---------- */
function recSheet(aoa, minW = 8) {
  const ws = XLSX.utils.aoa_to_sheet(aoa);
  const widths = [];
  aoa.forEach(row => row.forEach((v, i) => {
    const len = v === null || v === undefined ? 0 : String(v).length;
    widths[i] = Math.max(widths[i] || minW, Math.min(len + 2, 48));
  }));
  ws['!cols'] = widths.map(w => ({ wch: w }));
  return ws;
}

function recExport() {
  if (!REC.filtered.length) { alert('There is nothing to export.'); return; }
  if (typeof XLSX === 'undefined') { alert('The Excel library could not be loaded. Check your internet connection and refresh the page.'); return; }

  const rows = REC.filtered;
  const s = recStats(rows);
  const wb = XLSX.utils.book_new();
  const statusFilter = recEl('recStatus').value;

  const summary = [
    ['PYM Attendance Records'],
    [],
    ['Date range', `${REC.meta.from} to ${REC.meta.to}`],
    ['Categories', REC.meta.categories.join(', ')],
    ['Members', REC.meta.memberLabel],
    ['Status filter', statusFilter ? statusFilter : 'All'],
    ['Generated', new Date().toLocaleString('en-US')],
    [],
    ['Total records', s.total],
    ['Present', s.present],
    ['Absent', s.absent],
    ['  of which Excused', s.excused],
    ['Attendance rate (%)', s.rate],
    ['Events', s.events],
    ['Members', s.members],
    [],
    ['By Category'],
    ['Category', 'Events', 'Present', 'Absent (incl. Excused)', 'of which Excused', 'Total', 'Attendance (%)'],
    ...recByCategory(rows).map(c => [c.category, c.events, c.present, c.absent, c.excused, c.total, c.rate])
  ];
  XLSX.utils.book_append_sheet(wb, recSheet(summary, 14), 'Summary');

  XLSX.utils.book_append_sheet(wb, recSheet([
    ['Date', 'Category', 'Event', 'Event Time', 'Member', 'Slot', 'Status', 'Time In', 'Reason'],
    ...rows.map(r => [r.date, r.category, r.event, recTime(r.event_time), r.member, r.slot || '', r.status.charAt(0).toUpperCase() + r.status.slice(1), recTime(r.time_in), r.reason || ''])
  ]), 'Records');

  XLSX.utils.book_append_sheet(wb, recSheet([
    ['Member', 'Slot', 'Present', 'Absent (incl. Excused)', 'of which Excused', 'Total', 'Attendance (%)'],
    ...recByMember(rows).map(m => [m.member, m.slot, m.present, m.absent, m.excused, m.total, m.rate])
  ]), 'Member Summary');

  XLSX.utils.book_append_sheet(wb, recSheet([
    ['Date', 'Category', 'Event', 'Time', 'Present', 'Absent (incl. Excused)', 'of which Excused', 'Total', 'Attendance (%)'],
    ...recByEvent(rows).map(e => [e.date, e.category, e.event, recTime(e.time), e.present, e.absent, e.excused, e.total, e.rate])
  ]), 'Event Summary');

  XLSX.writeFile(wb, `PYM_Attendance_${REC.meta.from}_to_${REC.meta.to}.xlsx`);
}

/* ---------- Wiring ---------- */
document.addEventListener('DOMContentLoaded', async () => {
  const page = recEl('page-records');
  if (!page) return;

  // Sidebar nav: title + load (app.js handles the page switch itself)
  const nav = document.querySelector('.nav-item[data-page="records"]');
  if (nav) nav.addEventListener('click', () => {
    document.querySelector('.page-title').textContent = 'Attendance Records';
    document.querySelector('.page-subtitle').textContent = 'Filter, review and export attendance to Excel';
    window.loadRecordsPage();
  });

  recEl('recCatChips').addEventListener('click', (e) => {
    const chip = e.target.closest('.rec-chip');
    if (!chip) return;
    const id = chip.dataset.id;
    if (REC.selected.has(id)) REC.selected.delete(id); else REC.selected.add(id);
    renderRecChips();
  });
  recEl('recSelectAll').addEventListener('click', () => { REC.selected = new Set(REC.cats.map(c => String(c.id))); renderRecChips(); });
  recEl('recClearAll').addEventListener('click', () => { REC.selected = new Set(); renderRecChips(); });
  page.querySelectorAll('.rec-quick button').forEach(b => b.addEventListener('click', () => recSetRange(b.dataset.range)));
  recEl('recMemSearch').addEventListener('input', renderRecMembers);
  recEl('recMemList').addEventListener('change', (e) => {
    const cb = e.target.closest('input[type="checkbox"]');
    if (!cb) return;
    if (cb.checked) REC.memSel.add(cb.dataset.id); else REC.memSel.delete(cb.dataset.id);
    renderRecMembers();
  });
  recEl('recMemSelected').addEventListener('click', (e) => {
    const b = e.target.closest('[data-rm]');
    if (b) { REC.memSel.delete(b.dataset.rm); renderRecMembers(); }
  });
  const memReset = () => { REC.memSel = new Set(); recEl('recMemSearch').value = ''; renderRecMembers(); };
  recEl('recMemAll').addEventListener('click', memReset);
  recEl('recMemClear').addEventListener('click', memReset);
  recEl('recGenerateBtn').addEventListener('click', recGenerate);
  recEl('recDownloadBtn').addEventListener('click', recExport);
  recEl('recStatus').addEventListener('change', recApplyFilters);
  recEl('recSearch').addEventListener('input', recApplyFilters);
  page.querySelectorAll('.rec-tab').forEach(t => t.addEventListener('click', () => { REC.view = t.dataset.view; REC.page = 1; recRender(); }));
  recEl('recPager').addEventListener('click', (e) => {
    const b = e.target.closest('[data-pg]');
    if (!b || b.disabled) return;
    REC.page += b.dataset.pg === 'next' ? 1 : -1;
    recRender();
  });

  // Events and Groups/Slots now live in the Admin Panel for the Secretariat
  const s = await apiCall('check_session');
  if (s.success && s.data.role === 'secretariat') {
    document.querySelectorAll('.leader-only').forEach(el => { el.style.display = 'none'; });
  }
});
