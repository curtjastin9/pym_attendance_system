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

/* Static, light background wall (no per-frame work) */
.wall-move{position:absolute;inset:0;z-index:-5;animation:drift 60s ease-in-out infinite alternate;will-change:transform}
.wall{position:absolute;inset:-25%;display:grid;grid-template-columns:repeat(9,1fr);gap:16px;transform:rotate(-14deg) skewX(8deg) scale(1.1);opacity:.55}
.tile{aspect-ratio:2/3;border-radius:12px;display:flex;flex-direction:column;justify-content:flex-end;padding:14px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:15px;line-height:1.15;position:relative;overflow:hidden}
.tile i{position:absolute;top:14px;right:14px;font-size:22px;opacity:.5}
.tile small{display:block;font:600 10px 'Inter',sans-serif;letter-spacing:1.2px;text-transform:uppercase;opacity:.7;margin-top:4px}
@keyframes drift{from{transform:translate3d(-1.5%,-1%,0)}to{transform:translate3d(1.5%,1.2%,0)}}
.shade{position:absolute;inset:0;z-index:-4;background:radial-gradient(ellipse at 50% 45%,rgba(7,13,38,.72),rgba(7,13,38,.93) 75%),linear-gradient(180deg,rgba(7,13,38,.7),rgba(7,13,38,.35) 45%,rgba(7,13,38,.95))}

/* Spotlight: moved with transform only (GPU, no repaint) */
.spot{position:absolute;left:0;top:0;width:700px;height:700px;margin:-350px 0 0 -350px;z-index:-3;pointer-events:none;opacity:0;transition:opacity .6s;background:radial-gradient(circle,rgba(245,184,46,.16),rgba(37,99,235,.11) 40%,transparent 68%);will-change:transform}
.spot.on{opacity:1}

.orb{position:absolute;z-index:-3;border-radius:50%;pointer-events:none;opacity:.5}
.orb.o1{width:520px;height:520px;left:-160px;top:-120px;background:radial-gradient(circle,rgba(245,184,46,.2),transparent 68%);animation:orbA 16s ease-in-out infinite alternate}
.orb.o2{width:620px;height:620px;right:-200px;bottom:-160px;background:radial-gradient(circle,rgba(37,99,235,.28),transparent 68%);animation:orbB 19s ease-in-out infinite alternate}
@keyframes orbA{from{transform:translate3d(0,0,0)}to{transform:translate3d(90px,60px,0)}}
@keyframes orbB{from{transform:translate3d(0,0,0)}to{transform:translate3d(-80px,-50px,0)}}

#fx{position:absolute;inset:0;z-index:-2;width:100%;height:100%;pointer-events:none}
.vig{position:absolute;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(ellipse at center,transparent 55%,rgba(3,6,20,.6) 100%)}

nav{display:flex;align-items:center;justify-content:space-between;padding:clamp(16px,3.2vh,28px) max(22px,5vw);animation:fadeDown .9s var(--ease) both}
.brand{display:flex;align-items:center;gap:12px}
.brand-icon{width:44px;height:44px;display:grid;place-items:center;flex-shrink:0}
.brand-icon img{width:100%;height:100%;object-fit:contain}
.brand b{font-family:'Plus Jakarta Sans',sans-serif;font-size:21px;letter-spacing:1px;display:block;line-height:1}
.brand span{font-size:9.5px;font-weight:700;letter-spacing:3px;color:var(--gold);text-transform:uppercase}
.nav-login{background:var(--gold);color:var(--ink);font-weight:800;font-size:14px;padding:10px 22px;border-radius:10px;transition:background .25s,box-shadow .25s}
.nav-login:hover{background:#fff;box-shadow:0 10px 26px -8px rgba(245,184,46,.7)}
@keyframes fadeDown{from{opacity:0;transform:translate3d(0,-16px,0)}to{opacity:1;transform:none}}

.hero-body{flex:1;min-height:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:0 max(20px,6vw);max-width:980px;margin:0 auto;width:100%}
/* opacity + transform only (no blur filter = no lag) */
.rise{animation:riseIn .9s var(--ease) both;animation-delay:var(--d,0s)}
@keyframes riseIn{from{opacity:0;transform:translate3d(0,26px,0)}to{opacity:1;transform:none}}

.logo-in{margin-bottom:clamp(8px,2vh,20px)}
.logo-tilt{position:relative;will-change:transform}
.logo-tilt::before{content:'';position:absolute;left:50%;top:50%;width:160%;height:160%;transform:translate(-50%,-50%);background:radial-gradient(circle,rgba(245,184,46,.34),transparent 65%);animation:glowP 4.5s ease-in-out infinite;pointer-events:none}
.hero-logo{position:relative;display:block;width:clamp(86px,17vh,180px);height:auto;animation:bob 6s ease-in-out infinite}
@keyframes bob{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-9px,0)}}
@keyframes glowP{0%,100%{opacity:.35;transform:translate(-50%,-50%) scale(.92)}50%{opacity:.75;transform:translate(-50%,-50%) scale(1.05)}}

.pill{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(245,184,46,.4);background:rgba(245,184,46,.1);color:var(--gold);font-size:12px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;padding:7px 16px;border-radius:99px;margin-bottom:clamp(12px,2.6vh,24px)}
h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:clamp(32px,min(7vw,9.5vh),76px);line-height:1.04;letter-spacing:-2px;text-shadow:0 4px 24px rgba(0,0,0,.55)}
h1 em{font-style:normal;background:linear-gradient(110deg,var(--gold) 20%,#FFF3C4 45%,var(--gold) 70%);background-size:220% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:shine 5s ease-in-out infinite}
@keyframes shine{0%,20%{background-position:120% 0}70%,100%{background-position:-60% 0}}
.tag{font-size:clamp(15px,2.2vh + .6vw,22px);font-weight:600;margin-top:clamp(10px,2.4vh,22px)}
.sub{font-size:clamp(13px,1.5vw,16.5px);color:var(--muted);margin-top:clamp(8px,1.6vh,12px);max-width:600px;line-height:1.6}
.cta{display:flex;margin-top:clamp(20px,4.4vh,38px);justify-content:center}

.btn{position:relative;display:inline-flex;align-items:center;gap:12px;font-size:clamp(15px,1.7vw,18px);font-weight:800;padding:clamp(14px,2.2vh,17px) 34px;border-radius:12px;color:#fff;background:linear-gradient(135deg,var(--sapphire),#1D4ED8);box-shadow:0 14px 36px rgba(37,99,235,.5);transition:box-shadow .3s var(--ease);will-change:transform}
.btn:hover{box-shadow:0 20px 48px rgba(37,99,235,.68)}
.btn i{transition:transform .25s var(--ease)}
.btn:hover i.fa-chevron-right{transform:translateX(5px)}
.btn::before{content:'';position:absolute;inset:-2px;border-radius:14px;border:2px solid rgba(96,165,250,.7);opacity:0;animation:ring 2.8s ease-out infinite 1.6s;pointer-events:none}
@keyframes ring{0%{opacity:.8;transform:scale(1)}100%{opacity:0;transform:scale(1.18,1.45)}}
.btn .sweep{position:absolute;inset:0;border-radius:12px;overflow:hidden;pointer-events:none}
.btn .sweep::after{content:'';position:absolute;top:0;bottom:0;left:0;width:40%;background:linear-gradient(105deg,transparent,rgba(255,255,255,.3),transparent);transform:translateX(-140%) skewX(-18deg);animation:sweep 4.2s ease-in-out infinite 2.2s}
@keyframes sweep{0%{transform:translateX(-140%) skewX(-18deg)}35%,100%{transform:translateX(340%) skewX(-18deg)}}

.foot{padding:0 max(20px,5vw) clamp(12px,2.4vh,22px);text-align:center;font-size:12px;letter-spacing:.4px;color:rgba(255,255,255,.4);animation:fadeUp 1s var(--ease) 1.1s both}
@keyframes fadeUp{from{opacity:0;transform:translate3d(0,10px,0)}to{opacity:1;transform:none}}

/* Mouse glow: normal cursor stays, a soft light follows it (transform only = smooth) */
.glow-wrap{position:fixed;left:0;top:0;width:0;height:0;pointer-events:none;z-index:50;will-change:transform;opacity:0;transition:opacity .5s}
.glow-wrap.on{opacity:1}
.glow-wrap i{position:absolute;border-radius:50%;transform:translate(-50%,-50%);transition:transform .35s var(--ease),opacity .35s}
.glow-wrap .g1{width:760px;height:760px;background:radial-gradient(circle,rgba(245,184,46,.20) 0%,rgba(245,184,46,.09) 25%,rgba(37,99,235,.08) 48%,transparent 70%)}
.glow-wrap .g2{width:260px;height:260px;background:radial-gradient(circle,rgba(255,236,170,.42) 0%,rgba(245,184,46,.18) 38%,transparent 70%)}
.glow-wrap.hov .g2{transform:translate(-50%,-50%) scale(1.6)}
.glow-wrap.down .g2{transform:translate(-50%,-50%) scale(.8);opacity:.9}

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

<div class="glow-wrap" id="glow" aria-hidden="true"><i class="g1"></i><i class="g2"></i></div>

<script>
(function(){
  'use strict';
  var root=document.documentElement;
  var small=matchMedia('(max-width:760px)').matches;
  var reduce=matchMedia('(prefers-reduced-motion:reduce)').matches;
  var fine=matchMedia('(hover:hover) and (pointer:fine)').matches;

  /* Tile wall (light: 24-30 tiles, built once) */
  var items=[['Sunday 7:00 AM','Normal Serve','fa-sun'],['Special Event','Youth Activity','fa-star'],['Sunday 9:00 AM','Normal Serve','fa-church'],['Recollection','Retreat','fa-dove'],['Saturday 5:00 PM','Normal Serve','fa-moon'],['Bible Study','Fellowship','fa-book-bible'],['Sunday 5:00 PM','Normal Serve','fa-cross'],['Youth Day','Celebration','fa-people-group'],['Sunday 7:00 PM','Normal Serve','fa-candle-holder'],['Outreach','Service','fa-hand-holding-heart'],['Choir Night','Ministry','fa-music'],['Sunday 11:00 AM','Normal Serve','fa-bell']];
  var pal=[['#1E3A8A','#2563EB'],['#7C5A06','#F5B82E'],['#0F1F5C','#4F46E5'],['#064E3B','#059669'],['#4C1D95','#7C3AED'],['#7F1D1D','#DC2626'],['#0C4A6E','#0EA5E9'],['#78350F','#D97706']];
  var n=small?20:36,h='';
  for(var i=0;i<n;i++){
    var it=items[(i*5+Math.floor(i/9))%items.length], c=pal[(i*3+Math.floor(i/9)*2)%pal.length];
    h+='<div class="tile" style="background:linear-gradient(155deg,'+c[1]+','+c[0]+');margin-top:'+(((i%9)%2)?26:0)+'px"><i class="fas '+it[2]+'"></i>'+it[0]+'<small>'+it[1]+'</small></div>';
  }
  document.getElementById('wall').innerHTML=h;

  var glow=document.getElementById('glow');

  /* Pointer state: events only STORE values, rAF does all the drawing */
  var W=innerWidth,H=innerHeight;
  var px=W/2,py=H/2;            // raw pointer
  var sx=px,sy=py;              // smoothed glow position
  var active=false, moved=false;

  function onMove(e){
    px=e.clientX; py=e.clientY; active=true;
    if(!moved){moved=true; sx=px; sy=py;}
    glow.classList.add('on');
  }
  if(window.PointerEvent){addEventListener('pointermove',onMove,{passive:true});}
  else{addEventListener('mousemove',onMove,{passive:true});addEventListener('touchmove',function(e){var t=e.touches[0];if(t)onMove(t);},{passive:true});}
  document.addEventListener('mouseleave',function(){active=false;glow.classList.remove('on');});
  document.addEventListener('mouseenter',function(){if(moved)glow.classList.add('on');});
  addEventListener('touchstart',function(e){var t=e.touches[0];if(t)onMove(t);},{passive:true});
  addEventListener('touchend',function(){glow.classList.remove('on');active=false;},{passive:true});

  document.querySelectorAll('[data-hover]').forEach(function(el){
    el.addEventListener('mouseenter',function(){glow.classList.add('hov');});
    el.addEventListener('mouseleave',function(){glow.classList.remove('hov');});
  });
  addEventListener('mousedown',function(){glow.classList.add('down');});
  addEventListener('mouseup',function(){glow.classList.remove('down');});

  function measure(){W=innerWidth;H=innerHeight;}

  /* Canvas particles (small count, adaptive) */
  var cv=document.getElementById('fx'), ctx=cv.getContext('2d',{alpha:true});
  var dpr=1, parts=[], target=0;
  function build(count){
    parts=[];
    for(var i=0;i<count;i++)parts.push({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.35,vy:(Math.random()-.5)*.35,r:Math.random()*1.4+.8,g:Math.random()<.45});
  }
  function resize(){
    dpr=Math.min(devicePixelRatio||1,1.5);
    cv.width=Math.round(innerWidth*dpr); cv.height=Math.round(innerHeight*dpr);
    ctx.setTransform(dpr,0,0,dpr,0,0);
    measure();
    target=small?22:Math.min(46,Math.round(W*H/32000));
    build(target);
  }
  var rt; addEventListener('resize',function(){clearTimeout(rt);rt=setTimeout(resize,150);});
  resize();

  var LINK=small?80:110, LINK2=LINK*LINK, PR=150, PR2=PR*PR;
  function drawFX(dt){
    ctx.clearRect(0,0,W,H);
    var k=dt*60;                      // frame-rate independent
    var len=parts.length,i,j,p,q,dx,dy,d2;
    for(i=0;i<len;i++){
      p=parts[i];
      if(active){
        dx=p.x-sx; dy=p.y-sy; d2=dx*dx+dy*dy;
        if(d2<PR2&&d2>1){var d=Math.sqrt(d2),f=(1-d/PR)*.05*k;p.vx+=dx/d*f;p.vy+=dy/d*f;}
      }
      p.vx*=Math.pow(.985,k); p.vy*=Math.pow(.985,k);
      if(Math.abs(p.vx)<.06)p.vx+=(Math.random()-.5)*.01;
      if(Math.abs(p.vy)<.06)p.vy+=(Math.random()-.5)*.01;
      p.x+=p.vx*k; p.y+=p.vy*k;
      if(p.x<-8)p.x=W+8;else if(p.x>W+8)p.x=-8;
      if(p.y<-8)p.y=H+8;else if(p.y>H+8)p.y=-8;
    }
    /* lines: one batched path per colour = few draw calls */
    ctx.lineWidth=.8; ctx.strokeStyle='rgba(147,180,255,.16)'; ctx.beginPath();
    for(i=0;i<len;i++){p=parts[i];
      for(j=i+1;j<len;j++){q=parts[j];dx=p.x-q.x;if(dx>LINK||dx<-LINK)continue;dy=p.y-q.y;
        if(dx*dx+dy*dy<LINK2){ctx.moveTo(p.x,p.y);ctx.lineTo(q.x,q.y);}}}
    ctx.stroke();
    if(active){
      ctx.strokeStyle='rgba(245,184,46,.38)'; ctx.beginPath();
      for(i=0;i<len;i++){p=parts[i];dx=p.x-sx;dy=p.y-sy;if(dx*dx+dy*dy<PR2*1.6){ctx.moveTo(p.x,p.y);ctx.lineTo(sx,sy);}}
      ctx.stroke();
    }
    ctx.fillStyle='rgba(147,180,255,.8)'; ctx.beginPath();
    for(i=0;i<len;i++){p=parts[i];if(!p.g){ctx.moveTo(p.x+p.r,p.y);ctx.arc(p.x,p.y,p.r,0,6.2832);}}
    ctx.fill();
    ctx.fillStyle='rgba(245,184,46,.9)'; ctx.beginPath();
    for(i=0;i<len;i++){p=parts[i];if(p.g){ctx.moveTo(p.x+p.r,p.y);ctx.arc(p.x,p.y,p.r,0,6.2832);}}
    ctx.fill();
  }

  /* One rAF loop, time-based smoothing, adaptive quality */
  var last=performance.now(), running=true, slow=0, fast=0;
  function frame(now){
    if(!running)return;
    var dt=Math.min((now-last)/1000,.05); last=now;
    var e1=1-Math.pow(.0005,dt), e2=1-Math.pow(.00002,dt); // ease factors (frame-rate independent)

    if(dt>.028){slow++;fast=0;}else{fast++;slow=Math.max(0,slow-1);}
    if(slow>40&&parts.length>14){parts.length=Math.max(14,Math.floor(parts.length*.7));slow=0;} // auto-lighten on slow devices

    sx+=(px-sx)*e1; sy+=(py-sy)*e1;
    if(moved)glow.style.transform='translate3d('+sx.toFixed(1)+'px,'+sy.toFixed(1)+'px,0)';
    if(!reduce)drawFX(dt);
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
