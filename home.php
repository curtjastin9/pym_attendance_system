<?php
// MQAP - PYM Attendance System — public landing page (single-screen, interactive)
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<meta name="theme-color" content="#070D26"/>
<link rel="icon" type="image/png" href="favicon.png"/>
<title>MQAP - PYM Attendance System</title>
<meta name="description" content="Parish Youth Ministry attendance system. Log in to mark and review attendance."/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{--ink:#070D26;--sapphire:#2563EB;--gold:#F5B82E;--muted:rgba(255,255,255,.72);--ease:cubic-bezier(.2,.8,.2,1);--mx:50%;--my:50%}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden;overscroll-behavior:none}
body{font-family:'Inter',sans-serif;background:var(--ink);color:#fff;-webkit-font-smoothing:antialiased;-webkit-tap-highlight-color:transparent}
a{text-decoration:none;color:inherit}

/* ---------- One-screen hero ---------- */
.hero{position:fixed;inset:0;height:100vh;height:100dvh;display:flex;flex-direction:column;overflow:hidden;isolation:isolate}

/* Wall of tiles (single moving layer) */
.parallax{position:absolute;inset:0;z-index:-5;will-change:transform;transition:transform .6s var(--ease)}
.wall-move{position:absolute;inset:0;animation:drift 50s ease-in-out infinite alternate;will-change:transform}
.wall{position:absolute;inset:-25%;display:grid;grid-template-columns:repeat(9,1fr);gap:16px;transform:rotate(-14deg) skewX(8deg) scale(1.1);opacity:.85;animation:wallIn 1.6s var(--ease) both}
.tile{aspect-ratio:2/3;border-radius:12px;display:flex;flex-direction:column;justify-content:flex-end;padding:14px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:15px;line-height:1.15;letter-spacing:-.3px;position:relative;overflow:hidden;box-shadow:0 12px 30px rgba(0,0,0,.45)}
.tile i{position:absolute;top:14px;right:14px;font-size:22px;opacity:.5}
.tile small{display:block;font:600 10px 'Inter',sans-serif;letter-spacing:1.2px;text-transform:uppercase;opacity:.7;margin-top:4px}
.tile::after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.14),transparent 45%)}
@keyframes drift{from{transform:translate3d(-1.5%,-1%,0)}to{transform:translate3d(1.5%,1.2%,0)}}
@keyframes wallIn{from{opacity:0;transform:rotate(-14deg) skewX(8deg) scale(1.25)}to{opacity:.85;transform:rotate(-14deg) skewX(8deg) scale(1.1)}}

/* Dark shade that is "lit" by the mouse (spotlight reveals the wall) */
.shade{position:absolute;inset:0;z-index:-4;background:
  radial-gradient(480px circle at var(--mx) var(--my),rgba(7,13,38,0) 0%,rgba(7,13,38,.55) 55%,rgba(7,13,38,.9) 100%),
  radial-gradient(ellipse at 50% 45%,rgba(7,13,38,.7) 0%,rgba(7,13,38,.9) 70%),
  linear-gradient(180deg,rgba(7,13,38,.7),rgba(7,13,38,.4) 45%,rgba(7,13,38,.95))}
.glow{position:absolute;z-index:-3;inset:0;pointer-events:none;background:radial-gradient(360px circle at var(--mx) var(--my),rgba(245,184,46,.14),rgba(37,99,235,.10) 45%,transparent 70%);mix-blend-mode:screen}

/* Orbs */
.orb{position:absolute;z-index:-3;border-radius:50%;pointer-events:none;opacity:.55;will-change:transform}
.orb.o1{width:520px;height:520px;left:-160px;top:-120px;background:radial-gradient(circle,rgba(245,184,46,.22),transparent 68%);animation:orbA 14s ease-in-out infinite alternate}
.orb.o2{width:620px;height:620px;right:-200px;bottom:-160px;background:radial-gradient(circle,rgba(37,99,235,.3),transparent 68%);animation:orbB 17s ease-in-out infinite alternate}
@keyframes orbA{from{transform:translate3d(0,0,0) scale(1)}to{transform:translate3d(90px,60px,0) scale(1.15)}}
@keyframes orbB{from{transform:translate3d(0,0,0) scale(1.05)}to{transform:translate3d(-80px,-50px,0) scale(.92)}}

/* Interactive particle canvas */
#fx{position:absolute;inset:0;z-index:-2;width:100%;height:100%;pointer-events:none}

/* Vignette + film grain for a polished look */
.vig{position:absolute;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(ellipse at center,transparent 55%,rgba(3,6,20,.65) 100%)}

/* ---------- Nav ---------- */
nav{display:flex;align-items:center;justify-content:space-between;padding:clamp(16px,3.2vh,28px) max(22px,5vw);animation:fadeDown .9s var(--ease) both}
.brand{display:flex;align-items:center;gap:12px}
.brand-icon{width:44px;height:44px;display:grid;place-items:center;flex-shrink:0}
.brand-icon img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 4px 10px rgba(0,0,0,.4))}
.brand b{font-family:'Plus Jakarta Sans',sans-serif;font-size:21px;letter-spacing:1px;display:block;line-height:1}
.brand span{font-size:9.5px;font-weight:700;letter-spacing:3px;color:var(--gold);text-transform:uppercase}
.nav-login{position:relative;overflow:hidden;background:var(--gold);color:var(--ink);font-weight:800;font-size:14px;padding:10px 22px;border-radius:10px;transition:background .25s,box-shadow .25s}
.nav-login:hover{background:#fff;box-shadow:0 10px 26px -8px rgba(245,184,46,.7)}
@keyframes fadeDown{from{opacity:0;transform:translate3d(0,-16px,0)}to{opacity:1;transform:none}}

/* ---------- Hero content (always fits one screen) ---------- */
.hero-body{flex:1;min-height:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:0 max(20px,6vw);max-width:980px;margin:0 auto;width:100%;perspective:900px}
.rise{animation:riseIn 1s var(--ease) both;animation-delay:var(--d,0s)}
@keyframes riseIn{from{opacity:0;transform:translate3d(0,28px,0);filter:blur(6px)}to{opacity:1;transform:none;filter:blur(0)}}

.hero-logo-wrap{position:relative;margin-bottom:clamp(8px,2vh,20px);transform-style:preserve-3d;will-change:transform;transition:transform .25s var(--ease)}
.hero-logo-wrap::before{content:'';position:absolute;left:50%;top:50%;width:160%;height:160%;transform:translate(-50%,-50%);background:radial-gradient(circle,rgba(245,184,46,.38),transparent 65%);opacity:.6;animation:glowP 4.5s ease-in-out infinite;pointer-events:none}
.hero-logo{position:relative;display:block;width:clamp(86px,17vh,180px);height:auto;filter:drop-shadow(0 14px 34px rgba(0,0,0,.55));animation:bob 6s ease-in-out infinite}
@keyframes bob{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-10px,0)}}
@keyframes glowP{0%,100%{opacity:.35;transform:translate(-50%,-50%) scale(.92)}50%{opacity:.75;transform:translate(-50%,-50%) scale(1.06)}}

.pill{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(245,184,46,.4);background:rgba(245,184,46,.1);color:var(--gold);font-size:12px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;padding:7px 16px;border-radius:99px;margin-bottom:clamp(12px,2.6vh,24px);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:clamp(32px,min(7vw,9.5vh),76px);line-height:1.04;letter-spacing:-2px;text-shadow:0 6px 40px rgba(0,0,0,.6)}
h1 em{font-style:normal;background:linear-gradient(110deg,var(--gold) 20%,#FFF3C4 45%,var(--gold) 70%);background-size:220% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:shine 5s ease-in-out infinite}
@keyframes shine{0%,20%{background-position:120% 0}70%,100%{background-position:-60% 0}}
.tag{font-size:clamp(15px,2.2vh + .6vw,22px);font-weight:600;margin-top:clamp(10px,2.4vh,22px)}
.sub{font-size:clamp(13px,1.5vw,16.5px);color:var(--muted);margin-top:clamp(8px,1.6vh,12px);max-width:600px;line-height:1.6}
.cta{display:flex;gap:14px;margin-top:clamp(20px,4.4vh,38px);justify-content:center}

.btn{position:relative;display:inline-flex;align-items:center;gap:12px;font-size:clamp(15px,1.7vw,18px);font-weight:800;padding:clamp(14px,2.2vh,17px) 34px;border-radius:12px;border:0;color:#fff;background:linear-gradient(135deg,var(--sapphire),#1D4ED8);box-shadow:0 14px 40px rgba(37,99,235,.5);transition:box-shadow .3s var(--ease),transform .3s var(--ease);will-change:transform}
.btn:hover{box-shadow:0 22px 56px rgba(37,99,235,.7)}
.btn i{transition:transform .25s var(--ease)}
.btn:hover i.fa-chevron-right{transform:translateX(5px)}
.btn::before{content:'';position:absolute;inset:-2px;border-radius:14px;border:2px solid rgba(96,165,250,.7);opacity:0;animation:ring 2.8s ease-out infinite 1.6s;pointer-events:none}
@keyframes ring{0%{opacity:.8;transform:scale(1)}100%{opacity:0;transform:scale(1.18,1.45)}}
.btn .sweep{position:absolute;inset:0;border-radius:12px;overflow:hidden;pointer-events:none}
.btn .sweep::after{content:'';position:absolute;top:0;bottom:0;left:0;width:40%;background:linear-gradient(105deg,transparent,rgba(255,255,255,.3),transparent);transform:translateX(-140%) skewX(-18deg);animation:sweep 4.2s ease-in-out infinite 2.2s}
@keyframes sweep{0%{transform:translateX(-140%) skewX(-18deg)}35%,100%{transform:translateX(340%) skewX(-18deg)}}

.foot{padding:0 max(20px,5vw) clamp(12px,2.4vh,22px);text-align:center;font-size:12px;letter-spacing:.4px;color:rgba(255,255,255,.4);animation:fadeUp 1s var(--ease) 1.1s both}
@keyframes fadeUp{from{opacity:0;transform:translate3d(0,10px,0)}to{opacity:1;transform:none}}

/* ---------- Custom cursor (desktop only) ---------- */
.cur-dot,.cur-ring{position:fixed;left:0;top:0;pointer-events:none;z-index:9999;border-radius:50%;opacity:0;will-change:transform}
.cur-dot{width:8px;height:8px;margin:-4px 0 0 -4px;background:var(--gold);box-shadow:0 0 14px 2px rgba(245,184,46,.9)}
.cur-ring{width:40px;height:40px;margin:-20px 0 0 -20px;border:1.5px solid rgba(255,255,255,.55);transition:width .3s var(--ease),height .3s var(--ease),margin .3s var(--ease),background .3s,border-color .3s}
.cur-ring.hov{width:68px;height:68px;margin:-34px 0 0 -34px;background:rgba(245,184,46,.12);border-color:var(--gold)}
.cur-ring.down{width:28px;height:28px;margin:-14px 0 0 -14px;background:rgba(245,184,46,.25)}
.has-cur .cur-dot,.has-cur .cur-ring{opacity:1}
.has-cur,.has-cur a,.has-cur button{cursor:none}
.ripple{position:fixed;z-index:9998;pointer-events:none;width:12px;height:12px;margin:-6px 0 0 -6px;border-radius:50%;border:2px solid var(--gold);animation:rip .8s var(--ease) forwards}
@keyframes rip{to{transform:scale(11);opacity:0}}

@media (max-width:760px){
  .wall{grid-template-columns:repeat(5,1fr);inset:-40%}
  .orb{opacity:.4}
  .cta{width:100%;max-width:380px}
  .btn{width:100%;justify-content:center}
}
@media (max-height:560px){.sub,.pill{display:none}}
@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation:none!important;transition:none!important}
  .rise,nav,.foot,.wall{opacity:1;filter:none;transform:none}
}
.paused *,.paused *::before,.paused *::after{animation-play-state:paused!important}
</style>
</head>
<body>

<header class="hero" id="hero">
  <div class="parallax" id="parallax" aria-hidden="true"><div class="wall-move"><div class="wall" id="wall"></div></div></div>
  <div class="shade" aria-hidden="true"></div>
  <div class="glow" aria-hidden="true"></div>
  <div class="orb o1" aria-hidden="true"></div>
  <div class="orb o2" aria-hidden="true"></div>
  <canvas id="fx" aria-hidden="true"></canvas>
  <div class="vig" aria-hidden="true"></div>

  <nav>
    <a href="home.php" class="brand">
      <div class="brand-icon"><img src="logo.png" alt="PYM logo"/></div>
      <div><b>MQAP</b><span>PYM Attendance</span></div>
    </a>
    <a href="login.php" class="nav-login" data-hover>Log In</a>
  </nav>

  <main class="hero-body">
    <div class="hero-logo-wrap rise" id="logoWrap" style="--d:.15s"><img src="logo.png" alt="PYM logo" class="hero-logo"/></div>
    <div class="pill rise" style="--d:.3s"><i class="fas fa-church"></i> Parish Youth Ministry</div>
    <h1 class="rise" style="--d:.42s">MQAP – <em>PYM</em><br/>Attendance System</h1>
    <p class="tag rise" style="--d:.58s">Every youth. Every service. Every event.</p>
    <p class="sub rise" style="--d:.7s">A transparent, real-time record of attendance for Normal Serve and special events.</p>
    <div class="cta rise" style="--d:.85s">
      <a href="login.php" class="btn" id="mainBtn" data-hover data-magnet><span class="sweep"></span><i class="fas fa-right-to-bracket"></i> Login <i class="fas fa-chevron-right"></i></a>
    </div>
  </main>

  <footer class="foot">Parish Youth Ministry · MQAP</footer>
</header>

<div class="cur-ring" id="curRing"></div>
<div class="cur-dot" id="curDot"></div>

<script>
(function(){
  const root=document.documentElement;
  const small=matchMedia('(max-width:760px)').matches;
  const reduce=matchMedia('(prefers-reduced-motion:reduce)').matches;
  const fine=matchMedia('(hover:hover) and (pointer:fine)').matches;

  /* ---- Tile wall ---- */
  const items=[['Sunday 7:00 AM','Normal Serve','fa-sun'],['Special Event','Youth Activity','fa-star'],['Sunday 9:00 AM','Normal Serve','fa-church'],['Recollection','Retreat','fa-dove'],['Saturday 5:00 PM','Normal Serve','fa-moon'],['Bible Study','Fellowship','fa-book-bible'],['Sunday 5:00 PM','Normal Serve','fa-cross'],['Youth Day','Celebration','fa-people-group'],['Sunday 7:00 PM','Normal Serve','fa-candle-holder'],['Outreach','Service','fa-hand-holding-heart'],['Choir Night','Ministry','fa-music'],['Sunday 11:00 AM','Normal Serve','fa-bell']];
  const pal=[['#1E3A8A','#2563EB'],['#7C5A06','#F5B82E'],['#0F1F5C','#4F46E5'],['#064E3B','#059669'],['#4C1D95','#7C3AED'],['#7F1D1D','#DC2626'],['#0C4A6E','#0EA5E9'],['#78350F','#D97706']];
  const n=small?25:45; let h='';
  for(let i=0;i<n;i++){
    const it=items[(i*5+Math.floor(i/9))%items.length], c=pal[(i*3+Math.floor(i/9)*2)%pal.length];
    h+=`<div class="tile" style="background:linear-gradient(155deg,${c[1]},${c[0]});margin-top:${(i%9)%2?26:0}px"><i class="fas ${it[2]}"></i>${it[0]}<small>${it[1]}</small></div>`;
  }
  document.getElementById('wall').innerHTML=h;

  /* ---- Shared pointer state (smoothed) ---- */
  const P={x:innerWidth/2,y:innerHeight/2,tx:innerWidth/2,ty:innerHeight/2,active:false};
  const hero=document.getElementById('hero'), par=document.getElementById('parallax'), logo=document.getElementById('logoWrap');
  const ring=document.getElementById('curRing'), dot=document.getElementById('curDot'), btn=document.getElementById('mainBtn');
  let rx=P.x, ry=P.y;

  function setTarget(x,y){P.tx=x;P.ty=y;P.active=true;}
  addEventListener('pointermove',e=>{
    setTarget(e.clientX,e.clientY);
    if(fine){ dot.style.transform=`translate3d(${e.clientX}px,${e.clientY}px,0)`; }
  },{passive:true});
  addEventListener('pointerleave',()=>{P.active=false;});
  addEventListener('touchstart',e=>{const t=e.touches[0];if(t)setTarget(t.clientX,t.clientY);},{passive:true});

  /* ---- Cursor: ring follows with easing, grows on interactive elements, click ripple ---- */
  if(fine && !reduce){
    root.classList.add('has-cur');
    document.querySelectorAll('[data-hover]').forEach(el=>{
      el.addEventListener('mouseenter',()=>ring.classList.add('hov'));
      el.addEventListener('mouseleave',()=>ring.classList.remove('hov'));
    });
    addEventListener('pointerdown',()=>ring.classList.add('down'));
    addEventListener('pointerup',()=>ring.classList.remove('down'));
  }
  addEventListener('click',e=>{
    if(reduce)return;
    const r=document.createElement('div'); r.className='ripple';
    r.style.left=e.clientX+'px'; r.style.top=e.clientY+'px';
    document.body.appendChild(r); setTimeout(()=>r.remove(),850);
  });

  /* ---- Magnetic button (pulls slightly toward the cursor) ---- */
  let mx=0,my=0,mtx=0,mty=0;
  function magnet(){
    if(!btn||!fine||reduce)return;
    const b=btn.getBoundingClientRect(), cx=b.left+b.width/2, cy=b.top+b.height/2;
    const dx=P.x-cx, dy=P.y-cy, d=Math.hypot(dx,dy), R=170;
    if(P.active && d<R){const f=(1-d/R);mtx=dx*.28*f;mty=dy*.38*f;} else {mtx=0;mty=0;}
    mx+=(mtx-mx)*.15; my+=(mty-my)*.15;
    btn.style.transform=`translate3d(${mx.toFixed(2)}px,${my.toFixed(2)}px,0)`;
  }

  /* ---- Particle field that reacts to the mouse ---- */
  const cv=document.getElementById('fx'), ctx=cv.getContext('2d');
  let W=0,H=0,dpr=1, parts=[];
  function resize(){
    dpr=Math.min(devicePixelRatio||1,2); W=innerWidth; H=innerHeight;
    cv.width=W*dpr; cv.height=H*dpr; ctx.setTransform(dpr,0,0,dpr,0,0);
    const count=Math.round(Math.min(small?34:80,(W*H)/(small?16000:20000)));
    parts=Array.from({length:count},()=>({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,r:Math.random()*1.6+.7,g:Math.random()<.45}));
  }
  resize(); addEventListener('resize',resize);

  function drawFX(){
    ctx.clearRect(0,0,W,H);
    const link=small?90:120, pr=small?120:180;
    for(let i=0;i<parts.length;i++){
      const p=parts[i];
      // gentle push away from pointer
      if(P.active){
        const dx=p.x-P.x, dy=p.y-P.y, d=Math.hypot(dx,dy);
        if(d<pr && d>0.1){const f=(1-d/pr)*.9; p.vx+=dx/d*f*.06; p.vy+=dy/d*f*.06;}
      }
      p.vx*=.985; p.vy*=.985;
      p.x+=p.vx+(Math.random()-.5)*.02; p.y+=p.vy+(Math.random()-.5)*.02;
      if(p.x<-10)p.x=W+10; else if(p.x>W+10)p.x=-10;
      if(p.y<-10)p.y=H+10; else if(p.y>H+10)p.y=-10;
      ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,6.2832);
      ctx.fillStyle=p.g?'rgba(245,184,46,.85)':'rgba(147,180,255,.75)'; ctx.fill();
      for(let j=i+1;j<parts.length;j++){
        const q=parts[j], dx=p.x-q.x, dy=p.y-q.y, d2=dx*dx+dy*dy;
        if(d2<link*link){
          const a=(1-Math.sqrt(d2)/link)*.28;
          ctx.strokeStyle=`rgba(147,180,255,${a})`; ctx.lineWidth=.8;
          ctx.beginPath(); ctx.moveTo(p.x,p.y); ctx.lineTo(q.x,q.y); ctx.stroke();
        }
      }
      // connect to pointer
      if(P.active){
        const dx=p.x-P.x, dy=p.y-P.y, d=Math.hypot(dx,dy);
        if(d<pr*1.2){ctx.strokeStyle=`rgba(245,184,46,${(1-d/(pr*1.2))*.5})`;ctx.lineWidth=.9;ctx.beginPath();ctx.moveTo(p.x,p.y);ctx.lineTo(P.x,P.y);ctx.stroke();}
      }
    }
  }

  /* ---- Single animation loop ---- */
  let running=true;
  function frame(){
    if(!running)return;
    P.x+=(P.tx-P.x)*.14; P.y+=(P.ty-P.y)*.14;
    rx+=(P.tx-rx)*.2; ry+=(P.ty-ry)*.2;
    // spotlight position
    hero.style.setProperty('--mx',P.x.toFixed(1)+'px'); hero.style.setProperty('--my',P.y.toFixed(1)+'px');
    if(fine&&!reduce){ ring.style.transform=`translate3d(${rx.toFixed(1)}px,${ry.toFixed(1)}px,0)`;
      const nx=(P.x/W-.5), ny=(P.y/H-.5);
      par.style.transform=`translate3d(${(nx*-34).toFixed(1)}px,${(ny*-24).toFixed(1)}px,0)`;
      logo.style.transform=`rotateY(${(nx*16).toFixed(2)}deg) rotateX(${(ny*-14).toFixed(2)}deg)`;
    }
    magnet();
    if(!reduce)drawFX();
    requestAnimationFrame(frame);
  }
  hero.style.setProperty('--mx','50%'); hero.style.setProperty('--my','45%');
  requestAnimationFrame(frame);

  document.addEventListener('visibilitychange',()=>{
    root.classList.toggle('paused',document.hidden);
    if(document.hidden){running=false;}else if(!running){running=true;requestAnimationFrame(frame);}
  });
})();
</script>
</body>
</html>
