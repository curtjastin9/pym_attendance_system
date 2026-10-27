/* PYM — detailed Excel export (class-register style). Needs SheetJS (XLSX). Used by records.js and public.php */
(function () {
  const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const FM = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  const RNG = (a, b) => { const [fy, fm, fd] = String(a).split('-').map(Number), [ty, tm, td] = String(b).split('-').map(Number);
    return fy === ty ? `${FM[fm - 1]} ${fd} to ${FM[tm - 1]} ${td}, ${ty}` : `${FM[fm - 1]} ${fd}, ${fy} to ${FM[tm - 1]} ${td}, ${ty}`; };
  const D = d => { const p = String(d).split('-'); return p.length === 3 ? `${MON[+p[1] - 1]} ${+p[2]}, ${p[0]}` : d; };
  const SD = d => { const p = String(d).split('-'); return p.length === 3 ? `${MON[+p[1] - 1]} ${+p[2]}` : d; };
  const DOW = d => DAYS[new Date(d + 'T00:00:00').getDay()];
  const T = t => { if (!t) return ''; const [h, m] = String(t).split(':'); const H = +h; return `${H % 12 || 12}:${m} ${H >= 12 ? 'PM' : 'AM'}`; };
  const pct = (a, b) => (b > 0 ? Math.round((a / b) * 100) : 0);
  const MARK = { present: 'P', absent: 'A', excused: 'E' };
  const cap = s => s.charAt(0).toUpperCase() + s.slice(1);
  const SCH = { none: 'Non Scholar', shs: 'Senior High School Scholar', college: 'College Scholar' };
  const sch = v => SCH[v] || SCH.none;
  const MR = { P: 3, E: 2, A: 1 }; // when a member has 2 records on one date, Present > Excused > Absent
  const used = new Set();

  function sname(n) {
    const b = String(n).replace(/[\[\]:*?\/\\]/g, '-').trim().slice(0, 28) || 'Sheet';
    let s = b, i = 2;
    while (used.has(s.toLowerCase())) s = b.slice(0, 26) + ' ' + i++;
    used.add(s.toLowerCase());
    return s;
  }
  function mk(aoa, widths) {
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    if (!widths) {
      widths = [];
      aoa.forEach(r => r.forEach((v, i) => { widths[i] = Math.max(widths[i] || 8, Math.min(String(v == null ? '' : v).length + 2, 48)); }));
    }
    ws['!cols'] = widths.map(w => ({ wch: w }));
    return ws;
  }
  function stats(rows) {
    let p = 0, x = 0;
    const m = new Set(), e = new Set();
    rows.forEach(r => { if (r.status === 'present') p++; else if (r.status === 'excused') x++; m.add(r.member); e.add(r.date + '|' + r.category + '|' + r.event); });
    return { total: rows.length, present: p, absent: rows.length - p, excused: x, rate: pct(p, rows.length), members: m.size, events: e.size };
  }
  function group(rows, key, base) {
    const mp = new Map();
    rows.forEach(r => {
      const k = key(r);
      if (!mp.has(k)) mp.set(k, Object.assign(base(r), { present: 0, absent: 0, excused: 0, total: 0 }));
      const g = mp.get(k);
      g.total++;
      if (r.status === 'present') g.present++; else { g.absent++; if (r.status === 'excused') g.excused++; }
    });
    return [...mp.values()].map(g => Object.assign(g, { rate: pct(g.present, g.total) }));
  }
  const byTime = (a, b) => a.date.localeCompare(b.date) || String(a.time || '').localeCompare(String(b.time || '')) || String(a.event).localeCompare(String(b.event));

  /** o: { rows, from, to, categories[], cats[{name,is_system}], memberLabel, statusFilter, includeReason } */
  window.pymBuildWorkbook = function (o) {
    used.clear();
    const rows = o.rows, wb = XLSX.utils.book_new();
    const sys = new Set((o.cats || []).filter(c => Number(c.is_system) === 1).map(c => c.name));
    const s = stats(rows);

    /* ---- Summary ---- */
    const byCat = group(rows, r => r.category, r => ({ category: r.category }))
      .map(c => Object.assign(c, { events: new Set(rows.filter(r => r.category === c.category).map(r => r.date + '|' + r.event)).size }))
      .sort((a, b) => a.category.localeCompare(b.category));
    const bySch = ['none', 'shs', 'college'].map(k => {
      const g = stats(rows.filter(r => (r.scholar || 'none') === k));
      return [SCH[k], g.members, g.present, g.absent, g.excused, g.total, g.rate];
    }).filter(r => r[5] > 0);
    used.add('summary');
    XLSX.utils.book_append_sheet(wb, mk([
      ['PYM ATTENDANCE REGISTER'], [],
      ['Date range', RNG(o.from, o.to)], ['Categories', o.categories.join(', ')], ['Members', o.memberLabel || 'All members'], ['Scholar category', o.scholarLabel || 'All'],
      ['Status filter', o.statusFilter ? cap(o.statusFilter) : 'All'], ['Generated', new Date().toLocaleString('en-US')], [],
      ['Total records', s.total], ['Present', s.present], ['Absent (incl. excused)', s.absent], ['  of which Excused', s.excused],
      ['Attendance rate (%)', s.rate], ['Sessions / events', s.events], ['Members', s.members], [],
      ['LEGEND'], ['P', 'Present'], ['A', 'Absent'], ['E', 'Excused (reason in the "Excused Details" sheet)'], ['(blank)', 'No record for that member on that date'], [],
      ['BY CATEGORY'], ['Category', 'Sessions', 'Present', 'Absent (incl. Excused)', 'of which Excused', 'Total', 'Attendance (%)'],
      ...byCat.map(c => [c.category, c.events, c.present, c.absent, c.excused, c.total, c.rate]), [],
      ['BY SCHOLAR CATEGORY'], ['Scholar category', 'Members', 'Present', 'Absent (incl. Excused)', 'of which Excused', 'Total', 'Attendance (%)'],
      ...bySch, [],
      ['SHEETS IN THIS FILE'], ['Attendance Sheet: every member (names, grouped by slot) × every date, like a class register'], ['One register per slot / special-event category · Member Summary · Event Summary · Records (full list)'],
    ], [26, 28, 12, 22, 18, 10, 16]), 'Summary');

    /* ---- Master attendance sheet: names x dates, grouped by slot ---- */
    (function () {
      const colMap = new Map();
      rows.forEach(r => {
        const isSys = sys.has(r.category), k = isSys ? 'S|' + r.date : 'C|' + r.date + '|' + r.event;
        if (!colMap.has(k)) colMap.set(k, { k, date: r.date, sys: isSys, event: r.event });
      });
      const cols = [...colMap.values()].sort((a, b) => a.date.localeCompare(b.date) || ((b.sys ? 1 : 0) - (a.sys ? 1 : 0)) || String(a.event).localeCompare(String(b.event)));
      const idx = new Map(cols.map((c, i) => [c.k, i]));
      const mem = new Map(), gOrder = [];
      rows.forEach(r => {
        const isSys = sys.has(r.category), id = r.member_id != null ? r.member_id : r.member;
        if (!mem.has(id)) mem.set(id, { name: r.member, sch: r.scholar || 'none', home: '', ev: '', cells: Array(cols.length).fill(''), p: 0, a: 0, e: 0 });
        const m = mem.get(id);
        if (isSys) m.home = r.slot || '';
        if (!isSys && !m.ev) m.ev = r.slot || '';
        const ci = idx.get(isSys ? 'S|' + r.date : 'C|' + r.date + '|' + r.event), mk1 = MARK[r.status] || 'A';
        if ((MR[mk1] || 0) > (MR[m.cells[ci]] || 0)) m.cells[ci] = mk1;
      });
      const all = [...mem.values()];
      all.forEach(m => {
        m.p = m.cells.filter(c => c === 'P').length; m.e = m.cells.filter(c => c === 'E').length; m.a = m.cells.filter(c => c === 'A').length;
        m.group = m.home || m.ev || 'No slot';
      });
      rows.forEach(r => { const g = (sys.has(r.category) ? r.slot : '') || ''; if (g && !gOrder.includes(g)) gOrder.push(g); });
      all.forEach(m => { if (!gOrder.includes(m.group)) gOrder.push(m.group); });
      gOrder.sort((a, b) => (a === 'No slot') - (b === 'No slot'));
      const by = new Map();
      all.sort((a, b) => a.name.localeCompare(b.name)).forEach(m => { if (!by.has(m.group)) by.set(m.group, []); by.get(m.group).push(m); });
      const pad = n => Array(n).fill('');
      const aoa = [
        ['ATTENDANCE SHEET'],
        [`${RNG(o.from, o.to)} · ${o.categories.join(', ')} · ${all.length} member(s) · ${cols.length} date(s)`],
        ['P = Present   A = Absent   E = Excused   (blank = no record)'], [],
        ['#', 'Name', 'Slot', 'Scholar', ...cols.map(c => SD(c.date)), 'Present', 'Absent', 'Excused', 'Total', 'Rate %'],
        [...pad(4), ...cols.map(c => (c.sys ? DOW(c.date) : c.event)), ...pad(5)],
      ];
      let n = 0;
      gOrder.forEach(g => {
        const ms = by.get(g);
        if (!ms) return;
        aoa.push([], ['', g.toUpperCase() + '  (' + ms.length + ')']);
        ms.forEach(m => { n++; const t = m.p + m.a + m.e; aoa.push([n, m.name, m.group, sch(m.sch), ...m.cells, m.p, m.a, m.e, t, pct(m.p, t)]); });
      });
      aoa.push([]);
      const cnt = ch => cols.map((_, i) => all.filter(m => m.cells[i] === ch).length);
      const P = cnt('P'), A = cnt('A'), E = cnt('E');
      const foot = (l, arr) => aoa.push(['', l, '', '', ...arr, ...pad(5)]);
      foot('Present', P); foot('Absent', A); foot('Excused', E);
      foot('Attendance %', cols.map((_, i) => pct(P[i], P[i] + A[i] + E[i])));
      const w = [5, 34, 22, 24, ...cols.map(c => (c.sys ? 9 : Math.max(9, Math.min(String(c.event).length + 2, 22)))), 9, 9, 9, 8, 8];
      XLSX.utils.book_append_sheet(wb, mk(aoa, w), sname('Attendance Sheet'));
      wb.SheetNames.unshift(wb.SheetNames.pop()); // names first
    })();

    /* ---- Registers: one sheet per slot (Normal Serve) / per category (events) ---- */
    const groups = new Map();
    rows.forEach(r => {
      const isSys = sys.has(r.category);
      const k = isSys ? 'S|' + r.event : 'C|' + r.category;
      if (!groups.has(k)) groups.set(k, { title: isSys ? r.event : r.category, sys: isSys, category: r.category, rows: [] });
      groups.get(k).rows.push(r);
    });
    groups.forEach(g => {
      const sess = new Map();
      g.rows.forEach(r => { const k = r.date + '|' + r.event; if (!sess.has(k)) sess.set(k, { date: r.date, event: r.event, time: r.event_time }); });
      const cols = [...sess.values()].sort(byTime);
      const idx = new Map(cols.map((c, i) => [c.date + '|' + c.event, i]));
      const mem = new Map();
      g.rows.forEach(r => {
        const k = r.member_id != null ? r.member_id : r.member;
        if (!mem.has(k)) mem.set(k, { name: r.member, sch: r.scholar || 'none', slot: r.slot || '', cells: Array(cols.length).fill(''), p: 0, a: 0, e: 0 });
        const m = mem.get(k), i = idx.get(r.date + '|' + r.event);
        m.cells[i] = MARK[r.status] || 'A';
        if (r.status === 'present') m.p++; else if (r.status === 'excused') m.e++; else m.a++;
      });
      const list = [...mem.values()].sort((a, b) => a.name.localeCompare(b.name));
      const lead = g.sys ? ['#', 'Name', 'Scholar'] : ['#', 'Name', 'Slot', 'Scholar'];
      const L = lead.length, pad = n => Array(n).fill('');
      const aoa = [
        [g.title.toUpperCase()],
        [`${g.category} · ${RNG(o.from, o.to)} · ${list.length} member(s) · ${cols.length} session(s)`], [],
        [...lead, ...cols.map(c => SD(c.date)), 'Present', 'Absent', 'Excused', 'Total', 'Rate %'],
        [...pad(L), ...cols.map(c => (g.sys ? DOW(c.date) : c.event)), ...pad(5)],
        [...pad(L), ...cols.map(c => T(c.time)), ...pad(5)],
      ];
      list.forEach((m, i) => {
        const t = m.p + m.a + m.e;
        aoa.push([i + 1, m.name, ...(g.sys ? [] : [m.slot]), sch(m.sch), ...m.cells, m.p, m.a, m.e, t, pct(m.p, t)]);
      });
      aoa.push([]);
      const cnt = ch => cols.map((_, i) => list.filter(m => m.cells[i] === ch).length);
      const P = cnt('P'), A = cnt('A'), E = cnt('E');
      const foot = (label, arr) => aoa.push([...pad(L - 1), label, ...arr, ...pad(5)]);
      foot('Present', P); foot('Absent', A); foot('Excused', E);
      foot('Attendance %', cols.map((_, i) => pct(P[i], P[i] + A[i] + E[i])));
      const w = [5, 32, ...(g.sys ? [] : [20]), 24, ...cols.map(c => Math.max(9, Math.min((g.sys ? 9 : String(c.event).length + 2), 22))), 9, 9, 9, 8, 8];
      XLSX.utils.book_append_sheet(wb, mk(aoa, w), sname(g.title));
    });

    /* ---- Member Summary (with current missed-in-a-row) ---- */
    const last = new Map();
    [...rows].sort((a, b) => (b.date + (b.event_time || '')).localeCompare(a.date + (a.event_time || ''))).forEach(r => {
      const k = r.member_id != null ? r.member_id : r.member;
      if (!last.has(k)) last.set(k, { n: 0, done: false });
      const l = last.get(k);
      if (l.done) return;
      if (r.status === 'present') l.done = true; else l.n++;
    });
    const byMember = group(rows, r => (r.member_id != null ? r.member_id : r.member), r => ({ id: r.member_id != null ? r.member_id : r.member, member: r.member, slot: r.slot || '', sch: sch(r.scholar) }))
      .sort((a, b) => a.member.localeCompare(b.member));
    XLSX.utils.book_append_sheet(wb, mk([
      ['MEMBER SUMMARY'], [],
      ['#', 'Member', 'Slot', 'Scholar', 'Present', 'Absent', 'of which Excused', 'Total', 'Attendance (%)', 'Missed in a row (latest)'],
      ...byMember.map((m, i) => [i + 1, m.member, m.slot, m.sch, m.present, m.absent, m.excused, m.total, m.rate, (last.get(m.id) || { n: 0 }).n]),
    ], [5, 34, 20, 24, 10, 10, 18, 8, 15, 24]), sname('Member Summary'));

    /* ---- Event Summary ---- */
    const byEvent = group(rows, r => r.date + '|' + r.category + '|' + r.event, r => ({ date: r.date, category: r.category, event: r.event, time: r.event_time })).sort(byTime);
    XLSX.utils.book_append_sheet(wb, mk([
      ['EVENT / SESSION SUMMARY'], [],
      ['Date', 'Day', 'Category', 'Event', 'Time', 'Present', 'Absent (incl. Excused)', 'of which Excused', 'Total', 'Attendance (%)'],
      ...byEvent.map(e => [D(e.date), DOW(e.date), e.category, e.event, T(e.time), e.present, e.absent, e.excused, e.total, e.rate]),
    ], [14, 6, 18, 24, 11, 9, 22, 18, 8, 15]), sname('Event Summary'));

    /* ---- Records (full list) ---- */
    const sorted = [...rows].sort((a, b) => a.date.localeCompare(b.date) || String(a.event_time || '').localeCompare(String(b.event_time || '')) || a.member.localeCompare(b.member));
    const head = ['Date', 'Day', 'Category', 'Event', 'Event Time', 'Member', 'Slot', 'Scholar', 'Status', 'Time In'];
    if (o.includeReason) head.push('Reason');
    XLSX.utils.book_append_sheet(wb, mk([
      head,
      ...sorted.map(r => [D(r.date), DOW(r.date), r.category, r.event, T(r.event_time), r.member, r.slot || '', sch(r.scholar), cap(r.status), T(r.time_in), ...(o.includeReason ? [r.reason || ''] : [])]),
    ]), sname('Records'));

    /* ---- Excused details ---- */
    if (o.includeReason) {
      const ex = sorted.filter(r => r.status === 'excused');
      if (ex.length) XLSX.utils.book_append_sheet(wb, mk([
        ['EXCUSED DETAILS'], [],
        ['Date', 'Day', 'Category', 'Event', 'Member', 'Slot', 'Reason'],
        ...ex.map(r => [D(r.date), DOW(r.date), r.category, r.event, r.member, r.slot || '', r.reason || '']),
      ], [14, 6, 18, 24, 32, 20, 50]), sname('Excused Details'));
    }
    wb.Workbook = { Views: [{ activeTab: 0 }] };
    return wb;
  };
})();
