<?php
date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) session_start();

function requireLogin(string $redirect = 'login.php'): void {
    if (!isset($_SESSION['user'])) {
        header("Location: $redirect");
        exit;
    }
}

function requireAdmin(string $redirect = 'dashboard.php'): void {
    requireLogin();
    if ($_SESSION['user']['role'] !== 'admin') {
        header("Location: $redirect");
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function isAdmin(): bool {
    return ($_SESSION['user']['role'] ?? '') === 'admin';
}

function getTheme(): string {
    $theme = $_GET['theme'] ?? ($_COOKIE['ui_theme'] ?? 'light');
    if (in_array($theme, ['light','dark'])) {
        setcookie('ui_theme', $theme, time() + 60*60*24*30, '/');
    }
    return $theme;
}

function flashSet(string $key, string $msg): void {
    $_SESSION['flash'][$key] = $msg;
}

function flashGet(string $key): string {
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function generateQueueNo(string $dept): string {
    return strtoupper($dept) . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
}

function generateQueueNoFromCounter(PDO $pdo, int $deptId, string $deptCode): string {
    // Auto-reset if the last reset was before today
    $pdo->prepare("
        UPDATE queue_counters
        SET counter = 0, last_reset = CURDATE()
        WHERE dept_id = ? AND last_reset < CURDATE()
    ")->execute([$deptId]);

    // Increment and fetch atomically
    $pdo->prepare("
        INSERT INTO queue_counters (dept_id, counter, last_reset)
        VALUES (?, 1, CURDATE())
        ON DUPLICATE KEY UPDATE counter = counter + 1
    ")->execute([$deptId]);

    $num = (int)$pdo->prepare("SELECT counter FROM queue_counters WHERE dept_id = ?")
                    ->execute([$deptId]) ? 0 : 0;
    $stmt = $pdo->prepare("SELECT counter FROM queue_counters WHERE dept_id = ?");
    $stmt->execute([$deptId]);
    $num = (int)$stmt->fetchColumn();

    return strtoupper($deptCode) . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
}

function resetAllQueueCounters(PDO $pdo): void {
    $pdo->exec("UPDATE queue_counters SET counter = 0, last_reset = CURDATE()");
}

function resetDeptQueueCounter(PDO $pdo, int $deptId): void {
    $pdo->prepare("UPDATE queue_counters SET counter = 0, last_reset = CURDATE() WHERE dept_id = ?")
        ->execute([$deptId]);
}
