/* PYM — Analytics page (Secretariat only). Needs app.js, records.js. */
(function () {
  const $ = id => document.getElementById(id);
  const css = `
  .an-top{display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,#92400E,#D97706 60%,#F59E0B);color:#fff;border-radius:var(--radius);padding:16px 20px;margin-bottom:14px}
  .an-top i{font-size:30px;opacity:.9}.an-top b{display:block;font-size:19px;font-family:'Plus Jakarta Sans',sans-serif}.an-top span{font-size:12.5px;opacity:.9}
  .an-top.blue{background:linear-gradient(135deg,#1E40AF,#2563EB 60%,#3B82F6)}
  .an-sec{margin-bottom:26px}`;
  const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  function top(rows) {
    const m = new Map();
    rows.forEach(r => {
      const k = r.member_id != null ? r.member_id : r.member;
      if (!m.has(k)) m.set(k, { member: r.member, slot: r.slot || '—', scholar: r.scholar, present: 0, total: 0 });
      const g = m.get(k); g.total++; if (r.status === 'present') g.present++;
    });
    return Array.from(m.values()).filter(x => x.present > 0)
      .sort((a, b) => b.present - a.present || (b.present / b.total) - (a.present / a.total) || a.member.localeCompare(b.member)).slice(0, 10);
  }

  function block(title, icon, cls, list, label) {
    const win = list[0];
    const head = win ? `<div class="an-top ${cls}"><i class="fas ${icon}"></i><div><b>${escapeHtml(win.member)}</b><span>${win.present} present out of ${win.total} · ${recPct(win.present, win.total)}%</span></div></div>` : '';
    const tbl = list.length ? `<div class="table-wrap"><table class="data-table"><thead><tr><th>#</th><th>Member</th><th>Slot</th><th>Scholar</th><th>Present</th><th>Total</th><th>Attendance</th></tr></thead><tbody>` +
      list.map((m, i) => `<tr><td>${i < 3 ? '<i class="fas fa-medal" style="color:' + ['#D99A0B', '#94A3B8', '#B45309'][i] + '"></i> ' : ''}${i + 1}</td><td><strong>${escapeHtml(m.member)}</strong></td><td>${escapeHtml(m.slot)}</td><td>${scholarBadge(m.scholar)}</td><td>${m.present}</td><td>${m.total}</td><td><span class="rec-rate ${recRateCls(recPct(m.present, m.total))}">${recPct(m.present, m.total)}%</span></td></tr>`).join('') +
      `</tbody></table></div>` : '<p class="empty-msg">No attendance in this range.</p>';
    return `<div class="an-sec"><div class="coy-section-title"><i class="fas ${icon}" style="color:var(--gold-mid)"></i> ${title}</div>${head}${tbl}</div>`;
  }

  async function run() {
    const from = $('anFrom').value, to = $('anTo').value;
    if (!from || !to) { alert('Please choose a start and end date.'); return; }
    if (from > to) { alert('The start date must be before the end date.'); return; }
    const btn = $('anGo'), o = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    const cats = await recApi('get_record_categories');
    if (!cats.success) { btn.disabled = false; btn.innerHTML = o; alert(cats.message); return; }
    const sysIds = cats.data.filter(c => Number(c.is_system) === 1).map(c => c.id);
    const allIds = cats.data.map(c => c.id);
    const [a, b] = await Promise.all([
      sysIds.length ? recApi('get_records', { from, to, category_ids: sysIds, member_ids: [] }) : Promise.resolve({ success: true, data: { rows: [] } }),
      recApi('get_records', { from, to, category_ids: allIds, member_ids: [] })
    ]);
    btn.disabled = false; btn.innerHTML = o;
    if (!a.success) { alert(a.message); return; }
    if (!b.success) { alert(b.message); return; }
    $('anRange').textContent = recRange(from, to);
    $('anResults').innerHTML =
      block('TOP SERVER AWARDEE (Normal Serve)', 'fa-award', '', top(a.data.rows)) +
      block('MOST ATTENDANCE IN GENERAL ACTIVITY (ALL ATTENDANCE)', 'fa-medal', 'blue', top(b.data.rows));
  }

  function setRange(k) {
    const n = new Date(); let f, t = n;
    if (k === 'month') { f = new Date(n.getFullYear(), n.getMonth(), 1); t = new Date(n.getFullYear(), n.getMonth() + 1, 0); }
    else if (k === 'lastmonth') { f = new Date(n.getFullYear(), n.getMonth() - 1, 1); t = new Date(n.getFullYear(), n.getMonth(), 0); }
    else if (k === '30') f = new Date(n.getTime() - 29 * 864e5);
    else if (k === '90') f = new Date(n.getTime() - 89 * 864e5);
    else { f = new Date(n.getFullYear(), 0, 1); t = new Date(n.getFullYear(), 11, 31); }
    if (f < new Date(2026, 9, 1)) f = new Date(2026, 9, 1);
    if (t < f) t = f;
    $('anFrom').value = recYmd(f); $('anTo').value = recYmd(t);
  }

  document.addEventListener('DOMContentLoaded', () => {
    const page = $('page-analytics');
    if (!page) return;
    page.innerHTML = `
      <div class="card rec-card">
        <div class="card-header"><div>
          <h3><i class="fas fa-chart-line" style="color:var(--gold-mid);margin-right:8px"></i>Analytics</h3>
          <p class="card-sub" id="anRange">Pick a date range</p></div></div>
        <div class="rec-filters">
          <div class="form-field"><label>From</label><input type="date" id="anFrom" min="2026-10-01"/></div>
          <div class="form-field"><label>To</label><input type="date" id="anTo" min="2026-10-01"/></div>
        </div>
        <div class="rec-quick">
          <button type="button" data-r="month">This month</button><button type="button" data-r="lastmonth">Last month</button>
          <button type="button" data-r="30">Last 30 days</button><button type="button" data-r="90">Last 90 days</button><button type="button" data-r="year">This year</button>
        </div>
        <div class="rec-actions"><button type="button" class="auth-btn small" id="anGo"><i class="fas fa-chart-line"></i> Show Analytics</button></div>
      </div>
      <div class="card" id="anCard"><div id="anResults"><p class="empty-msg">Press Show Analytics.</p></div></div>`;
    page.querySelectorAll('.rec-quick button').forEach(b => b.addEventListener('click', () => setRange(b.dataset.r)));
    $('anGo').addEventListener('click', run);
    const nav = document.querySelector('.nav-item[data-page="analytics"]');
    if (nav) nav.addEventListener('click', () => {
      document.querySelector('.page-title').textContent = 'Analytics';
      document.querySelector('.page-subtitle').textContent = 'Top attendance and server awardees';
      if (!$('anFrom').value) { setRange('month'); run(); }
    });
  });
})();
