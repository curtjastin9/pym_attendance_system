<?php
// MQAP - PYM Attendance System — public attendance records (no login required)
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<link rel="icon" type="image/png" href="favicon.png"/>
<title>Attendance Records – MQAP · PYM Attendance System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{--ink:#070D26;--navy:#0F1F5C;--sapphire:#2563EB;--sap-pale:#EAF1FF;--gold:#F5B82E;--gold-d:#D99A0B;--emerald:#059669;--em-pale:#E6F7F0;--crimson:#DC2626;--cr-pale:#FDECEC;--amber:#B45309;--am-pale:#FEF3C7;
--text:#0F172A;--soft:#64748B;--faint:#94A3B8;--border:#E2E8F0;--surface:#fff;--surface2:#F8FAFC}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;color:var(--text);min-height:100vh;-webkit-font-smoothing:antialiased;
 background:radial-gradient(1000px 500px at 80% -10%,rgba(37,99,235,.35),transparent 60%),radial-gradient(800px 400px at 0% 0%,rgba(245,184,46,.14),transparent 55%),var(--ink)}
a{text-decoration:none;color:inherit}
button,input,select{font-family:inherit}
button{cursor:pointer}
.hidden{display:none!important}

.top{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:20px max(20px,5vw);position:sticky;top:0;z-index:20;background:rgba(7,13,38,.8);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,.08)}
.brand{display:flex;align-items:center;gap:12px;color:#fff}
.brand-icon{width:46px;height:46px;display:grid;place-items:center;flex-shrink:0}.brand-icon img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 4px 10px rgba(0,0,0,.4))}
.brand b{font-family:'Plus Jakarta Sans',sans-serif;font-size:19px;letter-spacing:1px;display:block;line-height:1}
.brand span{font-size:9px;font-weight:700;letter-spacing:2.6px;color:var(--gold);text-transform:uppercase}
.top-links{display:flex;gap:10px}
.tl{font-size:13px;font-weight:700;padding:9px 16px;border-radius:8px;color:#fff;border:1.5px solid rgba(255,255,255,.25);transition:.2s}
.tl:hover{background:rgba(255,255,255,.12)}
.tl.gold{background:var(--gold);color:var(--ink);border-color:var(--gold)}
.tl.gold:hover{background:#fff}

.wrap{max-width:1180px;margin:0 auto;padding:34px max(16px,4vw) 60px}
.title{color:#fff;margin-bottom:24px}
.title h1{font-family:'Plus Jakarta Sans',sans-serif;font-size:clamp(26px,4vw,38px);letter-spacing:-1px}
.title p{color:rgba(255,255,255,.65);font-size:14px;margin-top:6px}
.pubtag{display:inline-flex;align-items:center;gap:7px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#34D399;background:rgba(5,150,105,.15);border:1px solid rgba(52,211,153,.3);padding:5px 12px;border-radius:99px;margin-bottom:14px}

.card{background:var(--surface);border-radius:18px;padding:26px;box-shadow:0 20px 60px rgba(0,0,0,.35);margin-bottom:22px;border-top:4px solid var(--gold)}
.card.plain{border-top:none}
.filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;background:var(--surface2);border:1.5px solid var(--border);border-radius:14px;padding:16px;margin-bottom:14px}
label.l{display:block;font-size:11px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:var(--soft);margin-bottom:6px}
input[type=date],select,.inp{width:100%;border:1.5px solid var(--border);background:#fff;border-radius:10px;padding:11px 13px;font-size:14px;outline:none;color:var(--text)}
input:focus,select:focus{border-color:var(--sapphire);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.quick{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:18px}
.quick button,.link{border:1.5px solid var(--border);background:#fff;font-size:12px;font-weight:700;color:#334155;padding:7px 13px;border-radius:99px;transition:.18s}
.quick button:hover{background:var(--ink);color:#fff;border-color:var(--ink)}
.row-label{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:8px;font-size:11px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:var(--soft)}
.row-label small{font-weight:600;text-transform:none;letter-spacing:0;color:var(--faint);font-size:12px}
.lk{border:none;background:none;color:var(--sapphire);font-size:12px;font-weight:700;padding:0 0 0 10px;text-transform:none;letter-spacing:0}
.lk:hover{text-decoration:underline}
.chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
.chip{display:inline-flex;align-items:center;gap:7px;border:1.5px solid var(--border);background:#fff;color:#334155;font-size:13px;font-weight:700;padding:9px 15px;border-radius:99px;transition:.18s}
.chip i{font-size:10px;width:0;opacity:0;transition:.18s}
.chip.on{background:var(--sap-pale);border-color:#93B4FF;color:#1D4ED8}
.chip.on i{width:10px;opacity:1}
.chip em{font-style:normal;font-size:9px;font-weight:800;background:var(--am-pale);color:var(--amber);padding:2px 7px;border-radius:99px;text-transform:uppercase}

.mem{border:1.5px solid var(--border);border-radius:14px;padding:12px;margin-bottom:20px}
.mem-top{display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap}
.sbox{position:relative;flex:1;min-width:200px}
.sbox i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--faint);font-size:13px}
.sbox input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:10px 12px 10px 34px;font-size:14px;outline:none}
.sbox input:focus{border-color:var(--sapphire);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.sel{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}.sel:empty{display:none}
.tag{display:inline-flex;align-items:center;gap:6px;background:var(--sap-pale);color:#1D4ED8;border:1.5px solid #93B4FF;border-radius:99px;padding:4px 6px 4px 12px;font-size:12px;font-weight:700}
.tag button{border:none;background:rgba(0,0,0,.08);width:18px;height:18px;border-radius:50%;font-size:11px;line-height:1}
.mlist{max-height:200px;overflow:auto;border:1px solid var(--border);border-radius:10px}
.mi{display:flex;align-items:center;gap:10px;padding:9px 13px;font-size:13.5px;cursor:pointer;border-bottom:1px solid var(--border)}
.mi:last-child{border-bottom:none}.mi:hover{background:var(--surface2)}
.mi small{margin-left:auto;color:var(--faint);font-weight:600;font-size:12px}
.mi input{accent-color:var(--sapphire);width:16px;height:16px}
.empty{padding:16px;color:var(--faint);font-size:13px;text-align:center}

.go{display:inline-flex;align-items:center;gap:9px;border:none;background:linear-gradient(135deg,var(--sapphire),#1D4ED8);color:#fff;font-size:15px;font-weight:800;padding:14px 28px;border-radius:11px;box-shadow:0 10px 28px rgba(37,99,235,.4);transition:.2s}
.go:hover{transform:translateY(-2px)}.go:disabled{opacity:.6;cursor:wait;transform:none}

.rhead h2{font-family:'Plus Jakarta Sans',sans-serif;font-size:21px;letter-spacing:-.4px}
.rhead p{font-size:13px;color:var(--soft);margin-top:4px;line-height:1.5}
.rhead p b{color:var(--text)}
.xl{display:inline-flex;align-items:center;gap:9px;border:none;background:linear-gradient(135deg,#059669,#047857);color:#fff;font-size:14px;font-weight:800;padding:12px 22px;border-radius:11px;box-shadow:0 10px 26px rgba(5,150,105,.38);transition:.2s}.xl:hover{transform:translateY(-2px)}.xl:disabled{opacity:.45;cursor:not-allowed;transform:none}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:20px 0}
.st{border:1.5px solid var(--border);border-top-width:4px;border-radius:14px;padding:15px 17px}
.st b{display:block;font-family:'Plus Jakarta Sans',sans-serif;font-size:29px;letter-spacing:-1px;line-height:1.1}
.st span{font-size:10.5px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:var(--soft)}
.st.b{border-top-color:#60A5FA}.st.g{border-top-color:#10B981}.st.r{border-top-color:#F87171}.st.a{border-top-color:#F59E0B}.st.y{border-top-color:var(--gold)}
.bar{height:10px;border-radius:99px;background:var(--cr-pale);overflow:hidden;margin:0 0 22px}
.bar i{display:block;height:100%;background:linear-gradient(90deg,#10B981,#34D399);border-radius:99px;transition:width .5s}
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.tabs{display:flex;gap:4px;background:var(--surface2);border:1px solid var(--border);border-radius:11px;padding:4px}
.tab{border:none;background:none;padding:9px 15px;font-size:13px;font-weight:700;color:var(--soft);border-radius:8px}
.tab.on{background:var(--ink);color:#fff}
.tw{overflow-x:auto;border:1.5px solid var(--border);border-radius:14px}
table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:620px}
th{background:var(--surface2);text-align:left;font-size:10.5px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:var(--soft);padding:12px 15px;white-space:nowrap}
td{padding:12px 15px;border-top:1px solid var(--border);white-space:nowrap}
tbody tr:hover{background:var(--surface2)}
.pill{display:inline-block;border-radius:99px;padding:3px 11px;font-size:11px;font-weight:800;text-transform:capitalize}
.pill.present{background:var(--em-pale);color:var(--emerald)}.pill.absent{background:var(--cr-pale);color:var(--crimson)}.pill.excused{background:var(--am-pale);color:var(--amber)}
.rate{display:inline-block;border-radius:99px;padding:3px 11px;font-size:11.5px;font-weight:800}
.rate.hi{background:var(--em-pale);color:var(--emerald)}.rate.mid{background:var(--am-pale);color:var(--amber)}.rate.lo{background:var(--cr-pale);color:var(--crimson)}
.pager{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:14px;font-size:13px;color:var(--soft)}
.pager div{display:flex;gap:8px;align-items:center}
.pager button{min-width:36px;height:36px;border:1.5px solid var(--border);background:#fff;border-radius:9px;font-weight:700}
.pager button:hover:not(:disabled){background:var(--ink);color:#fff;border-color:var(--ink)}.pager button:disabled{opacity:.4;cursor:not-allowed}
.noteline{font-size:12px;color:var(--faint);margin-top:14px;display:flex;gap:7px;align-items:flex-start;line-height:1.5}
.sch-pill{display:inline-block;border-radius:99px;padding:3px 10px;font-size:11px;font-weight:800;white-space:nowrap}.sch-none{background:#F1F5F9;color:#475569}.sch-shs{background:#DBEAFE;color:#1D4ED8}.sch-college{background:#EDE9FE;color:#6D28D9}
footer{text-align:center;color:rgba(255,255,255,.4);font-size:12.5px;padding:10px 20px 40px}
@media(max-width:640px){.card{padding:18px}.tabs{width:100%}.tab{flex:1;padding:9px 6px}.top-links .tl:not(.gold){display:none}}
</style>
</head>
<body>

<div class="top">
  <a href="home.php" class="brand"><div class="brand-icon"><img src="logo.png" alt="PYM logo"/></div><div><b>MQAP</b><span>PYM Attendance</span></div></a>
  <div class="top-links"><a class="tl" href="home.php"><i class="fas fa-house"></i> Home</a><a class="tl gold" href="login.php">Log In</a></div>
</div>

<main class="wrap">
  <div class="title">
    <div class="pubtag"><i class="fas fa-lock-open"></i> Public records · No login needed</div>
    <h1>Attendance Records</h1>
    <p>Pick a date range and categories, or search a specific youth member to see their attendance.</p>
  </div>

  <div class="card">
    <div class="filters">
      <div><label class="l">From</label><input type="date" id="from"/></div>
      <div><label class="l">To</label><input type="date" id="to"/></div>
      <div><label class="l">Status</label>
        <select id="status"><option value="">All statuses</option><option value="present">Present only</option><option value="absent">Absent only (includes excused)</option><option value="excused">Excused only</option></select></div>
    </div>
    <div class="quick" id="quick">
      <button data-r="month">This month</button><button data-r="lastmonth">Last month</button><button data-r="30">Last 30 days</button><button data-r="90">Last 90 days</button><button data-r="year">This year</button>
    </div>

    <div class="row-label"><span>Event categories <button class="lk" id="catAll">Select all</button><button class="lk" id="catNone">Clear</button></span><small id="catCount">—</small></div>
    <div class="chips" id="chips"><span class="empty">Loading categories...</span></div>

    <div class="row-label"><span>Scholar category <button class="lk" id="schAll">Select all</button><button class="lk" id="schNone">Clear</button></span><small id="schCount">All categories</small></div>
    <div class="chips" id="schChips"></div>

    <div class="row-label"><span>Members <button class="lk" id="memAll">All members</button></span><small id="memCount">All members</small></div>
    <div class="mem">
      <div class="mem-top"><div class="sbox"><i class="fas fa-search"></i><input type="text" id="memSearch" placeholder="Search a member's name..." autocomplete="off"/></div><button class="lk" id="memClear">Clear selection</button></div>
      <div class="sel" id="memSel"></div>
      <div class="mlist" id="memList"><div class="empty">Loading members...</div></div>
    </div>

    <button class="go" id="go"><i class="fas fa-magnifying-glass-chart"></i> Show Records</button>
  </div>

  <div class="card plain hidden" id="results">
    <div class="rhead" style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:flex-start"><div><h2>Results</h2><p id="summary">—</p></div><button class="xl" id="dl"><i class="fas fa-download"></i> Download Excel</button></div>
    <div class="stats">
      <div class="st b"><b id="sTotal">0</b><span>Records</span></div>
      <div class="st g"><b id="sPresent">0</b><span>Present</span></div>
      <div class="st r"><b id="sAbsent">0</b><span>Absent (incl. excused)</span></div>
      <div class="st a"><b id="sExcused">0</b><span>Of which excused</span></div>
      <div class="st y"><b id="sRate">0%</b><span>Attendance rate</span></div>
    </div>
    <div class="bar" title="Attendance rate"><i id="bar" style="width:0"></i></div>

    <div class="toolbar">
      <div class="tabs">
        <button class="tab on" data-v="records">Records</button><button class="tab" data-v="members">Member Summary</button><button class="tab" data-v="events">Event Summary</button>
      </div>
      <div class="sbox" style="flex:0 1 260px"><i class="fas fa-search"></i><input type="text" id="q" placeholder="Search name or event..." autocomplete="off"/></div>
    </div>
    <div class="tw"><table><thead id="thead"></thead><tbody id="tbody"></tbody></table></div>
    <div class="pager" id="pager"></div>
    <p class="noteline"><i class="fas fa-circle-info"></i><span>Absent includes excused. Attendance rate = present ÷ all recorded sessions. Reasons for excused absences are kept private.</span></p>
  </div>
</main>
<footer>MQAP – PYM Attendance System · Parish Youth Ministry</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="export.js?v=4"></script>
<script>
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const MONTHS=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const fmtDate=d=>{const p=String(d).split('-');return p.length===3?`${MONTHS[+p[1]-1]} ${+p[2]}, ${p[0]}`:d};
const fmtTime=t=>{if(!t)return '—';const [h,m]=String(t).split(':');const H=+h;return `${H%12||12}:${m} ${H>=12?'PM':'AM'}`};
const ymd=d=>d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
const pct=(a,b)=>b>0?Math.round(a/b*100):0;
const cls=v=>v>=80?'hi':v>=50?'mid':'lo';
const SCH={none:'Non Scholar',shs:'Senior High School Scholar',college:'College Scholar'};
const schLabel=k=>SCH[k]||SCH.none;
const schPill=k=>{k=SCH[k]?k:'none';return `<span class="sch-pill sch-${k}">${SCH[k]}</span>`};
function drawSch(){
  $('schChips').innerHTML=['none','shs','college'].map(k=>`<button class="chip ${S.sch.has(k)?'on':''}" data-k="${k}"><i class="fas fa-check"></i>${SCH[k]}</button>`).join('');
  $('schCount').textContent=S.sch.size===3?'All categories':`${S.sch.size} of 3 selected`;
}

const S={sch:new Set(['none','shs','college']),cats:[],catSel:new Set(),members:[],memSel:new Set(),rows:[],f:[],view:'records',page:1,per:50,meta:null};

async function api(action,payload){
  try{const r=await fetch('api_public.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload||{})});return await r.json();}
  catch(e){return{success:false,message:'Network error. Please check your connection.'}}
}

function setRange(k){
  const n=new Date();let f,t=n;
  if(k==='month'){f=new Date(n.getFullYear(),n.getMonth(),1);t=new Date(n.getFullYear(),n.getMonth()+1,0)}
  else if(k==='lastmonth'){f=new Date(n.getFullYear(),n.getMonth()-1,1);t=new Date(n.getFullYear(),n.getMonth(),0)}
  else if(k==='30')f=new Date(n-29*864e5);
  else if(k==='90')f=new Date(n-89*864e5);
  else{f=new Date(n.getFullYear(),0,1);t=new Date(n.getFullYear(),11,31)}
  if(f<new Date(2026,9,1))f=new Date(2026,9,1);if(t<f)t=f;
  $('from').value=ymd(f);$('to').value=ymd(t);
}

function drawCats(){
  $('chips').innerHTML=S.cats.length?S.cats.map(c=>`<button class="chip ${S.catSel.has(String(c.id))?'on':''}" data-id="${c.id}"><i class="fas fa-check"></i>${esc(c.name)}${+c.is_system?'<em>Default</em>':''}</button>`).join(''):'<span class="empty">No categories yet.</span>';
  $('catCount').textContent=`${S.catSel.size} of ${S.cats.length} selected`;
}
function drawMembers(){
  const q=$('memSearch').value.trim().toLowerCase();
  const list=S.members.filter(m=>!q||m.full_name.toLowerCase().includes(q));
  $('memList').innerHTML=list.length?list.map(m=>`<label class="mi"><input type="checkbox" data-id="${m.id}" ${S.memSel.has(String(m.id))?'checked':''}/><span>${esc(m.full_name)}</span><small>${esc(m.slot||'No slot')}</small></label>`).join(''):'<div class="empty">No members found.</div>';
  const by={};S.members.forEach(m=>by[String(m.id)]=m);
  $('memSel').innerHTML=[...S.memSel].map(id=>`<span class="tag">${esc(by[id]?by[id].full_name:id)}<button data-rm="${id}">&times;</button></span>`).join('');
  $('memCount').textContent=S.memSel.size?`${S.memSel.size} member(s) selected`:'All members';
}

/* ---------- math ---------- */
function stats(rows){
  let p=0,x=0;const m=new Set(),e=new Set();
  rows.forEach(r=>{if(r.status==='present')p++;else if(r.status==='excused')x++;m.add(r.member);e.add(r.date+'|'+r.category+'|'+r.event)});
  const t=rows.length;return{total:t,present:p,absent:t-p,excused:x,rate:pct(p,t),members:m.size,events:e.size};
}
function group(rows,key,base){
  const mp=new Map();
  rows.forEach(r=>{const k=key(r);if(!mp.has(k))mp.set(k,Object.assign(base(r),{present:0,absent:0,excused:0,total:0}));const g=mp.get(k);g.total++;
    if(r.status==='present')g.present++;else{g.absent++;if(r.status==='excused')g.excused++}});
  return [...mp.values()].map(g=>Object.assign(g,{rate:pct(g.present,g.total)}));
}

async function generate(){
  const from=$('from').value,to=$('to').value;
  if(!from||!to){['from','to'].forEach(i=>{if(!$(i).value){$(i).style.borderColor='#DC2626';$(i).focus()}});return alert('Please choose a start date and an end date first.');}
  if(from>to)return alert('The start date must be before the end date.');
  if(!S.catSel.size)return alert('Select at least one category.');
  if(!S.sch.size)return alert('Select at least one scholar category.');
  const b=$('go'),o=b.innerHTML;b.disabled=true;b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Loading...';
  const res=await api('get_records',{from,to,category_ids:[...S.catSel],member_ids:[...S.memSel],scholar_types:[...S.sch]});
  b.disabled=false;b.innerHTML=o;
  if(!res.success)return alert(res.message);
  S.rows=res.data.rows;
  S.meta={from,to,cats:res.data.categories,scholarLabel:S.sch.size===3?'All':['none','shs','college'].filter(k=>S.sch.has(k)).map(schLabel).join(', ')};
  $('results').classList.remove('hidden');
  applyFilters();
  $('results').scrollIntoView({behavior:'smooth'});
}
function applyFilters(){
  const st=$('status').value,q=$('q').value.trim().toLowerCase();
  S.f=S.rows.filter(r=>{
    if(st==='absent'&&r.status==='present')return false;
    if(st&&st!=='absent'&&r.status!==st)return false;
    if(q&&!(`${r.member} ${r.event} ${r.category} ${r.slot||''} ${schLabel(r.scholar)}`).toLowerCase().includes(q))return false;
    return true;});
  S.page=1;render();
}
function render(){
  const s=stats(S.f);
  $('sTotal').textContent=s.total.toLocaleString();$('sPresent').textContent=s.present.toLocaleString();
  $('sAbsent').textContent=s.absent.toLocaleString();$('sExcused').textContent=s.excused.toLocaleString();
  $('dl').disabled=!s.total;
  $('sRate').textContent=s.rate+'%';$('bar').style.width=s.rate+'%';
  $('summary').innerHTML=`<b>${fmtDate(S.meta.from)}</b> to <b>${fmtDate(S.meta.to)}</b> · ${esc(S.meta.cats.join(', '))} · ${s.events} event(s) · ${s.members} member(s)`;
  document.querySelectorAll('.tab').forEach(t=>t.classList.toggle('on',t.dataset.v===S.view));

  let head,rows;
  if(S.view==='members'){
    head=['Member','Slot','Scholar','Present','Absent','Excused','Total','Attendance'];
    rows=group(S.f,r=>r.member,r=>({member:r.member,slot:r.slot||'—',scholar:r.scholar})).sort((a,b)=>a.member.localeCompare(b.member))
      .map(m=>[esc(m.member),esc(m.slot),schPill(m.scholar),m.present,m.absent,m.excused,m.total,`<span class="rate ${cls(m.rate)}">${m.rate}%</span>`]);
  }else if(S.view==='events'){
    head=['Date','Category','Event','Time','Present','Absent','Excused','Total','Attendance'];
    rows=group(S.f,r=>r.date+'|'+r.category+'|'+r.event,r=>({date:r.date,category:r.category,event:r.event,time:r.event_time}))
      .map(e=>[fmtDate(e.date),esc(e.category),esc(e.event),fmtTime(e.time),e.present,e.absent,e.excused,e.total,`<span class="rate ${cls(e.rate)}">${e.rate}%</span>`]);
  }else{
    head=['Date','Category','Event','Member','Slot','Scholar','Status','Time In'];
    rows=S.f.map(r=>[fmtDate(r.date),esc(r.category),esc(r.event),esc(r.member),esc(r.slot||'—'),schPill(r.scholar),`<span class="pill ${r.status}">${r.status}</span>`,fmtTime(r.time_in)]);
  }
  const pages=Math.max(1,Math.ceil(rows.length/S.per));if(S.page>pages)S.page=pages;
  const slice=rows.slice((S.page-1)*S.per,S.page*S.per);
  $('thead').innerHTML='<tr>'+head.map(h=>`<th>${h}</th>`).join('')+'</tr>';
  $('tbody').innerHTML=slice.length?slice.map(r=>'<tr>'+r.map(c=>`<td>${c}</td>`).join('')+'</tr>').join(''):`<tr><td colspan="${head.length}"><div class="empty" style="padding:40px">No attendance records match your filters.</div></td></tr>`;
  const a=rows.length?(S.page-1)*S.per+1:0,z=Math.min(S.page*S.per,rows.length);
  $('pager').innerHTML=`<span>Showing ${a}–${z} of ${rows.length.toLocaleString()}</span><div><button data-pg="-1" ${S.page<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button><span>Page ${S.page} / ${pages}</span><button data-pg="1" ${S.page>=pages?'disabled':''}><i class="fas fa-chevron-right"></i></button></div>`;
}

/* ---------- Excel download ---------- */
function sheet(aoa){
  const ws=XLSX.utils.aoa_to_sheet(aoa),w=[];
  aoa.forEach(r=>r.forEach((v,i)=>{w[i]=Math.max(w[i]||8,Math.min(String(v??'').length+2,48))}));
  ws['!cols']=w.map(x=>({wch:x}));return ws;
}
function download(){
  if(!S.f.length)return alert('There is nothing to download.');
  if(typeof XLSX==='undefined'||typeof pymBuildWorkbook==='undefined')return alert('The Excel library could not load. Check your connection and refresh.');
  const memberLabel=S.memSel.size?S.members.filter(m=>S.memSel.has(String(m.id))).map(m=>m.full_name).join(', '):'All members';
  const wb=pymBuildWorkbook({rows:S.f,from:S.meta.from,to:S.meta.to,categories:S.meta.cats,cats:S.cats,memberLabel,scholarLabel:S.meta.scholarLabel,statusFilter:$('status').value,includeReason:false});
  XLSX.writeFile(wb,`PYM_Attendance_${S.meta.from}_to_${S.meta.to}.xlsx`);
}
$('dl').onclick=download;

/* ---------- wiring ---------- */
['from','to'].forEach(i=>$(i).addEventListener('input',()=>{$(i).style.borderColor=''}));
$('quick').addEventListener('click',e=>{const b=e.target.closest('button');if(b)setRange(b.dataset.r)});
$('chips').addEventListener('click',e=>{const c=e.target.closest('.chip');if(!c)return;const id=c.dataset.id;S.catSel.has(id)?S.catSel.delete(id):S.catSel.add(id);drawCats()});
$('catAll').onclick=()=>{S.catSel=new Set(S.cats.map(c=>String(c.id)));drawCats()};
$('catNone').onclick=()=>{S.catSel=new Set();drawCats()};
$('schChips').addEventListener('click',e=>{const c=e.target.closest('.chip');if(!c)return;const k=c.dataset.k;S.sch.has(k)?S.sch.delete(k):S.sch.add(k);drawSch()});
$('schAll').onclick=()=>{S.sch=new Set(['none','shs','college']);drawSch()};
$('schNone').onclick=()=>{S.sch=new Set();drawSch()};
$('memSearch').addEventListener('input',drawMembers);
$('memList').addEventListener('change',e=>{const c=e.target.closest('input');if(!c)return;c.checked?S.memSel.add(c.dataset.id):S.memSel.delete(c.dataset.id);drawMembers()});
$('memSel').addEventListener('click',e=>{const b=e.target.closest('[data-rm]');if(b){S.memSel.delete(b.dataset.rm);drawMembers()}});
const resetMem=()=>{S.memSel=new Set();$('memSearch').value='';drawMembers()};
$('memAll').onclick=resetMem;$('memClear').onclick=resetMem;
$('go').onclick=generate;
$('status').addEventListener('change',()=>S.rows.length&&applyFilters());
$('q').addEventListener('input',()=>S.rows.length&&applyFilters());
document.querySelector('.tabs').addEventListener('click',e=>{const t=e.target.closest('.tab');if(t){S.view=t.dataset.v;S.page=1;render()}});
$('pager').addEventListener('click',e=>{const b=e.target.closest('[data-pg]');if(b&&!b.disabled){S.page+=+b.dataset.pg;render()}});

(async()=>{
  $('from').min=$('to').min='2026-10-01';
  const [c,m]=await Promise.all([api('get_record_categories'),api('get_record_members')]);
  if(c.success){S.cats=c.data;S.catSel=new Set(c.data.map(x=>String(x.id)))}
  drawCats();drawSch();
  if(m.success){S.members=m.data}else{$('memList').innerHTML=`<div class="empty">${esc(m.message)}</div>`}
  if(m.success)drawMembers();
})();
</script>
</body>
</html>
