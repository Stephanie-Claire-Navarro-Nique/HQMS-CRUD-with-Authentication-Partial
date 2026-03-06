<?php
require 'auth.php';
require 'db.php';

if (isset($_SESSION['user'])) { header('Location: dashboard.php'); exit; }

$theme  = getTheme();
$errors = [];
$email  = '';

if (isset($_COOKIE['remember_email'])) $email = $_COOKIE['remember_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 6)
        $errors[] = 'Password must be at least 6 characters.';

    if (empty($errors)) {
        $pdo  = getPDO();
        $found = null;
        $role  = '';

        // Check admins table first
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['password'])) {
            $found = $row;
            $role  = 'admin';
        }

        // Check staff table if not found in admins
        if (!$found) {
            $stmt = $pdo->prepare('SELECT * FROM staff WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if ($row && password_verify($password, $row['password'])) {
                $found = $row;
                $role  = 'staff';
            }
        }

        if ($found) {
            $_SESSION['user'] = [
                'id'    => $found['id'],
                'fname' => $found['fname'],
                'email' => $found['email'],
                'role'  => $role,
            ];
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            setcookie('remember_email', $remember ? $email : '', $remember ? time()+60*60*24*30 : time()-3600, '/');
            setcookie('last_login', date('Y-m-d H:i:s'), time()+60*60*24*30, '/');
            header('Location: dashboard.php');
            exit;
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Login – CareQueue</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="style.css"/>
</head>
<body class="auth-page <?= $theme==='dark'?'dark':'' ?>">
<div class="blob blob-1"></div><div class="blob blob-2"></div><div class="blob blob-3"></div>

<header class="site-header auth-header">
  <div class="brand">Care<em>Queue</em></div>
  <div class="d-flex align-items-center gap-2">
    <a href="login.php?theme=<?= $theme==='dark'?'light':'dark' ?>" class="theme-toggle">
      <i class="fa-solid <?= $theme==='dark'?'fa-sun':'fa-moon' ?>"></i> <?= $theme==='dark'?'Light':'Dark' ?>
    </a>
    <a href="signup.php" class="header-pill"><i class="fa-solid fa-user-plus"></i> Sign Up</a>
  </div>
</header>

<div class="auth-wrap">
  <div class="gradient-bar"></div>
  <div class="auth-card">
    <div class="icon-wrap"><i class="fa-solid fa-hospital"></i></div>
    <h1 class="auth-title">Welcome Back</h1>
    <p class="auth-sub">Login to the Hospital Queue Portal</p>

    <?php if (!empty($errors)): ?>
    <div class="alert-err">
      <ul><?php foreach($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <?php if (isset($_COOKIE['last_login'])): ?>
    <div class="alert-ok"><i class="fa-solid fa-clock fa-xs"></i> Last login: <?= sanitize($_COOKIE['last_login']) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="field">
        <label><i class="fa-solid fa-envelope fa-xs"></i> Email Address</label>
        <input type="email" name="email" placeholder="you@hospital.com" value="<?= sanitize($email) ?>"/>
      </div>
      <div class="field">
        <label><i class="fa-solid fa-lock fa-xs"></i> Password</label>
        <input type="password" name="password" placeholder="Your password"/>
      </div>
      <div class="check-row">
        <input  type="checkbox" name="remember" id="rem" <?= isset($_COOKIE['remember_email'])?'checked':'' ?>/>
        <label  for="rem">Remember my email</label>
      </div>
      <button type="submit" class="btn-main">Login <i class="fa-solid fa-arrow-right-to-bracket"></i></button>
    </form>
    <div class="divider">or</div>
    <p class="link-row">Don't have an account? <a href="signup.php">Sign Up here</a></p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
