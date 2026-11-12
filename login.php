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
  <meta name="theme-color" content="#08101F"/>
  <link rel="icon" type="image/png" href="favicon.png"/>
<title>PYM Attendance – Login</title>
  <link rel="stylesheet" href="style.css"/>
  <link rel="stylesheet" href="mobile.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    .auth-link{background:none;border:none;color:var(--sapphire-mid);font-size:13px;font-weight:700;margin-top:14px;cursor:pointer;text-align:center;padding:4px}
    .auth-link:hover:not(:disabled){text-decoration:underline}
    .auth-link:disabled{color:var(--text-faint);cursor:not-allowed}
    .auth-note{font-size:13px;color:var(--text-mid);line-height:1.6;text-align:center}
    .auth-note b{color:var(--text-primary)}
    input.code-input{text-align:center;font-size:24px!important;font-weight:800;letter-spacing:8px}

    /* =========================================================
       Animations — transform / opacity only, so they stay smooth
       and cost almost nothing for the server or the device.
       ========================================================= */
    :root{--ease:cubic-bezier(.2,.8,.2,1)}

    /* Background: two soft orbs + a few floating dots */
    .bg-fx{position:absolute;inset:0;z-index:0;pointer-events:none;overflow:hidden}
    .bg-fx .orb{position:absolute;border-radius:50%;opacity:.7}
    .bg-fx .o1{width:560px;height:560px;left:-180px;top:-160px;background:radial-gradient(circle,rgba(201,146,10,.22),transparent 68%);animation:orbA 15s ease-in-out infinite alternate}
    .bg-fx .o2{width:640px;height:640px;right:-220px;bottom:-180px;background:radial-gradient(circle,rgba(37,99,235,.3),transparent 68%);animation:orbB 18s ease-in-out infinite alternate}
    .bg-fx .o3{width:380px;height:380px;left:55%;top:8%;background:radial-gradient(circle,rgba(124,58,237,.16),transparent 68%);animation:orbA 20s ease-in-out infinite alternate-reverse}
    .bg-fx i{position:absolute;bottom:-12px;border-radius:50%;background:var(--gold-bright);opacity:0;animation:floatUp linear infinite}
    @keyframes orbA{from{transform:translate3d(0,0,0) scale(1)}to{transform:translate3d(80px,60px,0) scale(1.15)}}
    @keyframes orbB{from{transform:translate3d(0,0,0) scale(1.05)}to{transform:translate3d(-70px,-50px,0) scale(.92)}}
    @keyframes floatUp{0%{transform:translate3d(0,0,0) scale(.6);opacity:0}12%{opacity:.7}85%{opacity:.4}100%{transform:translate3d(var(--dx,20px),-105vh,0) scale(1.2);opacity:0}}

    /* Card entrance (once) */
    .auth-wrap{animation:cardIn .8s var(--ease) both}
    @keyframes cardIn{from{opacity:0;transform:translate3d(0,28px,0) scale(.97)}to{opacity:1;transform:none}}
    .auth-card{position:relative;overflow:hidden}
    /* a single light sweep across the card after it appears */
    .auth-card::before{content:'';position:absolute;top:0;bottom:0;left:0;width:35%;background:linear-gradient(105deg,transparent,rgba(37,99,235,.08),transparent);transform:translateX(-160%) skewX(-18deg);animation:cardSweep 1.6s ease-out .7s 1 both;pointer-events:none}
    @keyframes cardSweep{to{transform:translateX(420%) skewX(-18deg)}}

    /* Logo: gentle float + glow */
    a.auth-logo{position:relative}
    .auth-logo .logo-icon{position:relative}
    .auth-logo .logo-icon::before{content:'';position:absolute;left:50%;top:50%;width:150%;height:150%;transform:translate(-50%,-50%);background:radial-gradient(circle,rgba(245,184,46,.38),transparent 65%);opacity:.5;animation:logoGlow 4.5s ease-in-out infinite;pointer-events:none}
    .auth-logo .logo-icon img{position:relative;animation:logoBob 6s ease-in-out infinite}
    @keyframes logoBob{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-5px,0)}}
    @keyframes logoGlow{0%,100%{opacity:.3;transform:translate(-50%,-50%) scale(.92)}50%{opacity:.75;transform:translate(-50%,-50%) scale(1.06)}}

    /* Tabs */
    .auth-tab{transition:background .28s var(--ease),color .28s var(--ease),box-shadow .28s var(--ease),transform .15s}
    .auth-tab:active{transform:scale(.97)}

    /* Every time a form is shown, its fields slide in one after another */
    .auth-form.active>*{animation:fieldIn .5s var(--ease) both}
    .auth-form.active>*:nth-child(1){animation-delay:.02s}
    .auth-form.active>*:nth-child(2){animation-delay:.07s}
    .auth-form.active>*:nth-child(3){animation-delay:.12s}
    .auth-form.active>*:nth-child(4){animation-delay:.17s}
    .auth-form.active>*:nth-child(5){animation-delay:.22s}
    .auth-form.active>*:nth-child(6){animation-delay:.27s}
    .auth-form.active>*:nth-child(7){animation-delay:.32s}
    .auth-form.active>*:nth-child(8){animation-delay:.37s}
    .auth-form.active>*:nth-child(n+9){animation-delay:.42s}
    @keyframes fieldIn{from{opacity:0;transform:translate3d(0,14px,0)}to{opacity:1;transform:none}}

    /* Inputs */
    .auth-form input,.auth-form select{transition:border-color .22s,box-shadow .22s,transform .22s var(--ease)}
    .auth-form input:focus{transform:translateY(-1px)}

    /* Buttons */
    .auth-btn{position:relative;overflow:hidden;transition:transform .25s var(--ease),box-shadow .25s var(--ease)}
    .auth-btn::after{content:'';position:absolute;top:0;bottom:0;left:0;width:40%;background:linear-gradient(105deg,transparent,rgba(255,255,255,.28),transparent);transform:translateX(-140%) skewX(-18deg);pointer-events:none}
    .auth-btn:hover::after{animation:btnSweep .8s ease-out}
    .auth-btn:active{transform:translateY(0) scale(.98)}
    .auth-btn:disabled{opacity:.75;cursor:wait;transform:none}
    @keyframes btnSweep{to{transform:translateX(340%) skewX(-18deg)}}
    .auth-link{transition:color .2s,transform .2s}
    .auth-link:hover:not(:disabled){transform:translateY(-1px)}

    /* Success / error message */
    .auth-message.success{animation:msgIn .4s var(--ease) both}
    .auth-message.error{animation:msgShake .45s ease both}
    @keyframes msgIn{from{opacity:0;transform:translate3d(0,-8px,0) scale(.98)}to{opacity:1;transform:none}}
    @keyframes msgShake{0%{opacity:0;transform:translate3d(0,-6px,0)}30%{opacity:1;transform:translate3d(-6px,0,0)}50%{transform:translate3d(5px,0,0)}70%{transform:translate3d(-3px,0,0)}100%{transform:none}}

    @media (max-width:480px){.bg-fx .o3{display:none}.bg-fx i:nth-child(n+8){display:none}}
    @media (prefers-reduced-motion:reduce){
      *,*::before,*::after{animation:none!important;transition:none!important}
    }
    .paused *,.paused *::before,.paused *::after{animation-play-state:paused!important}
  </style>
</head>
<body class="auth-body">

  <div class="bg-fx" id="bgFx" aria-hidden="true">
    <div class="orb o1"></div><div class="orb o2"></div><div class="orb o3"></div>
  </div>

  <div class="auth-wrap">
    <div class="auth-card">

      <a href="home.php" class="auth-logo" title="Back to home">
        <div class="logo-icon logo-img"><img src="logo.png" alt="PYM logo"/></div>
        <div class="logo-text">
          <span class="logo-title">PYM</span>
          <span class="logo-sub">Attendance System</span>
        </div>
      </a>

      <div class="auth-tabs">
        <button class="auth-tab active" data-tab="login">Login</button>
        <button class="auth-tab" data-tab="register">Register</button>
      </div>

      <div id="authMessage" class="auth-message"></div>

      <!-- LOGIN FORM -->
      <form id="loginForm" class="auth-form active">
        <label>Username or Gmail</label>
        <input type="text" name="username" placeholder="Username or yourname@gmail.com" autocapitalize="none" required/>

        <label>Password</label>
        <input type="password" name="password" placeholder="Enter password" required/>

        <button type="submit" class="auth-btn">
          <i class="fas fa-sign-in-alt"></i> Log In
        </button>
        <button type="button" class="auth-link" id="forgotLink">Forgot password?</button>
      </form>

      <!-- REGISTER FORM -->
      <form id="registerForm" class="auth-form">
        <label>Full Name</label>
        <input type="text" name="full_name" placeholder="Juan Dela Cruz" required/>

        <label>Username</label>
        <input type="text" name="username" placeholder="Choose a username" required/>

        <label>Gmail</label>
        <input type="email" name="email" placeholder="yourname@gmail.com" required/>

        <label>Password</label>
        <input type="password" name="password" placeholder="At least 6 characters" required/>

        <button type="submit" class="auth-btn">
          <i class="fas fa-paper-plane"></i> Send Verification Code
        </button>

        <p class="auth-hint">We'll email a code to confirm your Gmail. After that, the Secretariat must approve your account (new accounts are Slot Leaders). One Gmail = one account.</p>
      </form>

      <!-- VERIFY CODE -->
      <form id="codeForm" class="auth-form">
        <p class="auth-note">Enter the 6-digit code sent to<br/><b id="codeEmail">—</b></p>
        <label>Verification Code</label>
        <input type="text" name="code" class="code-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••" required/>
        <button type="submit" class="auth-btn"><i class="fas fa-check"></i> Verify &amp; Register</button>
        <button type="button" class="auth-link" id="resendBtn">Resend code</button>
        <button type="button" class="auth-link" id="codeBack">Back</button>
      </form>

      <!-- FORGOT PASSWORD -->
      <form id="forgotForm" class="auth-form">
        <p class="auth-note">Enter your registered Gmail and we'll send you a reset code.</p>
        <label>Gmail</label>
        <input type="email" name="email" placeholder="yourname@gmail.com" required/>
        <button type="submit" class="auth-btn"><i class="fas fa-paper-plane"></i> Send Reset Code</button>
        <button type="button" class="auth-link" data-back>Back to login</button>
      </form>

      <!-- RESET PASSWORD -->
      <form id="resetForm" class="auth-form">
        <p class="auth-note">Enter the code from your email and choose a new password.</p>
        <label>Reset Code</label>
        <input type="text" name="code" class="code-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••" required/>
        <label>New Password</label>
        <input type="password" name="password" placeholder="At least 6 characters" required/>
        <label>Confirm Password</label>
        <input type="password" name="confirm" placeholder="Repeat new password" required/>
        <button type="submit" class="auth-btn"><i class="fas fa-key"></i> Reset Password</button>
        <button type="button" class="auth-link" data-back>Back to login</button>
      </form>

    </div>
  </div>

  <script>
  (function(){
    // A few tiny floating dots in the background (cheap, transform/opacity only)
    var fx=document.getElementById('bgFx'), h='';
    for(var i=0;i<10;i++){
      var sz=3+Math.round(Math.random()*3);
      h+='<i style="left:'+Math.round(Math.random()*100)+'%;width:'+sz+'px;height:'+sz+'px;--dx:'+Math.round(Math.random()*80-40)+'px;animation-duration:'+(10+Math.random()*9).toFixed(1)+'s;animation-delay:'+(Math.random()*-16).toFixed(1)+'s"></i>';
    }
    fx.insertAdjacentHTML('beforeend',h);
    // Pause animations when the tab is hidden
    document.addEventListener('visibilitychange',function(){document.documentElement.classList.toggle('paused',document.hidden);});
  })();
  </script>
  <script src="app.js"></script>
  <script src="auth.js"></script>
  <script src="mobile.js"></script>
</body>
</html>
