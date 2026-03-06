<?php
require 'auth.php';
require 'db.php';
requireLogin();

$theme = getTheme();
$user  = currentUser();
$pdo   = getPDO();

$total   = $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
$waiting = $pdo->query("SELECT COUNT(*) FROM patients p JOIN statuses s ON p.status_id=s.id WHERE s.name='Waiting'")->fetchColumn();
$served  = $pdo->query("SELECT COUNT(*) FROM patients p JOIN statuses s ON p.status_id=s.id WHERE s.name='Served'")->fetchColumn();
$depts   = $pdo->query('SELECT d.code AS dept, COUNT(*) AS cnt FROM patients p JOIN departments d ON p.dept_id=d.id GROUP BY d.code ORDER BY cnt DESC')->fetchAll();

$flash_ok  = flashGet('success');
$flash_err = flashGet('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard – CareQueue</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="style.css"/>
</head>
<body class="app-page <?= $theme==='dark'?'dark':'' ?>">

<header class="site-header">
  <div class="brand">Care<em>Queue</em></div>
  <div class="d-flex align-items-center gap-2">
    <span class="nav-user">Hello, <strong><?= sanitize($user['fname']) ?></strong>
      <?php if(isAdmin()): ?><span class="badge bg-danger ms-1" style="font-size:.65rem;">Admin</span><?php endif; ?>
    </span>
    <a href="dashboard.php?theme=<?= $theme==='dark'?'light':'dark' ?>" class="theme-toggle">
      <i class="fa-solid <?= $theme==='dark'?'fa-sun':'fa-moon' ?>"></i> <?= $theme==='dark'?'Light':'Dark' ?>
    </a>
    <a href="patients.php" class="nav-btn"><i class="fa-solid fa-user-plus"></i> Patients</a>
    <?php if(isAdmin()): ?>
    <a href="manage_staff.php" class="nav-btn"><i class="fa-solid fa-users"></i> Staff</a>
    <?php endif; ?>
    <a href="<?= isAdmin()?'profile_admin.php':'profile_staff.php' ?>" class="nav-btn"><i class="fa-solid fa-<?= isAdmin()?'user-shield':'user' ?>"></i> Profile</a>
    <a href="logout.php" class="nav-btn danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</header>

<main class="main">
  <div class="greeting">
    <h2><i class="fa-solid fa-gauge-high"></i> Dashboard</h2>
    <p>Welcome back, <?= sanitize($user['fname']) ?>. Here's today's queue overview.</p>
    <?php if(isset($_COOKIE['last_login'])): ?>
    <small class="text-muted"><i class="fa-solid fa-clock fa-xs"></i> Last login: <?= sanitize($_COOKIE['last_login']) ?></small>
    <?php endif; ?>
  </div>

  <?php if($flash_ok): ?><div class="alert-ok"><?= sanitize($flash_ok) ?></div><?php endif; ?>
  <?php if($flash_err): ?><div class="alert-err"><?= sanitize($flash_err) ?></div><?php endif; ?>

  <div class="stats-grid">
    <div class="stat-card sc-1"><i class="fa-solid fa-users stat-icon"></i><div class="num"><?= $total ?></div><div class="lbl">Total Patients</div></div>
    <div class="stat-card sc-2"><i class="fa-solid fa-clock stat-icon"></i><div class="num"><?= $waiting ?></div><div class="lbl">Waiting</div></div>
    <div class="stat-card sc-3"><i class="fa-solid fa-circle-check stat-icon"></i><div class="num"><?= $served ?></div><div class="lbl">Served</div></div>
    <div class="stat-card sc-4"><i class="fa-solid fa-building-columns stat-icon"></i><div class="num"><?= count($depts) ?></div><div class="lbl">Departments Active</div></div>
  </div>

  <div class="content-grid">
    <div class="panel">
      <div class="panel-head">
        <h3><i class="fa-solid fa-building-user"></i> Patients by Department</h3>
      </div>
      <div class="panel-body">
        <?php if(empty($depts)): ?>
        <p class="text-muted small">No data yet.</p>
        <?php else: foreach($depts as $d): ?>
        <div class="dept-row">
          <div class="dept-meta">
            <span class="dept-name"><?= sanitize($d['dept']) ?></span>
            <span class="dept-count"><?= $d['cnt'] ?> patient<?= $d['cnt']>1?'s':'' ?></span>
          </div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $total?round($d['cnt']/$total*100):0 ?>%"></div></div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="sidebar">
      <div class="action-card">
        <p>Register a new patient to the queue</p>
        <a href="patients.php?action=create" class="action-btn"><i class="fa-solid fa-user-plus"></i> Register Patient</a>
      </div>
      <div class="panel">
        <div class="panel-head"><h3><i class="fa-solid fa-key"></i> Session Info</h3></div>
        <div class="panel-body">
          <div class="session-box">
            <p><i class="fa-solid fa-circle-info fa-xs"></i> Active Session</p>
            <div class="srow"><span>User ID</span><span>#<?= $user['id'] ?></span></div>
            <div class="srow"><span>Email</span><span><?= sanitize($user['email']) ?></span></div>
            <div class="srow"><span>Role</span><span><?= sanitize($user['role']) ?></span></div>
            <div class="srow"><span>Login Time</span><span><?= $_SESSION['login_time']??'—' ?></span></div>
            <div class="srow"><span>Theme</span><span><?= sanitize($theme) ?></span></div>
            <div class="srow">
              <span>Remember Cookie</span>
              <span><?= isset($_COOKIE['remember_email']) ? '<i class="fa-solid fa-circle-check" style="color:#5cc876;"></i> Active' : '<i class="fa-solid fa-circle-xmark" style="color:#d4708e;"></i> None' ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>