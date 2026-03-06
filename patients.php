<?php
require 'auth.php';
require 'db.php';
requireLogin();

$theme  = getTheme();
$user   = currentUser();
$pdo    = getPDO();
$action = $_GET['action'] ?? 'list';

$depts    = $pdo->query('SELECT * FROM departments ORDER BY label')->fetchAll();
$statuses = $pdo->query('SELECT * FROM statuses ORDER BY id')->fetchAll();

function lookupId(array $rows, string $col, string $val): int {
    foreach ($rows as $r) { if ($r[$col] === $val) return (int)$r['id']; }
    return 0;
}

if ($action === 'delete' && isAdmin()) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM patients WHERE id = ?')->execute([$id]);
        flashSet('success', 'Patient record deleted.');
    }
    header('Location: patients.php'); exit;
}

// Reset queue counters (admin only)
if ($action === 'reset_queue' && isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptId = (int)($_POST['dept_id'] ?? 0);
    if ($deptId > 0) {
        resetDeptQueueCounter($pdo, $deptId);
        $dName = '';
        foreach ($depts as $d) { if ((int)$d['id'] === $deptId) { $dName = $d['code']; break; } }
        flashSet('success', "Queue counter for <strong>$dName</strong> reset to 000.");
    } else {
        resetAllQueueCounters($pdo);
        flashSet('success', 'All department queue counters have been reset to 000.');
    }
    header('Location: patients.php'); exit;
}

if ($action === 'status') {
    $id      = (int)($_GET['id'] ?? 0);
    $statVal = $_GET['val'] ?? '';
    $statId  = lookupId($statuses, 'name', $statVal);
    if ($id > 0 && $statId > 0) {
        $pdo->prepare('UPDATE patients SET status_id = ? WHERE id = ?')->execute([$statId, $id]);
    }
    header('Location: patients.php'); exit;
}

// --- CREATE ---
$errors = [];
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $mobile  = trim($_POST['mobile'] ?? '');
    $deptId  = (int)($_POST['dept_id'] ?? 0);
    $notes   = trim($_POST['notes'] ?? '');

    if (!preg_match('/^[A-Za-z\s]{3,}$/', $name))  $errors[] = 'Name must be letters only and at least 3 characters.';
    if (!preg_match('/^09\d{9}$/', $mobile))         $errors[] = 'Mobile must start with 09 and be 11 digits.';
    if ($deptId <= 0)                                $errors[] = 'Please select a department.';

    if (empty($errors)) {
        $dRow = $pdo->prepare('SELECT code FROM departments WHERE id = ?');
        $dRow->execute([$deptId]);
        $dCode = $dRow->fetchColumn() ?: 'Q';
        $qno   = generateQueueNoFromCounter($pdo, $deptId, $dCode);

        $pdo->prepare('INSERT INTO patients (queue_no, name, mobile, dept_id, status_id, notes, registered_by, registered_by_role) VALUES (?,?,?,?,1,?,?,?)')
            ->execute([$qno, $name, $mobile, $deptId, $notes, $user['id'], $user['role']]);
        flashSet('success', "Patient registered! Queue No: <strong>$qno</strong>");
        header('Location: patients.php'); exit;
    }
}

// --- EDIT (load) ---
$editData = [];
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT p.*, d.code AS dept_code, s.name AS status_name FROM patients p JOIN departments d ON p.dept_id=d.id JOIN statuses s ON p.status_id=s.id WHERE p.id=?');
        $stmt->execute([$id]);
        $editData = $stmt->fetch();
    }
    if (!$editData) { header('Location: patients.php'); exit; }
}

// --- EDIT (save) ---
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $mobile  = trim($_POST['mobile'] ?? '');
    $deptId  = (int)($_POST['dept_id'] ?? 0);
    $statId  = (int)($_POST['status_id'] ?? 0);
    $notes   = trim($_POST['notes'] ?? '');

    if (!preg_match('/^[A-Za-z\s]{3,}$/', $name))  $errors[] = 'Name must be letters only and at least 3 characters.';
    if (!preg_match('/^09\d{9}$/', $mobile))         $errors[] = 'Mobile must start with 09 and be 11 digits.';
    if ($deptId <= 0)                                $errors[] = 'Please select a department.';
    if ($statId <= 0)                                $errors[] = 'Please select a status.';

    if (empty($errors)) {
        $pdo->prepare('UPDATE patients SET name=?, mobile=?, dept_id=?, status_id=?, notes=? WHERE id=?')
            ->execute([$name, $mobile, $deptId, $statId, $notes, $id]);
        flashSet('success', 'Patient record updated.');
        header('Location: patients.php'); exit;
    }
    $editData = ['id'=>$id,'name'=>$name,'mobile'=>$mobile,'dept_id'=>$deptId,'status_id'=>$statId,'notes'=>$notes];
}

// --- LIST ---
$search     = trim($_GET['q'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 10;
$offset     = ($page - 1) * $perPage;

$where  = $search ? 'WHERE p.name LIKE ? OR p.queue_no LIKE ? OR d.code LIKE ? OR p.mobile LIKE ?' : '';
$params = $search ? ["%$search%","%$search%","%$search%","%$search%"] : [];

$cStmt = $pdo->prepare("SELECT COUNT(*) FROM patients p JOIN departments d ON p.dept_id=d.id $where");
$cStmt->execute($params);
$totalCount = (int)$cStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$lStmt = $pdo->prepare("SELECT p.*, d.code AS dept_code, d.label AS dept_label, s.name AS status_name,
    CASE WHEN p.registered_by_role='admin'
         THEN (SELECT a.fname FROM admins a WHERE a.id = p.registered_by)
         ELSE (SELECT st.fname FROM staff st WHERE st.id = p.registered_by)
    END AS reg_by
    FROM patients p
    JOIN departments d ON p.dept_id  = d.id
    JOIN statuses   s ON p.status_id = s.id
    $where ORDER BY p.id DESC LIMIT $perPage OFFSET $offset");
$lStmt->execute($params);
$patients = $lStmt->fetchAll();

$flash_ok  = flashGet('success');
$flash_err = flashGet('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title><?= $action==='create'?'Register Patient':($action==='edit'?'Edit Patient':'Patient Queue') ?> – CareQueue</title>
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
    <a href="patients.php?theme=<?= $theme==='dark'?'light':'dark' ?>" class="theme-toggle">
      <i class="fa-solid <?= $theme==='dark'?'fa-sun':'fa-moon' ?>"></i> <?= $theme==='dark'?'Light':'Dark' ?>
    </a>
    <a href="dashboard.php" class="nav-btn"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <?php if(isAdmin()): ?>
    <a href="manage_staff.php" class="nav-btn"><i class="fa-solid fa-users"></i> Staff</a><?php endif; ?>
    <a href="<?= isAdmin()?'profile_admin.php':'profile_staff.php' ?>" class="nav-btn"><i class="fa-solid fa-<?= isAdmin()?'user-shield':'user' ?>"></i> Profile</a>
    <a href="logout.php" class="nav-btn danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</header>

<main class="main">

<?php if ($action === 'create' || $action === 'edit'): ?>
  <h2 class="page-title" style="text-align:center;">
    <i class="fa-solid <?= $action==='edit'?'fa-pen-to-square':'fa-user-plus' ?>"></i>
    <?= $action==='edit'?'Edit Patient':'Register Patient' ?>
  </h2>
  <p class="page-sub" style="text-align:center;"><?= $action==='edit'?'Update the patient record below.':'Fill in the form to assign a queue number to a new patient.' ?></p>

  <?php if (!empty($errors)): ?>
  <div class="alert-err">
    <ul><?php foreach($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <div class="panel" style="max-width:450px;margin:0 auto;">
    <div class="panel-head"><h3><i class="fa-solid fa-pen-to-square"></i> Patient Details</h3></div>
    <div class="panel-body p24">
      <form method="POST" action="patients.php?action=<?= $action ?><?= $action==='edit'?'&id='.(int)($editData['id']??0):'' ?>">
        <?php if($action==='edit'): ?><input type="hidden" name="id" value="<?= (int)$editData['id'] ?>"/><?php endif; ?>

        <div class="field">
          <label><i class="fa-solid fa-user fa-xs"></i> Full Name</label>
          <input type="text" name="name" placeholder="e.g. Juan Dela Cruz" value="<?= sanitize($editData['name']??'') ?>"/>
          <p class="hint">Letters only · minimum 3 characters</p>
        </div>
        <div class="field">
          <label><i class="fa-solid fa-mobile-screen fa-xs"></i> Mobile Number</label>
          <input type="text" name="mobile" placeholder="09XXXXXXXXX" maxlength="11" value="<?= sanitize($editData['mobile']??'') ?>"/>
          <p class="hint">Must start with 09 · 11 digits total</p>
        </div>
        <div class="field">
          <label><i class="fa-solid fa-building fa-xs"></i> Department</label>
          <select name="dept_id">
            <option value="">— Select Department —</option>
            <?php foreach($depts as $d): ?>
            <option value="<?= $d['id'] ?>" <?= ($editData['dept_id']??0)==$d['id']?'selected':'' ?>>
              <?= sanitize($d['code']) ?> – <?= sanitize($d['label']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if($action==='edit'): ?>
        <div class="field">
          <label><i class="fa-solid fa-circle-dot fa-xs"></i> Status</label>
          <select name="status_id">
            <?php foreach($statuses as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ($editData['status_id']??0)==$s['id']?'selected':'' ?>><?= sanitize($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="field">
          <label><i class="fa-solid fa-notes-medical fa-xs"></i> Notes <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
          <textarea name="notes" rows="2" placeholder="Any additional notes..."><?= sanitize($editData['notes']??'') ?></textarea>
        </div>
        <div class="btn-row">
          <button type="submit" class="btn-main">
            <?= $action==='edit'?'<i class="fa-solid fa-floppy-disk"></i> Save Changes':'<i class="fa-solid fa-ticket"></i> Get Queue Number' ?>
          </button>
          <a href="patients.php" class="btn-cancel">Cancel</a>
        </div>
      </form>
    </div>
  </div>

<?php else: // LIST ?>
  <div class="page-header">
    <h2 class="page-title mb-0"><i class="fa-solid fa-table-list"></i> Patient Queue</h2>
    <a href="patients.php?action=create" class="btn-main"><i class="fa-solid fa-user-plus"></i> Register Patient</a>
  </div>

  <?php if($flash_ok): ?><div class="alert-ok"><?= $flash_ok ?></div><?php endif; ?>
  <?php if($flash_err): ?><div class="alert-err"><?= sanitize($flash_err) ?></div><?php endif; ?>

  <?php if(isAdmin()):
    // Load counters with dept info, auto-reset any stale ones first
    $pdo->exec("UPDATE queue_counters SET counter = 0, last_reset = CURDATE() WHERE last_reset < CURDATE()");
    $counters = $pdo->query("SELECT d.id, d.code, d.label, COALESCE(qc.counter,0) AS counter
        FROM departments d
        LEFT JOIN queue_counters qc ON qc.dept_id = d.id
        ORDER BY d.code")->fetchAll();
  ?>
  <div class="panel queue-counter-panel">
    <div class="panel-head">
      <h3><i class="fa-solid fa-arrow-up-1-9"></i> Queue Counters <span style="font-size:.73rem;color:var(--muted);font-weight:400;">(auto-resets daily)</span></h3>
      <form method="POST" action="patients.php?action=reset_queue"
        onsubmit="return confirm('Reset ALL department counters back to 000?');">
        <input type="hidden" name="dept_id" value="0"/>
        <button type="submit" class="btn-action btn-del" style="font-size:.78rem;padding:5px 12px;border-radius:8px;">
          <i class="fa-solid fa-rotate-left"></i> Reset All
        </button>
      </form>
    </div>
    <div class="panel-body p24">
      <div class="counter-grid">
        <?php foreach($counters as $c): ?>
        <div class="counter-card">
          <div class="counter-dept"><?= sanitize($c['code']) ?></div>
          <div class="counter-num"><?= str_pad($c['counter'], 3, '0', STR_PAD_LEFT) ?></div>
          <div class="counter-label"><?= sanitize($c['label']) ?></div>
          <form method="POST" action="patients.php?action=reset_queue"
            onsubmit="return confirm('Reset <?= sanitize($c['code']) ?> counter back to 000?');">
            <input type="hidden" name="dept_id" value="<?= $c['id'] ?>"/>
            <button type="submit" class="counter-reset-btn">
              <i class="fa-solid fa-rotate-left"></i> Reset
            </button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <form class="search-bar" method="GET" action="patients.php" class="mb-3 d-flex gap-2">
    <input type="text" name="q" placeholder="Search by name, queue #, dept, mobile..." value="<?= sanitize($search) ?>" />
    <button class="btn-search" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
    <?php if($search): ?><a href="patients.php" class="btn-cancel">Clear</a><?php endif; ?>
  </form>

  <div class="panel">
    <div class="panel-head">
      <h3><i class="fa-solid fa-table-list"></i> All Patients</h3>
      <span style="font-size:.8rem;color:var(--muted);"><?= $totalCount ?> record<?= $totalCount!=1?'s':'' ?></span>
    </div>
    <div class="panel-body p0">
      <?php if(empty($patients)): ?>
      <p class="no-data"><i class="fa-solid fa-inbox" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.4;"></i>No patients found.</p>
      <?php else: ?>
      <div class="table-wrap">
      <table >
        <thead>
          <tr>
            <th><i class="fa-solid fa-hashtag fa-xs"></i> Queue #</th>
            <th><i class="fa-solid fa-user fa-xs"></i> Name</th>
            <th><i class="fa-solid fa-phone fa-xs"></i> Mobile</th>
            <th><i class="fa-solid fa-building fa-xs"></i> Dept</th>
            <th><i class="fa-solid fa-clock fa-xs"></i> Time</th>
            <th><i class="fa-solid fa-circle-dot fa-xs"></i> Status</th>
            <th>By</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($patients as $p): ?>
          <tr>
            <td class="qno"><?= sanitize($p['queue_no']) ?></td>
            <td><?= sanitize($p['name']) ?></td>
            <td><?= sanitize($p['mobile']) ?></td>
            <td><span class="badge b-dept"><?= sanitize($p['dept_code']) ?></span></td>
            <td style="color:var(--muted);font-size:.82rem;"><?= sanitize($p['created_at']) ?></td>
            <td>
              <div class="dropdown">
                <span class="badge <?= $p['status_name']==='Waiting'?'b-wait':($p['status_name']==='Served'?'b-done':($p['status_name']==='In Progress'?'b-prog':'b-cancel')) ?> dropdown-toggle"
                  role="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <?= sanitize($p['status_name']) ?>
                </span>
                <ul class="dropdown-menu">
                  <?php foreach($statuses as $s): ?>
                  <li><a class="dropdown-item" href="patients.php?action=status&id=<?= $p['id'] ?>&val=<?= urlencode($s['name']) ?>"><?= sanitize($s['name']) ?></a></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </td>
            <td style="font-size:.8rem;color:var(--muted);"><?= sanitize($p['reg_by']??'—') ?></td>
            <td>
              <div class="action-btns">
                <a href="patients.php?action=edit&id=<?= $p['id'] ?>" class="btn-action btn-edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                <?php if(isAdmin()): ?>
                <button class="btn-action btn-del" title="Delete"
                  onclick="if(confirm('Delete <?= sanitize($p['name']) ?>?')) window.location='patients.php?action=delete&id=<?= $p['id'] ?>'">
                  <i class="fa-solid fa-trash"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>

      <?php if($totalPages > 1): ?>
      <div class="pagination">
        <?php if($page > 1): ?>
        <a href="patients.php?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>" class="btn-action btn-sec"><i class="fa-solid fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for($i=1;$i<=$totalPages;$i++): ?>
        <a href="patients.php?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $i===$page?'btn-primary':'btn-outline-secondary' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if($page < $totalPages): ?>
        <a href="patients.php?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>" class="btn-action btn-sec"><i class="fa-solid fa-chevron-right"></i></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>
</main>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>