<?php
// MQAP - PYM Attendance System — public landing page
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<meta name="theme-color" content="#070D26"/>
<link rel="icon" type="image/png" href="favicon.png"/>
<title>MQAP - PYM Attendance System</title>
<meta name="description" content="Parish Youth Ministry attendance system. View public attendance records or log in to mark attendance."/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{--ink:#070D26;--navy:#0F1F5C;--sapphire:#2563EB;--gold:#F5B82E;--gold-d:#D99A0B;--white:#fff;--muted:rgba(255,255,255,.72);--ease:cubic-bezier(.2,.8,.2,1)}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{font-family:'Inter',sans-serif;background:var(--ink);color:#fff;overflow-x:hidden;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}

/* ---------- Hero ---------- */
.hero{position:relative;min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;overflow:hidden;isolation:isolate}

/* Background wall: ONE moving layer (cheap), tiles themselves are static */
.parallax{position:absolute;inset:0;z-index:-3;transition:transform .5s var(--ease)}
.wall-move{position:absolute;inset:0;animation:drift 46s ease-in-out infinite alternate}
.wall{position:absolute;inset:-25%;display:grid;grid-template-columns:repeat(9,1fr);gap:16px;transform:rotate(-14deg) skewX(8deg) scale(1.1);opacity:.9;animation:wallIn 1.4s var(--ease) both}
.tile{aspect-ratio:2/3;border-radius:10px;display:flex;flex-direction:column;justify-content:flex-end;padding:14px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:15px;line-height:1.15;letter-spacing:-.3px;position:relative;overflow:hidden;box-shadow:0 12px 30px rgba(0,0,0,.45)}
.tile i{position:absolute;top:14px;right:14px;font-size:22px;opacity:.5}
.tile small{display:block;font:600 10px 'Inter',sans-serif;letter-spacing:1.2px;text-transform:uppercase;opacity:.7;margin-top:4px}
.tile::after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.14),transparent 45%)}
@keyframes drift{from{transform:translate3d(-1.5%,-1%,0)}to{transform:translate3d(1.5%,1.2%,0)}}
@keyframes wallIn{from{opacity:0;transform:rotate(-14deg) skewX(8deg) scale(1.2)}to{opacity:.9;transform:rotate(-14deg) skewX(8deg) scale(1.1)}}

.shade{position:absolute;inset:0;z-index:-2;background:
  radial-gradient(ellipse at 50% 42%,rgba(7,13,38,.55) 0%,rgba(7,13,38,.86) 62%,rgba(7,13,38,.97) 100%),
  linear-gradient(180deg,rgba(7,13,38,.78) 0%,rgba(7,13,38,.2) 40%,var(--ink) 100%)}

/* Soft glowing orbs (transform/opacity only) */
.orb{position:absolute;z-index:-1;border-radius:50%;pointer-events:none;opacity:.55}
.orb.o1{width:520px;height:520px;left:-160px;top:-120px;background:radial-gradient(circle,rgba(245,184,46,.22),transparent 68%);animation:orbA 14s ease-in-out infinite alternate}
.orb.o2{width:620px;height:620px;right:-200px;bottom:-60px;background:radial-gradient(circle,rgba(37,99,235,.28),transparent 68%);animation:orbB 17s ease-in-out infinite alternate}
@keyframes orbA{from{transform:translate3d(0,0,0) scale(1)}to{transform:translate3d(90px,60px,0) scale(1.15)}}
@keyframes orbB{from{transform:translate3d(0,0,0) scale(1.05)}to{transform:translate3d(-80px,-50px,0) scale(.92)}}

/* Floating sparkles */
.sparks{position:absolute;inset:0;z-index:-1;pointer-events:none;overflow:hidden}
.sparks i{position:absolute;bottom:-12px;width:5px;height:5px;border-radius:50%;background:var(--gold);opacity:0;animation:rise linear infinite}
@keyframes rise{0%{transform:translate3d(0,0,0) scale(.6);opacity:0}12%{opacity:.8}85%{opacity:.5}100%{transform:translate3d(var(--dx,20px),-105vh,0) scale(1.2);opacity:0}}

nav{display:flex;align-items:center;justify-content:space-between;padding:26px max(24px,6vw);animation:fadeDown .8s var(--ease) both}
.brand{display:flex;align-items:center;gap:12px}
.brand-icon{width:46px;height:46px;display:grid;place-items:center;flex-shrink:0}.brand-icon img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 4px 10px rgba(0,0,0,.4))}
.brand b{font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;letter-spacing:1px;display:block;line-height:1}
.brand span{font-size:9.5px;font-weight:700;letter-spacing:3px;color:var(--gold);text-transform:uppercase}
.nav-login{position:relative;overflow:hidden;background:var(--gold);color:var(--ink);font-weight:800;font-size:14px;padding:10px 20px;border-radius:8px;transition:transform .25s var(--ease),background .25s,box-shadow .25s}
.nav-login:hover{background:#fff;transform:translateY(-2px);box-shadow:0 10px 24px -8px rgba(245,184,46,.6)}
@keyframes fadeDown{from{opacity:0;transform:translate3d(0,-16px,0)}to{opacity:1;transform:none}}

.hero-body{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:10px max(20px,6vw) 190px;max-width:980px;margin:0 auto}

/* Staggered entrance */
.rise{animation:rise-in .9s var(--ease) both;animation-delay:var(--d,0s)}
@keyframes rise-in{from{opacity:0;transform:translate3d(0,26px,0)}to{opacity:1;transform:none}}

.hero-logo-wrap{position:relative;margin-bottom:20px}
.hero-logo-wrap::before{content:'';position:absolute;left:50%;top:50%;width:150%;height:150%;transform:translate(-50%,-50%);background:radial-gradient(circle,rgba(245,184,46,.35),transparent 65%);opacity:.6;animation:glow 4.5s ease-in-out infinite}
.hero-logo{position:relative;display:block;width:clamp(130px,18vw,190px);height:auto;filter:drop-shadow(0 14px 34px rgba(0,0,0,.55));animation:bob 6s ease-in-out infinite}
@keyframes bob{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-10px,0)}}
@keyframes glow{0%,100%{opacity:.35;transform:translate(-50%,-50%) scale(.92)}50%{opacity:.75;transform:translate(-50%,-50%) scale(1.06)}}

.pill{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(245,184,46,.4);background:rgba(245,184,46,.1);color:var(--gold);font-size:12px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;padding:7px 16px;border-radius:99px;margin-bottom:26px}
.pill i{font-size:11px}
h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:clamp(38px,7vw,76px);line-height:1.04;letter-spacing:-2.2px;text-shadow:0 6px 40px rgba(0,0,0,.6)}
h1 em{font-style:normal;background:linear-gradient(110deg,var(--gold) 20%,#FFF3C4 45%,var(--gold) 70%);background-size:220% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:shine 5s ease-in-out infinite}
@keyframes shine{0%,20%{background-position:120% 0}70%,100%{background-position:-60% 0}}
.tag{font-size:clamp(16px,2.2vw,22px);font-weight:600;margin-top:22px;color:#fff}
.sub{font-size:clamp(14px,1.6vw,17px);color:var(--muted);margin-top:12px;max-width:620px;line-height:1.6}
.cta{display:flex;gap:14px;margin-top:38px;flex-wrap:wrap;justify-content:center}
.btn{position:relative;display:inline-flex;align-items:center;gap:12px;font-size:clamp(15px,1.7vw,19px);font-weight:800;padding:17px 34px;border-radius:10px;transition:transform .25s var(--ease),box-shadow .25s var(--ease);border:2px solid transparent;overflow:visible}
.btn i{transition:transform .25s var(--ease)}
.btn:hover i.fa-chevron-right{transform:translateX(5px)}
.btn-primary{background:linear-gradient(135deg,var(--sapphire),#1D4ED8);box-shadow:0 14px 40px rgba(37,99,235,.5)}
.btn-primary:hover{transform:translateY(-3px);box-shadow:0 20px 50px rgba(37,99,235,.65)}
.btn-primary:active{transform:translateY(-1px) scale(.98)}
/* soft pulsing ring (opacity/transform only) */
.btn-primary::before{content:'';position:absolute;inset:-2px;border-radius:12px;border:2px solid rgba(96,165,250,.7);opacity:0;animation:ring 2.8s ease-out infinite 1.6s;pointer-events:none}
@keyframes ring{0%{opacity:.8;transform:scale(1)}100%{opacity:0;transform:scale(1.18,1.4)}}
/* light sweep */
.btn-primary::after{content:'';position:absolute;top:0;bottom:0;left:0;width:40%;border-radius:10px;background:linear-gradient(105deg,transparent,rgba(255,255,255,.28),transparent);transform:translateX(-140%) skewX(-18deg);animation:sweep 4.2s ease-in-out infinite 2.2s;pointer-events:none}
@keyframes sweep{0%{transform:translateX(-140%) skewX(-18deg)}35%,100%{transform:translateX(340%) skewX(-18deg)}}

/* Stage horizon: static shape + pulsing glow overlay */
.curve{position:absolute;left:50%;bottom:-80px;width:240%;height:150px;transform:translateX(-50%);border-radius:50% 50% 0 0/100% 100% 0 0;border-top:3px solid transparent;background:linear-gradient(var(--ink),var(--ink)) padding-box,linear-gradient(90deg,transparent 5%,var(--gold) 30%,#fff 50%,var(--sapphire) 70%,transparent 95%) border-box;animation:curveIn 1.2s var(--ease) .3s both}
.curve::after{content:'';position:absolute;inset:-3px 0 0;border-radius:inherit;box-shadow:0 -22px 70px rgba(37,99,235,.45),0 -6px 40px rgba(245,184,46,.28);opacity:.35;animation:pulse 5s ease-in-out infinite}
@keyframes curveIn{from{opacity:0;transform:translate(-50%,60px)}to{opacity:1;transform:translateX(-50%)}}
@keyframes pulse{0%,100%{opacity:.3}50%{opacity:1}}

@media (max-width:760px){
  .curve{bottom:-80px}
  .wall{grid-template-columns:repeat(5,1fr);inset:-40%}
  .btn{width:100%;justify-content:center}
  .cta{width:100%;max-width:380px}
  .hero-body{padding-bottom:110px}
  .orb{opacity:.4}
}
@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation:none!important;transition:none!important}
  .rise,nav,.curve,.wall{opacity:1}
}
/* Pause everything when the tab is hidden (saves battery / CPU) */
.paused *,.paused *::before,.paused *::after{animation-play-state:paused!important}
</style>
</head>
<body>

<header class="hero" id="hero">
  <div class="parallax" id="parallax" aria-hidden="true"><div class="wall-move"><div class="wall" id="wall"></div></div></div>
  <div class="shade"></div>
  <div class="orb o1" aria-hidden="true"></div>
  <div class="orb o2" aria-hidden="true"></div>
  <div class="sparks" id="sparks" aria-hidden="true"></div>

  <nav>
    <a href="home.php" class="brand">
      <div class="brand-icon"><img src="logo.png" alt="PYM logo"/></div>
      <div><b>MQAP</b><span>PYM Attendance</span></div>
    </a>
    <a href="login.php" class="nav-login">Log In</a>
  </nav>

  <div class="hero-body">
    <div class="hero-logo-wrap rise" style="--d:.15s"><img src="logo.png" alt="PYM logo" class="hero-logo"/></div>
    <div class="pill rise" style="--d:.3s"><i class="fas fa-church"></i> Parish Youth Ministry</div>
    <h1 class="rise" style="--d:.42s">MQAP – <em>PYM</em><br/>Attendance System</h1>
    <p class="tag rise" style="--d:.58s">Every youth. Every service. Every event.</p>
    <p class="sub rise" style="--d:.7s">A transparent, real-time record of attendance for Normal Serve and special events — open for everyone to view.</p>

    <div class="cta rise" style="--d:.85s">
      <a href="login.php" class="btn btn-primary"><i class="fas fa-right-to-bracket"></i> Login <i class="fas fa-chevron-right"></i></a>
    </div>
  </div>
  <div class="curve"></div>
</header>

<script>
(function(){
  const items=[
    ['Sunday 7:00 AM','Normal Serve','fa-sun'],['Special Event','Youth Activity','fa-star'],['Sunday 9:00 AM','Normal Serve','fa-church'],
    ['Recollection','Retreat','fa-dove'],['Saturday 5:00 PM','Normal Serve','fa-moon'],['Bible Study','Fellowship','fa-book-bible'],
    ['Sunday 5:00 PM','Normal Serve','fa-cross'],['Youth Day','Celebration','fa-people-group'],['Sunday 7:00 PM','Normal Serve','fa-candle-holder'],
    ['Outreach','Service','fa-hand-holding-heart'],['Choir Night','Ministry','fa-music'],['Sunday 11:00 AM','Normal Serve','fa-bell']
  ];
  const pal=[['#1E3A8A','#2563EB'],['#7C5A06','#F5B82E'],['#0F1F5C','#4F46E5'],['#064E3B','#059669'],['#4C1D95','#7C3AED'],['#7F1D1D','#DC2626'],['#0C4A6E','#0EA5E9'],['#78350F','#D97706']];
  const small=window.matchMedia('(max-width:760px)').matches;
  const n=small?25:45; // fewer tiles on phones = less work
  let h='';
  for(let i=0;i<n;i++){
    const it=items[(i*5+Math.floor(i/9))%items.length], c=pal[(i*3+Math.floor(i/9)*2)%pal.length];
    h+=`<div class="tile" style="background:linear-gradient(155deg,${c[1]},${c[0]});margin-top:${(i%9)%2?26:0}px"><i class="fas ${it[2]}"></i>${it[0]}<small>${it[1]}</small></div>`;
  }
  document.getElementById('wall').innerHTML=h;

  // Sparkles (a handful of tiny dots)
  const sp=document.getElementById('sparks');
  let s='';
  for(let i=0;i<(small?7:12);i++){
    s+=`<i style="left:${Math.round(Math.random()*100)}%;--dx:${Math.round(Math.random()*80-40)}px;animation-duration:${(9+Math.random()*9).toFixed(1)}s;animation-delay:${(Math.random()*-14).toFixed(1)}s;width:${3+Math.round(Math.random()*3)}px;height:${3+Math.round(Math.random()*3)}px"></i>`;
  }
  sp.innerHTML=s;

  // Gentle mouse parallax (desktop only, one rAF-throttled transform)
  const reduce=window.matchMedia('(prefers-reduced-motion:reduce)').matches;
  if(!reduce && window.matchMedia('(hover:hover) and (pointer:fine)').matches){
    const el=document.getElementById('parallax');
    let x=0,y=0,ticking=false;
    window.addEventListener('mousemove',e=>{
      x=(e.clientX/innerWidth-.5)*-26; y=(e.clientY/innerHeight-.5)*-18;
      if(!ticking){ticking=true;requestAnimationFrame(()=>{el.style.transform=`translate3d(${x}px,${y}px,0)`;ticking=false;});}
    },{passive:true});
  }

  // Pause animations when the tab is hidden
  document.addEventListener('visibilitychange',()=>document.documentElement.classList.toggle('paused',document.hidden));
})();
</script>
</body>
</html>
