<?php
require_once __DIR__ . '/session.php';
// If already logged in, go straight to the dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
  <meta name="theme-color" content="#070D26"/>
  <link rel="icon" type="image/png" href="favicon.png"/>
  <title>PYM Attendance – Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    :root{
      --ink:#070D26; --sapphire:#2563EB; --sapphire-2:#1D4ED8; --glow:#3B82F6;
      --gold:#F5B82E; --gold-2:#FFF3C4;
      --text:#F1F5FF; --soft:rgba(226,232,255,.68); --faint:rgba(226,232,255,.42);
      --line:rgba(255,255,255,.12);
      --ok:#34D399; --bad:#FCA5A5;
      --ease:cubic-bezier(.2,.8,.2,1);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html{-webkit-text-size-adjust:100%}
    body.auth-body{
      min-height:100vh;min-height:100dvh;display:flex;align-items:center;justify-content:center;
      padding:max(28px,env(safe-area-inset-top)) 16px max(28px,env(safe-area-inset-bottom));
      font-family:'Inter',system-ui,sans-serif;color:var(--text);background:var(--ink);
      -webkit-font-smoothing:antialiased;-webkit-tap-highlight-color:transparent;position:relative;overflow-x:hidden;
    }
    a{text-decoration:none;color:inherit}
    button,input{font-family:inherit}
    .hidden{display:none!important}

    /* ================= Background: static layers + one light canvas ================= */
    .bg{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none;background:radial-gradient(ellipse at 50% 40%,#0F1F5C 0%,#070D26 70%)}
    .wall-move{position:absolute;inset:0;transform:translateZ(0)}
    .wall{position:absolute;inset:-25%;display:grid;grid-template-columns:repeat(9,1fr);gap:16px;transform:rotate(-14deg) skewX(8deg) scale(1.1);opacity:.4}
    .tile{aspect-ratio:2/3;border-radius:12px;display:flex;flex-direction:column;justify-content:flex-end;padding:14px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:15px;line-height:1.15;position:relative;overflow:hidden}
    .tile i{position:absolute;top:14px;right:14px;font-size:22px;opacity:.5}
    .tile small{display:block;font:600 10px 'Inter',sans-serif;letter-spacing:.8px;opacity:.7;margin-top:4px}
    .shade{position:absolute;inset:0;background:radial-gradient(ellipse at 50% 45%,rgba(7,13,38,.78),rgba(7,13,38,.95) 75%),linear-gradient(180deg,rgba(7,13,38,.7),rgba(7,13,38,.35) 45%,rgba(7,13,38,.95))}
    .orb{position:absolute;border-radius:50%;opacity:.55;transform:translateZ(0)}
    .orb.o1{width:520px;height:520px;left:-160px;top:-120px;background:radial-gradient(circle,rgba(245,184,46,.22),transparent 68%)}
    .orb.o2{width:620px;height:620px;right:-200px;bottom:-160px;background:radial-gradient(circle,rgba(37,99,235,.3),transparent 68%)}
    #fx{position:absolute;inset:0;width:100%;height:100%}
    .vig{position:absolute;inset:0;background:radial-gradient(ellipse at center,transparent 55%,rgba(3,6,20,.6) 100%)}

    /* Cursor light: GPU layers moved by transform only */
    .gw{position:fixed;left:0;top:0;width:0;height:0;pointer-events:none;z-index:1;will-change:transform;opacity:0;transition:opacity .4s;contain:layout style}
    .gw.on{opacity:1}
    .gw i{position:absolute;border-radius:50%;transform:translate(-50%,-50%) scale(var(--s,1));transition:transform .25s var(--ease)}
    .g1{width:560px;height:560px;background:radial-gradient(circle,rgba(245,184,46,.16) 0%,rgba(245,184,46,.06) 30%,rgba(37,99,235,.06) 52%,transparent 70%)}
    .g2{width:200px;height:200px;background:radial-gradient(circle,rgba(255,236,170,.3) 0%,rgba(245,184,46,.12) 40%,transparent 70%)}
    .gw.hov i.g2{--s:1.5}
    .gw.down i.g2{--s:.8}

    /* ================= Layout ================= */
    .home-link{position:fixed;top:max(16px,env(safe-area-inset-top));left:max(16px,env(safe-area-inset-left));z-index:5;display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--soft);padding:9px 14px;border-radius:99px;border:1px solid var(--line);background:rgba(10,17,46,.82);transition:color .2s,border-color .2s,background .2s}
    .home-link:hover{color:#fff;border-color:rgba(245,184,46,.5);background:rgba(245,184,46,.12)}
    .home-link i{font-size:11px;transition:transform .25s var(--ease)}
    .home-link:hover i{transform:translateX(-3px)}

    .auth-wrap{position:relative;z-index:2;width:100%;max-width:440px;animation:cardIn .7s var(--ease) both}
    @keyframes cardIn{from{opacity:0;transform:translate3d(0,24px,0)}to{opacity:1;transform:none}}

    /* No backdrop-filter: blurring a live canvas behind the card every frame was the main lag source */
    .auth-card{
      position:relative;overflow:hidden;border-radius:28px;padding:34px 32px 30px;
      background:linear-gradient(180deg,rgba(17,27,68,.92),rgba(10,17,46,.94));border:1px solid var(--line);
      box-shadow:0 30px 80px -20px rgba(0,0,0,.65),inset 0 1px 0 rgba(255,255,255,.1);
    }
    .auth-card::before{content:'';position:absolute;left:12%;right:12%;top:0;height:2px;background:linear-gradient(90deg,transparent,var(--gold),transparent);opacity:.85}

    /* Brand */
    a.auth-logo{display:flex;flex-direction:column;align-items:center;gap:10px;margin-bottom:26px;text-align:center}
    .logo-icon{position:relative;width:76px;height:76px;display:grid;place-items:center}
    .logo-icon::before{content:'';position:absolute;left:50%;top:50%;width:170%;height:170%;transform:translate(-50%,-50%);background:radial-gradient(circle,rgba(245,184,46,.38),transparent 65%);animation:glowP 4.5s ease-in-out infinite;will-change:transform,opacity}
    .logo-icon img{position:relative;width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 6px 14px rgba(0,0,0,.45));animation:bob 6s ease-in-out infinite;will-change:transform}
    @keyframes bob{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-6px,0)}}
    @keyframes glowP{0%,100%{opacity:.35;transform:translate(-50%,-50%) scale(.92)}50%{opacity:.8;transform:translate(-50%,-50%) scale(1.06)}}
    .logo-text{display:flex;flex-direction:column;gap:2px}
    .logo-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:26px;font-weight:800;letter-spacing:1px;line-height:1}
    .logo-sub{font-size:12.5px;font-weight:600;color:var(--gold);letter-spacing:.6px}

    /* Tabs with a sliding pill (transform only) */
    .auth-tabs{position:relative;display:flex;padding:5px;margin-bottom:22px;border-radius:14px;background:rgba(255,255,255,.06);border:1px solid var(--line)}
    .tab-ind{position:absolute;left:0;top:5px;bottom:5px;width:0;border-radius:10px;background:linear-gradient(135deg,var(--sapphire),var(--sapphire-2));box-shadow:0 8px 22px -6px rgba(37,99,235,.7);transition:transform .3s var(--ease),width .3s var(--ease),opacity .2s;z-index:0;will-change:transform}
    .auth-tab{position:relative;z-index:1;flex:1;min-height:42px;border:none;background:none;border-radius:10px;font-size:14px;font-weight:700;color:var(--soft);cursor:pointer;transition:color .2s}
    .auth-tab:hover{color:#fff}
    .auth-tab.active{color:#fff}

    /* Messages */
    .auth-message{font-size:13px;font-weight:600;line-height:1.5;text-align:center;border-radius:12px;margin-bottom:16px;padding:11px 14px}
    .auth-message:empty{display:none}
    .auth-message.success{color:var(--ok);background:rgba(16,185,129,.12);border:1px solid rgba(52,211,153,.3);animation:msgIn .3s var(--ease) both}
    .auth-message.error{color:var(--bad);background:rgba(239,68,68,.12);border:1px solid rgba(252,165,165,.3);animation:msgShake .4s ease both}
    @keyframes msgIn{from{opacity:0;transform:translate3d(0,-8px,0)}to{opacity:1;transform:none}}
    @keyframes msgShake{0%{opacity:0;transform:translate3d(0,-6px,0)}30%{opacity:1;transform:translate3d(-6px,0,0)}50%{transform:translate3d(5px,0,0)}70%{transform:translate3d(-3px,0,0)}100%{transform:none}}

    /* Forms */
    .auth-form{display:none;flex-direction:column}
    .auth-form.active{display:flex}
    .auth-form.active>*{animation:fieldIn .35s var(--ease) both}
    .auth-form.active>*:nth-child(1){animation-delay:0s}
    .auth-form.active>*:nth-child(2){animation-delay:.03s}
    .auth-form.active>*:nth-child(3){animation-delay:.06s}
    .auth-form.active>*:nth-child(4){animation-delay:.09s}
    .auth-form.active>*:nth-child(5){animation-delay:.12s}
    .auth-form.active>*:nth-child(6){animation-delay:.15s}
    .auth-form.active>*:nth-child(n+7){animation-delay:.18s}
    @keyframes fieldIn{from{opacity:0;transform:translate3d(0,10px,0)}to{opacity:1;transform:none}}

    .auth-form label{font-size:13px;font-weight:600;color:var(--soft);margin:14px 0 7px}
    .auth-form label:first-child{margin-top:0}
    .field{position:relative}
    .field>i.ic{position:absolute;left:16px;top:50%;transform:translateY(-50%);font-size:14px;color:var(--faint);transition:color .15s;pointer-events:none;z-index:2}
    .field:focus-within>i.ic{color:var(--gold)}
    .auth-form input{
      width:100%;min-height:50px;padding:13px 16px 13px 44px;font-size:15px;color:#fff;
      background:rgba(255,255,255,.06);border:1.5px solid var(--line);border-radius:14px;outline:none;
      transition:border-color .15s,box-shadow .15s,background-color .15s;
    }
    .auth-form input::placeholder{color:rgba(226,232,255,.35)}
    .auth-form input:hover{border-color:rgba(255,255,255,.22)}
    .auth-form input:focus{border-color:var(--glow);background:rgba(255,255,255,.09);box-shadow:0 0 0 4px rgba(59,130,246,.18)}
    .auth-form input:-webkit-autofill{-webkit-text-fill-color:#fff;caret-color:#fff;box-shadow:0 0 0 100px #14204f inset;transition:background-color 9999s}
    input.code-input{padding:13px 16px!important;text-align:center;font-size:26px!important;font-weight:800;letter-spacing:10px}
    input.code-input::placeholder{letter-spacing:8px}

    /* password show / hide (button is added by mobile.js) */
    .pw-wrap{position:relative;display:flex;width:100%}
    .pw-wrap>input{flex:1;padding-right:52px!important}
    .pw-toggle{position:absolute;right:5px;top:50%;transform:translateY(-50%);width:40px;height:40px;border:none;background:none;color:var(--faint);border-radius:10px;font-size:15px;display:grid;place-items:center;cursor:pointer;transition:color .15s,background .15s}
    .pw-toggle:hover{color:#fff;background:rgba(255,255,255,.08)}

    /* Password strength (register) */
    .meter{display:flex;gap:5px;margin-top:9px}
    .meter i{flex:1;height:4px;border-radius:99px;background:rgba(255,255,255,.1);transition:background .2s}
    .meter-txt{font-size:12px;color:var(--faint);margin-top:6px;min-height:16px}

    /* Buttons */
    .auth-btn{
      position:relative;overflow:hidden;margin-top:22px;min-height:52px;padding:14px;border:none;border-radius:14px;
      display:flex;align-items:center;justify-content:center;gap:10px;font-size:15px;font-weight:800;color:#fff;cursor:pointer;
      background:linear-gradient(135deg,var(--sapphire),var(--sapphire-2));
      box-shadow:0 14px 32px -8px rgba(37,99,235,.65);transition:transform .2s var(--ease),box-shadow .2s var(--ease);
    }
    .auth-btn::after{content:'';position:absolute;top:0;bottom:0;left:0;width:40%;background:linear-gradient(105deg,transparent,rgba(255,255,255,.3),transparent);transform:translateX(-140%) skewX(-18deg);pointer-events:none;animation:sweep 4.6s ease-in-out infinite 2.4s;will-change:transform}
    @keyframes sweep{0%{transform:translateX(-140%) skewX(-18deg)}35%,100%{transform:translateX(340%) skewX(-18deg)}}
    .auth-btn:hover{transform:translate3d(0,-2px,0);box-shadow:0 20px 40px -8px rgba(37,99,235,.8)}
    .auth-btn:active{transform:scale(.98)}
    .auth-btn:disabled{opacity:.75;cursor:wait;transform:none}
    .auth-btn i{transition:transform .2s var(--ease)}
    .auth-btn:hover i.fa-sign-in-alt,.auth-btn:hover i.fa-paper-plane{transform:translateX(3px)}

    .auth-link{background:none;border:none;color:#93B4FF;font-size:13.5px;font-weight:600;margin-top:16px;cursor:pointer;text-align:center;padding:6px;transition:color .15s}
    .auth-link:hover:not(:disabled){color:var(--gold)}
    .auth-link:disabled{color:var(--faint);cursor:not-allowed}
    .auth-note{font-size:13.5px;color:var(--soft);line-height:1.65;text-align:center;margin-bottom:4px}
    .auth-note b{color:#fff}
    .auth-hint{margin-top:16px;font-size:12.5px;text-align:center;color:var(--faint);line-height:1.65}

    .auth-foot{position:relative;z-index:2;text-align:center;margin-top:18px;font-size:12px;color:var(--faint);letter-spacing:.3px}

    /* Toasts (used by mobile.js) */
    .toast-host{position:fixed;left:0;right:0;top:calc(10px + env(safe-area-inset-top));z-index:9999;display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none;padding:0 12px}

    :focus-visible{outline:3px solid rgba(59,130,246,.6);outline-offset:2px}

    @media (max-width:480px){
      body.auth-body{align-items:flex-start;padding-top:70px}
      .auth-card{padding:28px 20px 24px;border-radius:24px}
      .wall{grid-template-columns:repeat(5,1fr);inset:-40%}
      .auth-form input{font-size:16px}
    }
    @media (max-height:640px){a.auth-logo{flex-direction:row;justify-content:center}.logo-icon{width:52px;height:52px}.logo-text{align-items:flex-start}}
    @media (prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
    .paused *,.paused *::before,.paused *::after{animation-play-state:paused!important}
  </style>
</head>
<body class="auth-body">

  <!-- Background (static layers + light particle canvas) -->
  <div class="bg" aria-hidden="true">
    <div class="wall-move"><div class="wall" id="wall"></div></div>
    <div class="shade"></div>
    <div class="orb o1"></div><div class="orb o2"></div>
    <canvas id="fx"></canvas>
    <div class="vig"></div>
  </div>
  <div class="gw" id="gw1" aria-hidden="true"><i class="g1"></i></div>
  <div class="gw" id="gw2" aria-hidden="true"><i class="g2"></i></div>

  <a href="home.php" class="home-link" data-hover><i class="fas fa-arrow-left"></i> Home</a>

  <div class="auth-wrap">
    <div class="auth-card">

      <a href="home.php" class="auth-logo" title="Back to home">
        <div class="logo-icon"><img src="logo.png" alt="PYM logo"/></div>
        <div class="logo-text">
          <span class="logo-title">PYM</span>
          <span class="logo-sub">Attendance System</span>
        </div>
      </a>

      <div class="auth-tabs" id="authTabs">
        <span class="tab-ind" id="tabInd"></span>
        <button type="button" class="auth-tab active" data-tab="login">Login</button>
        <button type="button" class="auth-tab" data-tab="register">Register</button>
      </div>

      <div id="authMessage" class="auth-message"></div>

      <!-- LOGIN FORM -->
      <form id="loginForm" class="auth-form active">
        <label>Username or Gmail</label>
        <div class="field"><i class="fas fa-user ic"></i>
          <input type="text" name="username" placeholder="Username or yourname@gmail.com" autocapitalize="none" autocomplete="username" required/>
        </div>

        <label>Password</label>
        <div class="field"><i class="fas fa-lock ic"></i>
          <input type="password" name="password" placeholder="Enter password" autocomplete="current-password" required/>
        </div>

        <button type="submit" class="auth-btn" data-hover>
          <i class="fas fa-sign-in-alt"></i> Log In
        </button>
        <button type="button" class="auth-link" id="forgotLink">Forgot password?</button>
      </form>

      <!-- REGISTER FORM -->
      <form id="registerForm" class="auth-form">
        <label>Full name</label>
        <div class="field"><i class="fas fa-id-card ic"></i>
          <input type="text" name="full_name" placeholder="Juan Dela Cruz" autocomplete="name" required/>
        </div>

        <label>Username</label>
        <div class="field"><i class="fas fa-at ic"></i>
          <input type="text" name="username" placeholder="Choose a username" autocapitalize="none" autocomplete="username" required/>
        </div>

        <label>Gmail</label>
        <div class="field"><i class="fas fa-envelope ic"></i>
          <input type="email" name="email" placeholder="yourname@gmail.com" autocomplete="email" required/>
        </div>

        <label>Password</label>
        <div>
          <div class="field"><i class="fas fa-lock ic"></i>
            <input type="password" name="password" placeholder="At least 6 characters" autocomplete="new-password" required/>
          </div>
          <div class="meter" id="pwMeter" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
          <div class="meter-txt" id="pwMeterTxt"></div>
        </div>

        <button type="submit" class="auth-btn" data-hover>
          <i class="fas fa-paper-plane"></i> Send Verification Code
        </button>

        <p class="auth-hint">We'll email a code to confirm your Gmail. After that, the Secretariat must approve your account (new accounts are Slot Leaders). One Gmail = one account.</p>
      </form>

      <!-- VERIFY CODE -->
      <form id="codeForm" class="auth-form">
        <p class="auth-note">Enter the 6-digit code sent to<br/><b id="codeEmail">—</b></p>
        <label>Verification code</label>
        <input type="text" name="code" class="code-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••" required/>
        <button type="submit" class="auth-btn" data-hover><i class="fas fa-check"></i> Verify &amp; Register</button>
        <button type="button" class="auth-link" id="resendBtn">Resend code</button>
        <button type="button" class="auth-link" id="codeBack" style="margin-top:0">Back</button>
      </form>

      <!-- FORGOT PASSWORD -->
      <form id="forgotForm" class="auth-form">
        <p class="auth-note">Enter your registered Gmail and we'll send you a reset code.</p>
        <label>Gmail</label>
        <div class="field"><i class="fas fa-envelope ic"></i>
          <input type="email" name="email" placeholder="yourname@gmail.com" autocomplete="email" required/>
        </div>
        <button type="submit" class="auth-btn" data-hover><i class="fas fa-paper-plane"></i> Send Reset Code</button>
        <button type="button" class="auth-link" data-back>Back to login</button>
      </form>

      <!-- RESET PASSWORD -->
      <form id="resetForm" class="auth-form">
        <p class="auth-note">Enter the code from your email and choose a new password.</p>
        <label>Reset code</label>
        <input type="text" name="code" class="code-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••" required/>
        <label>New password</label>
        <div class="field"><i class="fas fa-lock ic"></i>
          <input type="password" name="password" placeholder="At least 6 characters" autocomplete="new-password" required/>
        </div>
        <label>Confirm password</label>
        <div class="field"><i class="fas fa-lock ic"></i>
          <input type="password" name="confirm" placeholder="Repeat new password" autocomplete="new-password" required/>
        </div>
        <button type="submit" class="auth-btn" data-hover><i class="fas fa-key"></i> Reset Password</button>
        <button type="button" class="auth-link" data-back>Back to login</button>
      </form>

    </div>
    <p class="auth-foot">Parish Youth Ministry · MQAP</p>
  </div>

  <script>
  (function () {
    'use strict';
    var small = matchMedia('(max-width:760px)').matches;
    var reduce = matchMedia('(prefers-reduced-motion:reduce)').matches;

    /* ---------- Static tile wall (built once) ---------- */
    var items = [['Sunday 7:00 AM','Normal Serve','fa-sun'],['Special Event','Youth Activity','fa-star'],['Sunday 9:00 AM','Normal Serve','fa-church'],['Recollection','Retreat','fa-dove'],['Saturday 5:00 PM','Normal Serve','fa-moon'],['Bible Study','Fellowship','fa-book-bible'],['Sunday 5:00 PM','Normal Serve','fa-cross'],['Youth Day','Celebration','fa-people-group'],['Sunday 7:00 PM','Normal Serve','fa-candle-holder'],['Outreach','Service','fa-hand-holding-heart'],['Choir Night','Ministry','fa-music'],['Sunday 11:00 AM','Normal Serve','fa-bell']];
    var pal = [['#1E3A8A','#2563EB'],['#7C5A06','#F5B82E'],['#0F1F5C','#4F46E5'],['#064E3B','#059669'],['#4C1D95','#7C3AED'],['#7F1D1D','#DC2626'],['#0C4A6E','#0EA5E9'],['#78350F','#D97706']];
    var n = small ? 15 : 27, h = '';
    for (var i = 0; i < n; i++) {
      var it = items[(i*5 + Math.floor(i/9)) % items.length], c = pal[(i*3 + Math.floor(i/9)*2) % pal.length];
      h += '<div class="tile" style="background:linear-gradient(155deg,'+c[1]+','+c[0]+');margin-top:'+(((i%9)%2)?26:0)+'px"><i class="fas '+it[2]+'"></i>'+it[0]+'<small>'+it[1]+'</small></div>';
    }
    document.getElementById('wall').innerHTML = h;

    /* ---------- Cursor light: small glow locked to the pointer, big glow lightly eased ---------- */
    var gw1 = document.getElementById('gw1'), gw2 = document.getElementById('gw2');
    var W = innerWidth, H = innerHeight, px = W/2, py = H/2, gx = px, gy = py, active = false, moved = false;
    function show(on) { gw1.classList.toggle('on', on); gw2.classList.toggle('on', on); }
    function cls(add, c) { gw1.classList[add ? 'add' : 'remove'](c); gw2.classList[add ? 'add' : 'remove'](c); }
    function onMove(e) { px = e.clientX; py = e.clientY; active = true; if (!moved) { moved = true; gx = px; gy = py; } show(true); }
    if (window.PointerEvent) addEventListener('pointermove', onMove, { passive: true });
    else { addEventListener('mousemove', onMove, { passive: true }); addEventListener('touchmove', function (e) { var t = e.touches[0]; if (t) onMove(t); }, { passive: true }); }
    document.addEventListener('mouseleave', function () { active = false; show(false); });
    document.addEventListener('mouseenter', function () { if (moved) show(true); });
    addEventListener('touchstart', function (e) { var t = e.touches[0]; if (t) onMove(t); }, { passive: true });
    addEventListener('touchend', function () { show(false); active = false; }, { passive: true });
    addEventListener('mousedown', function () { cls(true, 'down'); });
    addEventListener('mouseup', function () { cls(false, 'down'); });
    document.addEventListener('mouseover', function (e) { if (e.target.closest && e.target.closest('[data-hover],.auth-tab,.auth-link')) cls(true, 'hov'); });
    document.addEventListener('mouseout', function (e) { if (e.target.closest && e.target.closest('[data-hover],.auth-tab,.auth-link')) cls(false, 'hov'); });

    /* ---------- Particles: 1x canvas, few dots ---------- */
    var cv = document.getElementById('fx'), ctx = cv.getContext('2d', { alpha: true }), parts = [];
    function build(count) { parts = []; for (var i = 0; i < count; i++) parts.push({ x: Math.random()*W, y: Math.random()*H, vx: (Math.random()-.5)*.3, vy: (Math.random()-.5)*.3, r: Math.random()*1.3+.8, g: Math.random() < .45 }); }
    function resize() {
      W = innerWidth; H = innerHeight;
      cv.width = W; cv.height = H;
      build(small ? 16 : Math.min(30, Math.round(W*H/50000)));
    }
    var rt; addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(resize, 150); });
    resize();

    var LINK = small ? 85 : 115, LINK2 = LINK*LINK, PR = 140, PR2 = PR*PR;
    function drawFX(k) {
      ctx.clearRect(0, 0, W, H);
      var len = parts.length, i, j, p, q, dx, dy, d2;
      for (i = 0; i < len; i++) {
        p = parts[i];
        if (active) { dx = p.x - px; dy = p.y - py; d2 = dx*dx + dy*dy; if (d2 < PR2 && d2 > 1) { var d = Math.sqrt(d2), f = (1 - d/PR)*.05*k; p.vx += dx/d*f; p.vy += dy/d*f; } }
        p.vx *= .985; p.vy *= .985;
        if (Math.abs(p.vx) < .06) p.vx += (Math.random()-.5)*.01;
        if (Math.abs(p.vy) < .06) p.vy += (Math.random()-.5)*.01;
        p.x += p.vx*k; p.y += p.vy*k;
        if (p.x < -8) p.x = W+8; else if (p.x > W+8) p.x = -8;
        if (p.y < -8) p.y = H+8; else if (p.y > H+8) p.y = -8;
      }
      ctx.lineWidth = .8; ctx.strokeStyle = 'rgba(147,180,255,.16)'; ctx.beginPath();
      for (i = 0; i < len; i++) { p = parts[i]; for (j = i+1; j < len; j++) { q = parts[j]; dx = p.x - q.x; if (dx > LINK || dx < -LINK) continue; dy = p.y - q.y; if (dx*dx + dy*dy < LINK2) { ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); } } }
      ctx.stroke();
      if (active) { ctx.strokeStyle = 'rgba(245,184,46,.34)'; ctx.beginPath(); for (i = 0; i < len; i++) { p = parts[i]; dx = p.x - px; dy = p.y - py; if (dx*dx + dy*dy < PR2*1.5) { ctx.moveTo(p.x, p.y); ctx.lineTo(px, py); } } ctx.stroke(); }
      ctx.fillStyle = 'rgba(147,180,255,.8)'; ctx.beginPath();
      for (i = 0; i < len; i++) { p = parts[i]; if (!p.g) { ctx.moveTo(p.x+p.r, p.y); ctx.arc(p.x, p.y, p.r, 0, 6.2832); } }
      ctx.fill();
      ctx.fillStyle = 'rgba(245,184,46,.9)'; ctx.beginPath();
      for (i = 0; i < len; i++) { p = parts[i]; if (p.g) { ctx.moveTo(p.x+p.r, p.y); ctx.arc(p.x, p.y, p.r, 0, 6.2832); } }
      ctx.fill();
    }

    var last = performance.now(), running = true, slow = 0;
    function frame(now) {
      if (!running) return;
      var dt = Math.min((now - last)/1000, .05); last = now;
      var k = dt*60;
      if (dt > .03) slow++; else slow = Math.max(0, slow-1);
      if (slow > 40 && parts.length > 10) { parts.length = Math.max(10, Math.floor(parts.length*.7)); slow = 0; }
      var e = 1 - Math.pow(1e-9, dt);
      gx += (px - gx)*e; gy += (py - gy)*e;
      if (moved) {
        gw1.style.transform = 'translate3d('+gx.toFixed(1)+'px,'+gy.toFixed(1)+'px,0)';
        gw2.style.transform = 'translate3d('+px+'px,'+py+'px,0)';
      }
      if (!reduce) drawFX(k);
      requestAnimationFrame(frame);
    }
    requestAnimationFrame(function (t) { last = t; frame(t); });
    document.addEventListener('visibilitychange', function () {
      document.documentElement.classList.toggle('paused', document.hidden);
      if (document.hidden) running = false; else if (!running) { running = true; last = performance.now(); requestAnimationFrame(frame); }
    });

    /* ---------- Sliding tab pill ---------- */
    var tabs = document.getElementById('authTabs'), ind = document.getElementById('tabInd');
    function place() {
      var a = tabs.querySelector('.auth-tab.active');
      if (!a) { ind.style.opacity = 0; return; }
      ind.style.opacity = 1; ind.style.width = a.offsetWidth + 'px'; ind.style.transform = 'translateX(' + a.offsetLeft + 'px)';
    }
    new MutationObserver(place).observe(tabs, { attributes: true, subtree: true, attributeFilter: ['class'] });
    addEventListener('resize', place); addEventListener('load', place);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(place);
    place();

    /* ---------- Password strength meter (register) ---------- */
    document.addEventListener('input', function (e) {
      var t = e.target;
      if (!t || t.name !== 'password' || !t.form || t.form.id !== 'registerForm') return;
      var v = t.value, s = 0;
      if (v.length >= 6) s++;
      if (v.length >= 10) s++;
      if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
      if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
      if (!v) s = 0;
      var cols = ['#EF4444', '#F59E0B', '#3B82F6', '#10B981'], bars = document.querySelectorAll('#pwMeter i');
      bars.forEach(function (b, i) { b.style.background = i < s ? cols[Math.max(0, s-1)] : ''; });
      document.getElementById('pwMeterTxt').textContent = v ? ['Too short', 'Okay', 'Good', 'Strong'][Math.max(0, s-1)] || 'Too short' : '';
    });
  })();
  </script>
  <script src="app.js"></script>
  <script src="auth.js"></script>
  <script src="mobile.js"></script>
</body>
</html>
