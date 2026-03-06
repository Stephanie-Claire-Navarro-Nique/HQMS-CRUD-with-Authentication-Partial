<?php
require 'auth.php';
require 'db.php';
requireLogin();

// Staff only — admins go to their own profile
if (isAdmin()) { header('Location: profile_admin.php'); exit; }

$theme  = getTheme();
$user   = currentUser();
$pdo    = getPDO();
$action = $_GET['action'] ?? 'view';

// Load current staff's own data
$stmt = $pdo->prepare("SELECT id, fname, email, created_at FROM staff WHERE id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

$errors = [];

// EDIT PROFILE
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname    = trim($_POST['fname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!preg_match('/^[A-Za-z\s]{3,}$/', $fname)) $errors[] = 'Name must be letters only, min 3 chars.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Invalid email address.';
    if ($password !== '' && strlen($password) < 6)   $errors[] = 'New password must be at least 6 characters.';
    if ($password !== $confirm)                       $errors[] = 'Passwords do not match.';

    $chk = $pdo->prepare('SELECT id FROM staff WHERE email = ? AND id != ? LIMIT 1');
    $chk->execute([$email, $user['id']]);
    if ($chk->fetch()) $errors[] = 'Email already in use.';

    if (empty($errors)) {
        if ($password !== '') {
            $pdo->prepare('UPDATE staff SET fname=?, email=?, password=? WHERE id=?')
                ->execute([$fname, $email, password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        } else {
            $pdo->prepare('UPDATE staff SET fname=?, email=? WHERE id=?')
                ->execute([$fname, $email, $user['id']]);
        }
        $_SESSION['user']['fname'] = $fname;
        $_SESSION['user']['email'] = $email;
        flashSet('success', 'Profile updated successfully.');
        header('Location: profile_staff.php'); exit;
    }
    $profile = ['id' => $user['id'], 'fname' => $fname, 'email' => $email, 'created_at' => $profile['created_at']];
}

$flash_ok  = flashGet('success');
$flash_err = flashGet('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>My Profile – CareQueue</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="style.css"/>
</head>
<body class="app-page <?= $theme==='dark'?'dark':'' ?>">

<header class="site-header">
  <div class="brand">Care<em>Queue</em></div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="nav-user">Hello, <strong><?= sanitize($user['fname']) ?></strong></span>
    <a href="profile_staff.php?theme=<?= $theme==='dark'?'light':'dark' ?>" class="theme-toggle">
      <i class="fa-solid <?= $theme==='dark'?'fa-sun':'fa-moon' ?>"></i> <?= $theme==='dark'?'Light':'Dark' ?>
    </a>
    <a href="dashboard.php" class="nav-btn"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <a href="patients.php" class="nav-btn"><i class="fa-solid fa-table-list"></i> Patients</a>
    <a href="profile_staff.php" class="nav-btn active"><i class="fa-solid fa-user"></i> Profile</a>
    <a href="logout.php" class="nav-btn danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</header>

<main class="main">

  <h2 class="page-title"><i class="fa-solid fa-user"></i> My Profile</h2>
  <p class="page-sub">View and update your account information.</p>

  <?php if($flash_ok): ?><div class="alert-ok"><i class="fa-solid fa-circle-check"></i> <?= sanitize($flash_ok) ?></div><?php endif; ?>
  <?php if($flash_err): ?><div class="alert-err"><?= sanitize($flash_err) ?></div><?php endif; ?>
  <?php if(!empty($errors)): ?>
  <div class="alert-err"><ul><?php foreach($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="profile-grid">

    <!-- Profile Card -->
    <div class="panel profile-card">
      <div class="profile-banner" style="background: linear-gradient(135deg, var(--mint-d), var(--rose-d));"></div>
      <div class="profile-card-body">
        <div class="profile-avatar" style="background: linear-gradient(135deg, var(--mint-d), var(--rose-d)); box-shadow: 0 6px 20px rgba(80,180,120,.3);">
          <i class="fa-solid fa-user"></i>
        </div>
        <h3 class="profile-name"><?= sanitize($profile['fname']) ?></h3>
        <p class="profile-email"><?= sanitize($profile['email']) ?></p>
        <span class="badge bg-secondary" style="font-size:.78rem;padding:5px 14px;">Staff</span>
        <div class="profile-meta">
          <div class="profile-meta-row">
            <span><i class="fa-solid fa-calendar fa-xs"></i> Member Since</span>
            <strong><?= date('F j, Y', strtotime($profile['created_at'])) ?></strong>
          </div>
          <?php if(isset($_COOKIE['last_login'])): ?>
          <div class="profile-meta-row">
            <span><i class="fa-solid fa-clock fa-xs"></i> Last Login</span>
            <strong><?= sanitize($_COOKIE['last_login']) ?></strong>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Edit Form -->
    <div class="profile-right">
      <div class="panel">
        <div class="panel-head"><h3><i class="fa-solid fa-pen-to-square"></i> Edit Profile</h3></div>
        <div class="panel-body p24">
          <form method="POST" action="profile_staff.php?action=edit">
            <div class="field">
              <label><i class="fa-solid fa-user fa-xs"></i> Full Name</label>
              <input type="text" name="fname" value="<?= sanitize($profile['fname']) ?>"/>
            </div>
            <div class="field">
              <label><i class="fa-solid fa-envelope fa-xs"></i> Email Address</label>
              <input type="email" name="email" value="<?= sanitize($profile['email']) ?>"/>
            </div>
            <div class="field">
              <label><i class="fa-solid fa-lock fa-xs"></i> New Password
                <span style="font-size:.75rem;color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0;">(leave blank to keep current)</span>
              </label>
              <input type="password" name="password" placeholder="Enter new password"/>
            </div>
            <div class="field">
              <label><i class="fa-solid fa-shield-halved fa-xs"></i> Confirm New Password</label>
              <input type="password" name="confirm" placeholder="Repeat new password"/>
            </div>
            <div class="btn-row">
              <button type="submit" class="btn-main"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>

</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>