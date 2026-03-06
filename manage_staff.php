<?php
require 'auth.php';
require 'db.php';
requireAdmin();

$theme      = getTheme();
$user       = currentUser();
$pdo        = getPDO();
$action     = $_GET['action'] ?? 'list';
$roleFilter = 'staff';

// DELETE
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM staff WHERE id = ?')->execute([$id]);
        flashSet('success', 'Staff account deleted.');
    }
    header('Location: manage_staff.php'); exit;
}

$errors   = [];
$editData = [];

if ($action === 'edit') {
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, fname, email, created_at, 'staff' AS role FROM staff WHERE id = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
    if (!$editData) { header('Location: manage_staff.php'); exit; }
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)($_POST['id'] ?? 0);
    $fname = trim($_POST['fname'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!preg_match('/^[A-Za-z\s]{3,}$/', $fname)) $errors[] = 'Name must be letters only, min 3 chars.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Invalid email address.';

    $chk = $pdo->prepare('SELECT id FROM staff WHERE email = ? AND id != ? LIMIT 1');
    $chk->execute([$email, $id]);
    if ($chk->fetch()) $errors[] = 'Email already in use.';

    if (empty($errors)) {
        $pdo->prepare('UPDATE staff SET fname=?, email=? WHERE id=?')->execute([$fname, $email, $id]);
        flashSet('success', 'Staff account updated.');
        header('Location: manage_staff.php'); exit;
    }
    $editData = ['id'=>$id,'fname'=>$fname,'email'=>$email,'role'=>$roleFilter];
}

// ADD STAFF
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname    = trim($_POST['fname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!preg_match('/^[A-Za-z\s]{3,}$/', $fname)) $errors[] = 'Name must be letters only, min 3 chars.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Invalid email address.';
    if (strlen($password) < 6)                       $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)                      $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $chk = $pdo->prepare('SELECT id FROM staff WHERE email = ? UNION SELECT id FROM admins WHERE email = ? LIMIT 1');
        $chk->execute([$email, $email]);
        if ($chk->fetch()) {
            $errors[] = 'Email already registered.';
        } else {
            $pdo->prepare('INSERT INTO staff (fname, email, password) VALUES (?,?,?)')
                ->execute([$fname, $email, password_hash($password, PASSWORD_DEFAULT)]);
            flashSet('success', 'New staff account created.');
            header('Location: manage_staff.php'); exit;
        }
    }
}

// LIST
$search     = trim($_GET['q'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 10;
$offset     = ($page - 1) * $perPage;

$where  = $search ? "WHERE fname LIKE ? OR email LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

$cnt = $pdo->prepare("SELECT COUNT(*) FROM staff $where");
$cnt->execute($params);
$totalCount = (int)$cnt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $pdo->prepare("SELECT id, fname, email, created_at, 'staff' AS role FROM staff $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

$flash_ok  = flashGet('success');
$flash_err = flashGet('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Manage Staff – CareQueue</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="style.css"/>
</head>
<body class="app-page <?= $theme==='dark'?'dark':'' ?>">

<header class="site-header">
  <div class="brand">Care<em>Queue</em></div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="nav-user">Hello, <strong><?= sanitize($user['fname']) ?></strong> <span class="badge bg-danger ms-1" style="font-size:.65rem;">Admin</span></span>
    <a href="manage_staff.php?theme=<?= $theme==='dark'?'light':'dark' ?>" class="theme-toggle">
      <i class="fa-solid <?= $theme==='dark'?'fa-sun':'fa-moon' ?>"></i> <?= $theme==='dark'?'Light':'Dark' ?>
    </a>
    <a href="dashboard.php" class="nav-btn"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <a href="patients.php" class="nav-btn"><i class="fa-solid fa-table-list"></i> Patients</a>
    <a href="manage_staff.php" class="nav-btn active"><i class="fa-solid fa-users"></i> Staff</a>
    <a href="profile_admin.php" class="nav-btn"><i class="fa-solid fa-user-shield"></i> Profile</a>
    <a href="logout.php" class="nav-btn danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</header>

<main class="main">

<?php if ($action === 'edit' && $editData): ?>

  <h2 class="page-title"><i class="fa-solid fa-user-pen"></i> Edit Staff Account</h2>
  <?php if (!empty($errors)): ?>
  <div class="alert-err"><ul><?php foreach($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <div class="panel" style="max-width:500px;margin:0 auto;">
    <div class="panel-head"><h3><i class="fa-solid fa-user"></i> Staff #<?= (int)$editData['id'] ?></h3></div>
    <div class="panel-body p24">
      <form method="POST" action="manage_staff.php?action=edit&id=<?= (int)$editData['id'] ?>">
        <input type="hidden" name="id" value="<?= (int)$editData['id'] ?>"/>
        <div class="field">
          <label><i class="fa-solid fa-user fa-xs"></i> Full Name</label>
          <input type="text" name="fname" value="<?= sanitize($editData['fname']) ?>"/>
        </div>
        <div class="field">
          <label><i class="fa-solid fa-envelope fa-xs"></i> Email</label>
          <input type="email" name="email" value="<?= sanitize($editData['email']) ?>"/>
        </div>
        <div class="btn-row">
          <button type="submit" class="btn-main"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
          <a href="manage_staff.php" class="btn-cancel">Cancel</a>
        </div>
      </form>
    </div>
  </div>

<?php elseif ($action === 'add'): ?>

  <h2 class="page-title" style="margin-bottom:20px;text-align:center;"><i class="fa-solid fa-user-plus"></i> Add New Staff</h2>
  <?php if (!empty($errors)): ?>
  <div class="alert-err" style="max-width:450px;margin:0 auto 16px;"><ul><?php foreach($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <div class="panel" style="max-width:450px;margin:0 auto;">
    <div class="panel-head"><h3><i class="fa-solid fa-users"></i> New Staff Account</h3></div>
    <div class="panel-body p24">
      <form method="POST" action="manage_staff.php?action=add">
        <div class="field">
          <label><i class="fa-solid fa-user fa-xs"></i> Full Name</label>
          <input type="text" name="fname" placeholder="e.g. Maria Santos" value="<?= sanitize($_POST['fname'] ?? '') ?>"/>
          <p class="hint">Letters only · minimum 3 characters</p>
        </div>
        <div class="field">
          <label><i class="fa-solid fa-envelope fa-xs"></i> Email Address</label>
          <input type="email" name="email" placeholder="staff@hospital.com" value="<?= sanitize($_POST['email'] ?? '') ?>"/>
        </div>
        <div class="field">
          <label><i class="fa-solid fa-lock fa-xs"></i> Password</label>
          <input type="password" name="password" placeholder="Minimum 6 characters"/>
        </div>
        <div class="field">
          <label><i class="fa-solid fa-shield-halved fa-xs"></i> Confirm Password</label>
          <input type="password" name="confirm" placeholder="Repeat password"/>
        </div>
        <div class="btn-row">
          <button type="submit" class="btn-main"><i class="fa-solid fa-user-plus"></i> Create Staff</button>
          <a href="manage_staff.php" class="btn-cancel">Cancel</a>
        </div>
      </form>
    </div>
  </div>

<?php else: // LIST ?>

  <div class="page-header">
    <h2 class="page-title mb-0"><i class="fa-solid fa-users"></i> Staff Accounts <span class="badge bg-secondary ms-2" style="font-size:.7rem;">Admin Only</span></h2>
    <a href="manage_staff.php?action=add" class="btn-main"><i class="fa-solid fa-user-plus"></i> Add Staff</a>
  </div>

  <?php if($flash_ok): ?><div class="alert-ok"><?= sanitize($flash_ok) ?></div><?php endif; ?>
  <?php if($flash_err): ?><div class="alert-err"><?= sanitize($flash_err) ?></div><?php endif; ?>

  <form class="search-bar" method="GET" action="manage_staff.php" class="mb-3 d-flex gap-2">
    <input type="text" name="q" placeholder="Search by name or email..." value="<?= sanitize($search) ?>" />
    <button class="btn-search" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
    <?php if($search): ?><a href="manage_staff.php" class="btn-cancel">Clear</a><?php endif; ?>
  </form>

  <div class="panel">
    <div class="panel-head">
      <h3><i class="fa-solid fa-users"></i> Staff Users</h3>
      <span style="font-size:.8rem;color:var(--muted);"><?= $totalCount ?> record<?= $totalCount!=1?'s':'' ?></span>
    </div>
    <div class="panel-body p0">
      <div class="table-wrap">
      <table >
        <thead>
          <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Registered</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach($users as $u): ?>
          <tr>
            <td><?= $u['id'] ?></td>
            <td><?= sanitize($u['fname']) ?></td>
            <td><?= sanitize($u['email']) ?></td>
            <td><span class="badge bg-secondary"><?= sanitize($u['role']) ?></span></td>
            <td style="font-size:.82rem;color:var(--muted);"><?= sanitize($u['created_at']) ?></td>
            <td>
              <div class="action-btns">
                <a href="manage_staff.php?action=edit&id=<?= $u['id'] ?>" class="btn-action btn-edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                <button class="btn-action btn-del" title="Delete"
                  onclick="if(confirm('Delete staff <?= sanitize($u['fname']) ?>?')) window.location='manage_staff.php?action=delete&id=<?= $u['id'] ?>'">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php if($totalPages > 1): ?>
      <div class="pagination">
        <?php if($page>1): ?><a href="manage_staff.php?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>" class="btn-action btn-sec"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
        <?php for($i=1;$i<=$totalPages;$i++): ?>
        <a href="manage_staff.php?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $i===$page?'btn-primary':'btn-outline-secondary' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if($page<$totalPages): ?><a href="manage_staff.php?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>" class="btn-action btn-sec"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>