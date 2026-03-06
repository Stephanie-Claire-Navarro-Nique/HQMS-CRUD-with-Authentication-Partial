<?php
require 'auth.php';
require 'db.php';

if (isset($_SESSION['user'])) { header('Location: dashboard.php'); exit; }

$theme   = getTheme();
$errors  = [];
$success = false;
$fname   = '';
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname    = trim($_POST['fname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    

    if (!preg_match('/^[A-Za-z\s]{3,}$/', $fname))
        $errors[] = 'Full name must be letters only and at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 6)
        $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)
        $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $pdo  = getPDO();
        $chk  = $pdo->prepare('SELECT id FROM staff WHERE email = ? UNION SELECT id FROM admins WHERE email = ? LIMIT 1');
        $chk->execute([$email, $email]);
        if ($chk->fetch()) {
            $errors[] = 'That email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $pdo->prepare('INSERT INTO staff (fname, email, password) VALUES (?, ?, ?)');
            $ins->execute([$fname, $email, $hash]);
            $success = true;
            $fname = $email = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Sign Up – CareQueue</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="style.css"/>
</head>
<body class="auth-page signup-page <?= $theme==='dark'?'dark':'' ?>">
<div class="blob blob-1"></div><div class="blob blob-2"></div><div class="blob blob-3"></div>

<header class="site-header auth-header">
  <div class="brand">Care<em>Queue</em></div>
  <a href="login.php" class="header-pill"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
</header>

<div class="auth-wrap wide">
  <div class="gradient-bar alt"></div>
  <div class="auth-card">
    <div class="tag-row">
      <span class="tag t-mint"><i class="fa-solid fa-hospital fa-xs"></i> Hospital</span>
      <span class="tag t-rose"><i class="fa-solid fa-list-ol fa-xs"></i> Queue System</span>
      <span class="tag t-lilac"><i class="fa-solid fa-id-badge fa-xs"></i> Users Portal</span>
    </div>
    <h1 class="auth-title">Create Account</h1>
    <p class="auth-sub">Register to manage the hospital queue</p>

    <?php if ($success): ?>
    <div class="alert-ok"><i class="fa-solid fa-circle-check"></i> Account created! <a href="login.php">Login now</a></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
    <div class="alert-err">
      <ul><?php foreach($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="signup.php">
      <div class="field">
        <label><i class="fa-solid fa-user fa-xs"></i> Full Name</label>
        <input type="text" name="fname" placeholder="e.g. Maria Santos" value="<?= sanitize($fname) ?>"/>
        <p class="hint">Letters only · at least 3 characters</p>
      </div>
      <div class="field">
        <label><i class="fa-solid fa-envelope fa-xs"></i> Email Address</label>
        <input type="email" name="email" placeholder="you@hospital.com" value="<?= sanitize($email) ?>"/>
      </div>
      <div class="field">
        <label><i class="fa-solid fa-lock fa-xs"></i> Password</label>
        <input type="password" name="password" placeholder="Minimum 6 characters"/>
      </div>
      <div class="field">
        <label><i class="fa-solid fa-shield-halved fa-xs"></i> Confirm Password</label>
        <input type="password" name="confirm" placeholder="Repeat your password"/>
      </div>
      <div class="field">
        <label><i class="fa-solid fa-user-tag fa-xs"></i> Role</label>
        <select name="role">
          <option value="staff">Staff</option>
          <option value="admin">Admin</option>
        </select>
        <p class="hint">Admins can delete records and manage all patients.</p>
      </div>
      <button type="submit" class="btn-main"><i class="fa-solid fa-user-plus"></i> Create Account</button>
    </form>
    <div class="divider">or</div>
    <p class="link-row">Already have an account? <a href="login.php">Login here</a></p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
