<?php
// MQAP - PYM Attendance System — public landing page (single screen, smooth interactive)
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
:root{--ink:#070D26;--sapphire:#2563EB;--gold:#F5B82E;--muted:rgba(255,255,255,.72);--ease:cubic-bezier(.2,.8,.2,1)}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden;overscroll-behavior:none}
body{font-family:'Inter',sans-serif;background:var(--ink);color:#fff;-webkit-font-smoothing:antialiased;-webkit-tap-highlight-color:transparent}
a{text-decoration:none;color:inherit}

.hero{position:fixed;inset:0;height:100vh;height:100dvh;display:flex;flex-direction:column;overflow:hidden;isolation:isolate;background:radial-gradient(ellipse at 50% 40%,#0F1F5C 0%,#070D26 70%)}

/* Fully static background (painted once, never repainted) */
.wall-move{position:absolute;inset:0;z-index:-5;transform:translateZ(0)}
.wall{position:absolute;inset:-25%;display:grid;grid-template-columns:repeat(9,1fr);gap:16px;transform:rotate(-14deg) skewX(8deg) scale(1.1);opacity:.55}
.tile{aspect-ratio:2/3;border-radius:12px;display:flex;flex-direction:column;justify-content:flex-end;padding:14px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:15px;line-height:1.15;position:relative;overflow:hidden}
.tile i{position:absolute;top:14px;right:14px;font-size:22px;opacity:.5}
.tile small{display:block;font:600 10px 'Inter',sans-serif;letter-spacing:1.2px;text-transform:uppercase;opacity:.7;margin-top:4px}
.shade{position:absolute;inset:0;z-index:-4;background:radial-gradient(ellipse at 50% 45%,rgba(7,13,38,.72),rgba(7,13,38,.93) 75%),linear-gradient(180deg,rgba(7,13,38,.7),rgba(7,13,38,.35) 45%,rgba(7,13,38,.95))}

.orb{position:absolute;z-index:-3;border-radius:50%;pointer-events:none;opacity:.5;transform:translateZ(0)}
.orb.o1{width:520px;height:520px;left:-160px;top:-120px;background:radial-gradient(circle,rgba(245,184,46,.2),transparent 68%)}
.orb.o2{width:620px;height:620px;right:-200px;bottom:-160px;background:radial-gradient(circle,rgba(37,99,235,.28),transparent 68%)}

#fx{position:absolute;inset:0;z-index:-2;width:100%;height:100%;pointer-events:none}
.vig{position:absolute;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(ellipse at center,transparent 55%,rgba(3,6,20,.6) 100%)}

nav{display:flex;align-items:center;justify-content:space-between;padding:clamp(16px,3.2vh,28px) max(22px,5vw);animation:fadeDown .8s var(--ease) both}
.brand{display:flex;align-items:center;gap:12px}
.brand-icon{width:44px;height:44px;display:grid;place-items:center;flex-shrink:0}
.brand-icon img{width:100%;height:100%;object-fit:contain}
.brand b{font-family:'Plus Jakarta Sans',sans-serif;font-size:21px;letter-spacing:1px;display:block;line-height:1}
.brand span{font-size:9.5px;font-weight:700;letter-spacing:3px;color:var(--gold);text-transform:uppercase}
.nav-login{background:var(--gold);color:var(--ink);font-weight:800;font-size:14px;padding:10px 22px;border-radius:10px;transition:background .2s}
.nav-login:hover{background:#fff}
@keyframes fadeDown{from{opacity:0;transform:translate3d(0,-16px,0)}to{opacity:1;transform:none}}

.hero-body{flex:1;min-height:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:0 max(20px,6vw);max-width:980px;margin:0 auto;width:100%}
.rise{animation:riseIn .8s var(--ease) both;animation-delay:var(--d,0s)}
@keyframes riseIn{from{opacity:0;transform:translate3d(0,26px,0)}to{opacity:1;transform:none}}

.logo-in{margin-bottom:clamp(8px,2vh,20px)}
.logo-tilt{position:relative}
.logo-tilt::before{content:'';position:absolute;left:50%;top:50%;width:160%;height:160%;transform:translate(-50%,-50%);background:radial-gradient(circle,rgba(245,184,46,.34),transparent 65%);animation:glowP 4.5s ease-in-out infinite;pointer-events:none;will-change:transform,opacity}
.hero-logo{position:relative;display:block;width:clamp(86px,17vh,180px);height:auto;animation:bob 6s ease-in-out infinite;will-change:transform}
@keyframes bob{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-9px,0)}}
@keyframes glowP{0%,100%{opacity:.35;transform:translate(-50%,-50%) scale(.92)}50%{opacity:.75;transform:translate(-50%,-50%) scale(1.05)}}

.pill{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(245,184,46,.4);background:rgba(245,184,46,.1);color:var(--gold);font-size:12px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;padding:7px 16px;border-radius:99px;margin-bottom:clamp(12px,2.6vh,24px)}
h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:clamp(32px,min(7vw,9.5vh),76px);line-height:1.04;letter-spacing:-2px;text-shadow:0 4px 24px rgba(0,0,0,.55)}
/* static gradient text (animating background-position repaints every frame = lag) */
h1 em{font-style:normal;background:linear-gradient(110deg,var(--gold) 20%,#FFF3C4 50%,var(--gold) 80%);-webkit-background-clip:text;background-clip:text;color:transparent}
.tag{font-size:clamp(15px,2.2vh + .6vw,22px);font-weight:600;margin-top:clamp(10px,2.4vh,22px)}
.sub{font-size:clamp(13px,1.5vw,16.5px);color:var(--muted);margin-top:clamp(8px,1.6vh,12px);max-width:600px;line-height:1.6}
.cta{display:flex;margin-top:clamp(20px,4.4vh,38px);justify-content:center}

.btn{position:relative;display:inline-flex;align-items:center;gap:12px;font-size:clamp(15px,1.7vw,18px);font-weight:800;padding:clamp(14px,2.2vh,17px) 34px;border-radius:12px;color:#fff;background:linear-gradient(135deg,var(--sapphire),#1D4ED8);box-shadow:0 14px 36px rgba(37,99,235,.5);transition:transform .25s var(--ease),box-shadow .25s var(--ease)}
.btn:hover{transform:translate3d(0,-2px,0);box-shadow:0 20px 48px rgba(37,99,235,.68)}
.btn i{transition:transform .25s var(--ease)}
.btn:hover i.fa-chevron-right{transform:translateX(5px)}
.btn::before{content:'';position:absolute;inset:-2px;border-radius:14px;border:2px solid rgba(96,165,250,.7);opacity:0;animation:ring 2.8s ease-out infinite 1.6s;pointer-events:none;will-change:transform,opacity}
@keyframes ring{0%{opacity:.8;transform:scale(1)}100%{opacity:0;transform:scale(1.18,1.45)}}
.btn .sweep{position:absolute;inset:0;border-radius:12px;overflow:hidden;pointer-events:none}
.btn .sweep::after{content:'';position:absolute;top:0;bottom:0;left:0;width:40%;background:linear-gradient(105deg,transparent,rgba(255,255,255,.3),transparent);transform:translateX(-140%) skewX(-18deg);animation:sweep 4.2s ease-in-out infinite 2.2s;will-change:transform}
@keyframes sweep{0%{transform:translateX(-140%) skewX(-18deg)}35%,100%{transform:translateX(340%) skewX(-18deg)}}

.foot{padding:0 max(20px,5vw) clamp(12px,2.4vh,22px);text-align:center;font-size:12px;letter-spacing:.4px;color:rgba(255,255,255,.4);animation:fadeUp 1s var(--ease) 1s both}
@keyframes fadeUp{from{opacity:0;transform:translate3d(0,10px,0)}to{opacity:1;transform:none}}

/* Cursor light: each layer is its own GPU layer moved by transform only */
.gw{position:fixed;left:0;top:0;width:0;height:0;pointer-events:none;z-index:50;will-change:transform;opacity:0;transition:opacity .4s;contain:layout style}
.gw.on{opacity:1}
.gw i{position:absolute;border-radius:50%;transform:translate(-50%,-50%) scale(var(--s,1));transition:transform .25s var(--ease)}
.g1{width:560px;height:560px;background:radial-gradient(circle,rgba(245,184,46,.18) 0%,rgba(245,184,46,.07) 30%,rgba(37,99,235,.06) 52%,transparent 70%)}
.g2{width:200px;height:200px;background:radial-gradient(circle,rgba(255,236,170,.4) 0%,rgba(245,184,46,.16) 40%,transparent 70%)}
body.hov{--s:1}
.gw.hov i.g2{--s:1.5}
.gw.down i.g2{--s:.8}

@media (max-width:760px){
  .wall{grid-template-columns:repeat(5,1fr);inset:-40%}
  .cta{width:100%;max-width:380px}
  .btn{width:100%;justify-content:center}
}
@media (max-height:560px){.sub,.pill{display:none}}
@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation:none!important;transition:none!important}
  .rise,nav,.foot{opacity:1;transform:none}
}
.paused *,.paused *::before,.paused *::after{animation-play-state:paused!important}
</style>
</head>
<body>

<header class="hero" id="hero">
  <div class="wall-move" aria-hidden="true"><div class="wall" id="wall"></div></div>
  <div class="shade" aria-hidden="true"></div>
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
    <div class="logo-in rise" style="--d:.15s"><div class="logo-tilt" id="logoTilt"><img src="logo.png" alt="PYM logo" class="hero-logo"/></div></div>
    <div class="pill rise" style="--d:.3s"><i class="fas fa-church"></i> Parish Youth Ministry</div>
    <h1 class="rise" style="--d:.42s">MQAP – <em>PYM</em><br/>Attendance System</h1>
    <p class="tag rise" style="--d:.58s">Every youth. Every service. Every event.</p>
    <p class="sub rise" style="--d:.7s">A transparent, real-time record of attendance for Normal Serve and special events.</p>
    <div class="cta rise" style="--d:.85s">
      <a href="login.php" class="btn" id="mainBtn" data-hover><span class="sweep"></span><i class="fas fa-right-to-bracket"></i> Login <i class="fas fa-chevron-right"></i></a>
    </div>
  </main>

  <footer class="foot">Parish Youth Ministry · MQAP</footer>
</header>

<div class="gw" id="gw1" aria-hidden="true"><i class="g1"></i></div>
<div class="gw" id="gw2" aria-hidden="true"><i class="g2"></i></div>

<script>
(function(){
  'use strict';
  var root=document.documentElement;
  var small=matchMedia('(max-width:760px)').matches;
  var reduce=matchMedia('(prefers-reduced-motion:reduce)').matches;

  /* Static tile wall (built once) */
  var items=[['Sunday 7:00 AM','Normal Serve','fa-sun'],['Special Event','Youth Activity','fa-star'],['Sunday 9:00 AM','Normal Serve','fa-church'],['Recollection','Retreat','fa-dove'],['Saturday 5:00 PM','Normal Serve','fa-moon'],['Bible Study','Fellowship','fa-book-bible'],['Sunday 5:00 PM','Normal Serve','fa-cross'],['Youth Day','Celebration','fa-people-group'],['Sunday 7:00 PM','Normal Serve','fa-candle-holder'],['Outreach','Service','fa-hand-holding-heart'],['Choir Night','Ministry','fa-music'],['Sunday 11:00 AM','Normal Serve','fa-bell']];
  var pal=[['#1E3A8A','#2563EB'],['#7C5A06','#F5B82E'],['#0F1F5C','#4F46E5'],['#064E3B','#059669'],['#4C1D95','#7C3AED'],['#7F1D1D','#DC2626'],['#0C4A6E','#0EA5E9'],['#78350F','#D97706']];
  var n=small?15:27,h='';
  for(var i=0;i<n;i++){
    var it=items[(i*5+Math.floor(i/9))%items.length], c=pal[(i*3+Math.floor(i/9)*2)%pal.length];
    h+='<div class="tile" style="background:linear-gradient(155deg,'+c[1]+','+c[0]+');margin-top:'+(((i%9)%2)?26:0)+'px"><i class="fas '+it[2]+'"></i>'+it[0]+'<small>'+it[1]+'</small></div>';
  }
  document.getElementById('wall').innerHTML=h;

  var gw1=document.getElementById('gw1'), gw2=document.getElementById('gw2');

  /* Pointer: events only store values, one rAF loop draws */
  var W=innerWidth,H=innerHeight;
  var px=W/2,py=H/2;      // raw pointer
  var gx=px,gy=py;        // soft outer glow (tight easing)
  var active=false, moved=false;

  function show(on){gw1.classList.toggle('on',on);gw2.classList.toggle('on',on);}
  function onMove(e){
    px=e.clientX; py=e.clientY; active=true;
    if(!moved){moved=true; gx=px; gy=py;}
    show(true);
  }
  if(window.PointerEvent){addEventListener('pointermove',onMove,{passive:true});}
  else{addEventListener('mousemove',onMove,{passive:true});addEventListener('touchmove',function(e){var t=e.touches[0];if(t)onMove(t);},{passive:true});}
  document.addEventListener('mouseleave',function(){active=false;show(false);});
  document.addEventListener('mouseenter',function(){if(moved)show(true);});
  addEventListener('touchstart',function(e){var t=e.touches[0];if(t)onMove(t);},{passive:true});
  addEventListener('touchend',function(){show(false);active=false;},{passive:true});

  function cls(add,c){gw1.classList[add?'add':'remove'](c);gw2.classList[add?'add':'remove'](c);}
  document.querySelectorAll('[data-hover]').forEach(function(el){
    el.addEventListener('mouseenter',function(){cls(true,'hov');});
    el.addEventListener('mouseleave',function(){cls(false,'hov');});
  });
  addEventListener('mousedown',function(){cls(true,'down');});
  addEventListener('mouseup',function(){cls(false,'down');});

  /* Canvas particles: light and cheap */
  var cv=document.getElementById('fx'), ctx=cv.getContext('2d',{alpha:true});
  var parts=[];
  function build(count){
    parts=[];
    for(var i=0;i<count;i++)parts.push({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,r:Math.random()*1.3+.8,g:Math.random()<.45});
  }
  function resize(){
    W=innerWidth;H=innerHeight;
    cv.width=W; cv.height=H;           // 1x resolution: crisp enough, 2-4x cheaper
    build(small?16:Math.min(30,Math.round(W*H/50000)));
  }
  var rt; addEventListener('resize',function(){clearTimeout(rt);rt=setTimeout(resize,150);});
  resize();

  var LINK=small?85:115, LINK2=LINK*LINK, PR=140, PR2=PR*PR;
  function drawFX(k){
    ctx.clearRect(0,0,W,H);
    var len=parts.length,i,j,p,q,dx,dy,d2;
    for(i=0;i<len;i++){
      p=parts[i];
      if(active){
        dx=p.x-px; dy=p.y-py; d2=dx*dx+dy*dy;
        if(d2<PR2&&d2>1){var d=Math.sqrt(d2),f=(1-d/PR)*.05*k;p.vx+=dx/d*f;p.vy+=dy/d*f;}
      }
      p.vx*=.985; p.vy*=.985;
      if(Math.abs(p.vx)<.06)p.vx+=(Math.random()-.5)*.01;
      if(Math.abs(p.vy)<.06)p.vy+=(Math.random()-.5)*.01;
      p.x+=p.vx*k; p.y+=p.vy*k;
      if(p.x<-8)p.x=W+8;else if(p.x>W+8)p.x=-8;
      if(p.y<-8)p.y=H+8;else if(p.y>H+8)p.y=-8;
    }
    ctx.lineWidth=.8; ctx.strokeStyle='rgba(147,180,255,.16)'; ctx.beginPath();
    for(i=0;i<len;i++){p=parts[i];
      for(j=i+1;j<len;j++){q=parts[j];dx=p.x-q.x;if(dx>LINK||dx<-LINK)continue;dy=p.y-q.y;
        if(dx*dx+dy*dy<LINK2){ctx.moveTo(p.x,p.y);ctx.lineTo(q.x,q.y);}}}
    ctx.stroke();
    if(active){
      ctx.strokeStyle='rgba(245,184,46,.34)'; ctx.beginPath();
      for(i=0;i<len;i++){p=parts[i];dx=p.x-px;dy=p.y-py;if(dx*dx+dy*dy<PR2*1.5){ctx.moveTo(p.x,p.y);ctx.lineTo(px,py);}}
      ctx.stroke();
    }
    ctx.fillStyle='rgba(147,180,255,.8)'; ctx.beginPath();
    for(i=0;i<len;i++){p=parts[i];if(!p.g){ctx.moveTo(p.x+p.r,p.y);ctx.arc(p.x,p.y,p.r,0,6.2832);}}
    ctx.fill();
    ctx.fillStyle='rgba(245,184,46,.9)'; ctx.beginPath();
    for(i=0;i<len;i++){p=parts[i];if(p.g){ctx.moveTo(p.x+p.r,p.y);ctx.arc(p.x,p.y,p.r,0,6.2832);}}
    ctx.fill();
  }

  /* One rAF loop. Small glow = locked to pointer (zero lag). Big glow = very light easing. */
  var last=performance.now(), running=true, slow=0;
  function frame(now){
    if(!running)return;
    var dt=Math.min((now-last)/1000,.05); last=now;
    var k=dt*60;
    if(dt>.03){slow++;}else{slow=Math.max(0,slow-1);}
    if(slow>40&&parts.length>10){parts.length=Math.max(10,Math.floor(parts.length*.7));slow=0;}

    var e=1-Math.pow(1e-9,dt);                 // ~30% per 60fps frame: follows closely
    gx+=(px-gx)*e; gy+=(py-gy)*e;
    if(moved){
      gw1.style.transform='translate3d('+gx.toFixed(1)+'px,'+gy.toFixed(1)+'px,0)';
      gw2.style.transform='translate3d('+px+'px,'+py+'px,0)';
    }
    if(!reduce)drawFX(k);
    requestAnimationFrame(frame);
  }
  requestAnimationFrame(function(t){last=t;frame(t);});

  document.addEventListener('visibilitychange',function(){
    root.classList.toggle('paused',document.hidden);
    if(document.hidden){running=false;}else if(!running){running=true;last=performance.now();requestAnimationFrame(frame);}
  });
})();
</script>
</body>
</html>
