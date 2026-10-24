<?php
// MQAP - PYM Attendance System — public landing page
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<link rel="icon" type="image/png" href="favicon.png"/>
<title>MQAP - PYM Attendance System</title>
<meta name="description" content="Parish Youth Ministry attendance system. View public attendance records or log in to mark attendance."/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{--ink:#070D26;--navy:#0F1F5C;--sapphire:#2563EB;--gold:#F5B82E;--gold-d:#D99A0B;--white:#fff;--muted:rgba(255,255,255,.72)}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{font-family:'Inter',sans-serif;background:var(--ink);color:#fff;overflow-x:hidden;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}

/* ---------- Hero ---------- */
.hero{position:relative;min-height:100vh;display:flex;flex-direction:column;overflow:hidden;isolation:isolate}
.wall{position:absolute;inset:-25%;z-index:-3;display:grid;grid-template-columns:repeat(9,1fr);gap:16px;transform:rotate(-14deg) skewX(8deg) scale(1.1);opacity:.9}
.tile{aspect-ratio:2/3;border-radius:10px;display:flex;flex-direction:column;justify-content:flex-end;padding:14px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:15px;line-height:1.15;letter-spacing:-.3px;position:relative;overflow:hidden;box-shadow:0 12px 30px rgba(0,0,0,.45);animation:float 9s ease-in-out infinite}
.tile i{position:absolute;top:14px;right:14px;font-size:22px;opacity:.5}
.tile small{display:block;font:600 10px 'Inter',sans-serif;letter-spacing:1.2px;text-transform:uppercase;opacity:.7;margin-top:4px}
.tile::after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.14),transparent 45%)}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.shade{position:absolute;inset:0;z-index:-2;background:
  radial-gradient(ellipse at 50% 42%,rgba(7,13,38,.55) 0%,rgba(7,13,38,.86) 62%,rgba(7,13,38,.97) 100%),
  linear-gradient(180deg,rgba(7,13,38,.78) 0%,rgba(7,13,38,.2) 40%,var(--ink) 100%)}

nav{display:flex;align-items:center;justify-content:space-between;padding:26px max(24px,6vw)}
.brand{display:flex;align-items:center;gap:12px}
.brand-icon{width:46px;height:46px;display:grid;place-items:center;flex-shrink:0}.brand-icon img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 4px 10px rgba(0,0,0,.4))}
.brand b{font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;letter-spacing:1px;display:block;line-height:1}
.brand span{font-size:9.5px;font-weight:700;letter-spacing:3px;color:var(--gold);text-transform:uppercase}
.nav-login{background:var(--gold);color:var(--ink);font-weight:800;font-size:14px;padding:10px 20px;border-radius:8px;transition:.2s}
.nav-login:hover{background:#fff;transform:translateY(-1px)}

.hero-body{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:10px max(20px,6vw) 190px;max-width:980px;margin:0 auto}
.hero-logo{width:clamp(130px,18vw,190px);height:auto;margin-bottom:20px;filter:drop-shadow(0 14px 34px rgba(0,0,0,.55))}
.pill{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(245,184,46,.4);background:rgba(245,184,46,.1);color:var(--gold);font-size:12px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;padding:7px 16px;border-radius:99px;margin-bottom:26px;backdrop-filter:blur(6px)}
.pill i{font-size:11px}
h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:clamp(38px,7vw,76px);line-height:1.04;letter-spacing:-2.2px;text-shadow:0 6px 40px rgba(0,0,0,.6)}
h1 em{font-style:normal;background:linear-gradient(120deg,var(--gold),#FFE29A 50%,var(--gold));-webkit-background-clip:text;background-clip:text;color:transparent}
.tag{font-size:clamp(16px,2.2vw,22px);font-weight:600;margin-top:22px;color:#fff}
.sub{font-size:clamp(14px,1.6vw,17px);color:var(--muted);margin-top:12px;max-width:620px;line-height:1.6}
.cta{display:flex;gap:14px;margin-top:38px;flex-wrap:wrap;justify-content:center}
.btn{display:inline-flex;align-items:center;gap:12px;font-size:clamp(15px,1.7vw,19px);font-weight:800;padding:17px 34px;border-radius:10px;transition:all .22s cubic-bezier(.2,.8,.2,1);border:2px solid transparent}
.btn i{transition:transform .22s}
.btn:hover i.fa-chevron-right{transform:translateX(5px)}
.btn-primary{background:linear-gradient(135deg,var(--sapphire),#1D4ED8);box-shadow:0 14px 40px rgba(37,99,235,.5)}
.btn-primary:hover{transform:translateY(-3px);box-shadow:0 20px 50px rgba(37,99,235,.65)}
.btn-ghost{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.35);backdrop-filter:blur(8px)}
.btn-ghost:hover{background:#fff;color:var(--ink);transform:translateY(-3px)}
.note{margin-top:22px;font-size:12.5px;color:rgba(255,255,255,.55);display:flex;align-items:center;gap:8px}

/* curved glow like a stage horizon */
.curve{position:absolute;left:50%;bottom:-80px;width:240%;height:150px;transform:translateX(-50%);border-radius:50% 50% 0 0/100% 100% 0 0;border-top:3px solid transparent;background:linear-gradient(var(--ink),var(--ink)) padding-box,linear-gradient(90deg,transparent 5%,var(--gold) 30%,#fff 50%,var(--sapphire) 70%,transparent 95%) border-box;box-shadow:0 -22px 70px rgba(37,99,235,.35),0 -6px 40px rgba(245,184,46,.18)}

/* ---------- Sections ---------- */
section.features{background:var(--ink);padding:70px max(24px,6vw) 40px}
.features h2{font-family:'Plus Jakarta Sans',sans-serif;font-size:clamp(26px,4vw,38px);letter-spacing:-1px;text-align:center}
.features>p{text-align:center;color:var(--muted);margin:10px auto 40px;max-width:560px;line-height:1.6}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;max-width:1100px;margin:0 auto}
.card{background:linear-gradient(160deg,rgba(255,255,255,.07),rgba(255,255,255,.02));border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:26px;transition:.25s}
.card:hover{transform:translateY(-5px);border-color:rgba(245,184,46,.45);background:linear-gradient(160deg,rgba(245,184,46,.1),rgba(255,255,255,.02))}
.card .ic{width:48px;height:48px;border-radius:12px;display:grid;place-items:center;font-size:20px;margin-bottom:18px;background:rgba(37,99,235,.2);color:#7FA8FF}
.card:nth-child(2) .ic{background:rgba(245,184,46,.18);color:var(--gold)}
.card:nth-child(3) .ic{background:rgba(5,150,105,.2);color:#34D399}
.card h3{font-size:17px;margin-bottom:8px}
.card p{font-size:13.5px;line-height:1.65;color:var(--muted)}
footer{padding:44px 24px 36px;text-align:center;color:rgba(255,255,255,.45);font-size:12.5px;line-height:1.8}
footer b{color:var(--gold);font-weight:700}

@media (max-width:760px){.curve{bottom:-80px}.hero-body{padding-bottom:160px}.wall{grid-template-columns:repeat(5,1fr);inset:-40%}.btn{width:100%;justify-content:center}.cta{width:100%;max-width:380px}.hero-body{padding-bottom:90px}}
@media (prefers-reduced-motion:reduce){.tile{animation:none}}
</style>
</head>
<body>

<header class="hero">
  <div class="wall" id="wall" aria-hidden="true"></div>
  <div class="shade"></div>

  <nav>
    <a href="home.php" class="brand">
      <div class="brand-icon"><img src="logo.png" alt="PYM logo"/></div>
      <div><b>MQAP</b><span>PYM Attendance</span></div>
    </a>
    <a href="login.php" class="nav-login">Log In</a>
  </nav>

  <div class="hero-body">
    <img src="logo.png" alt="PYM logo" class="hero-logo"/>
    <div class="pill"><i class="fas fa-church"></i> Parish Youth Ministry</div>
    <h1>MQAP – <em>PYM</em><br/>Attendance System</h1>
    <p class="tag">Every youth. Every service. Every event.</p>
    <p class="sub">A transparent, real-time record of attendance for Normal Serve and special events — open for everyone to view.</p>

    <div class="cta">
      <a href="login.php" class="btn btn-primary"><i class="fas fa-right-to-bracket"></i> Login <i class="fas fa-chevron-right"></i></a>
    </div>
  </div>
  <div class="curve"></div>
</header>

<section class="features">
  <h2>Built for the ministry</h2>
  <p>Simple for leaders to mark, clear for everyone to see.</p>
  <div class="grid">
    <div class="card"><div class="ic"><i class="fas fa-eye"></i></div><h3>Open records</h3><p>Anyone can check attendance by date, category or member — no login required.</p></div>
    <div class="card"><div class="ic"><i class="fas fa-user-check"></i></div><h3>Leaders mark attendance</h3><p>Slot leaders and the secretariat mark present, absent or excused with a reason.</p></div>
    <div class="card"><div class="ic"><i class="fas fa-file-excel"></i></div><h3>Excel export</h3><p>The secretariat can download accurate, filtered reports in one click.</p></div>
  </div>
</section>

<footer>
  <b>MQAP – PYM Attendance System</b><br/>
  Parish Youth Ministry · Built for service
</footer>

<script>
(function(){
  const items=[
    ['Sunday 7:00 AM','Normal Serve','fa-sun'],['Special Event','Youth Activity','fa-star'],['Sunday 9:00 AM','Normal Serve','fa-church'],
    ['Recollection','Retreat','fa-dove'],['Saturday 5:00 PM','Normal Serve','fa-moon'],['Bible Study','Fellowship','fa-book-bible'],
    ['Sunday 5:00 PM','Normal Serve','fa-cross'],['Youth Day','Celebration','fa-people-group'],['Sunday 7:00 PM','Normal Serve','fa-candle-holder'],
    ['Outreach','Service','fa-hand-holding-heart'],['Choir Night','Ministry','fa-music'],['Sunday 11:00 AM','Normal Serve','fa-bell']
  ];
  const pal=[['#1E3A8A','#2563EB'],['#7C5A06','#F5B82E'],['#0F1F5C','#4F46E5'],['#064E3B','#059669'],['#4C1D95','#7C3AED'],['#7F1D1D','#DC2626'],['#0C4A6E','#0EA5E9'],['#78350F','#D97706']];
  const wall=document.getElementById('wall');
  let h='';
  for(let i=0;i<54;i++){
    const it=items[(i*5+Math.floor(i/9))%items.length], c=pal[(i*3+Math.floor(i/9)*2)%pal.length];
    h+=`<div class="tile" style="background:linear-gradient(155deg,${c[1]},${c[0]});animation-delay:${(i%9)*-1.1}s;margin-top:${(i%9)%2?26:0}px"><i class="fas ${it[2]}"></i>${it[0]}<small>${it[1]}</small></div>`;
  }
  wall.innerHTML=h;
})();
</script>
</body>
</html>
