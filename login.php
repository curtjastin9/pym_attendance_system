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
  </style>
</head>
<body class="auth-body">

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

  <script src="app.js"></script>
  <script src="auth.js"></script>
  <script src="mobile.js"></script>
</body>
</html>
