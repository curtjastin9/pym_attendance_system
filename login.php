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
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="icon" type="image/png" href="favicon.png"/>
<title>PYM Attendance – Login</title>
  <link rel="stylesheet" href="style.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
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
        <label>Username</label>
        <input type="text" name="username" placeholder="Enter username" required/>

        <label>Password</label>
        <input type="password" name="password" placeholder="Enter password" required/>

        <button type="submit" class="auth-btn">
          <i class="fas fa-sign-in-alt"></i> Log In
        </button>
      </form>

      <!-- REGISTER FORM -->
      <form id="registerForm" class="auth-form">
        <label>Full Name</label>
        <input type="text" name="full_name" placeholder="Juan Dela Cruz" required/>

        <label>Username</label>
        <input type="text" name="username" placeholder="Choose a username" required/>

        <label>Password</label>
        <input type="password" name="password" placeholder="At least 6 characters" required/>

        <label>Role</label>
        <select name="role">
          <option value="leader">Slot Leader</option>
          <option value="secretariat">Secretariat</option>
        </select>

        <button type="submit" class="auth-btn">
          <i class="fas fa-user-plus"></i> Create Account
        </button>

        <p class="auth-hint">New accounts require approval by the Secretariat before you can log in.</p>
      </form>

    </div>
  </div>

  <script src="app.js"></script>
</body>
</html>
