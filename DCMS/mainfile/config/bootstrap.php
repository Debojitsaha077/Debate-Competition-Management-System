<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/database.php';

/* ── Utility helpers ────────────────────────────────────── */

function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/* ── CSRF ───────────────────────────────────────────────── */

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

/* ── Auth helpers ───────────────────────────────────────── */

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user() || current_user()['role'] !== 'user') {
        redirect('login.php');
    }
    require_approved();
}

function require_approved(): void {
    $u = current_user();
    if (!$u) return;
    if (($u['status'] ?? '') !== 'approved') {
        redirect('pending.php');
    }
}

function require_admin(): void {
    if (!current_user() || current_user()['role'] !== 'admin') {
        redirect('../admin-login.php');
    }
}

/* ── Database query shortcuts (raw SQL, no ORM) ─────────── */

function q(string $sql, array $params = []): array {
    $s = db()->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
}

function one(string $sql, array $params = []): ?array {
    $s = db()->prepare($sql);
    $s->execute($params);
    $r = $s->fetch();
    return $r ?: null;
}

function exec_sql(string $sql, array $params = []): bool {
    $s = db()->prepare($sql);
    return $s->execute($params);
}

/* ── Competition helpers ────────────────────────────────── */

function all_competitions(): array {
    return q('SELECT c.*, i.name AS host_name, i.type AS host_type 
              FROM competition c 
              JOIN institution i ON i.inst_id = c.host_inst_id 
              ORDER BY c.status = "active" DESC, c.start_date DESC, c.name ASC');
}

function get_competition(int $id): ?array {
    return one('SELECT c.*, i.name AS host_name, i.type AS host_type 
                FROM competition c 
                JOIN institution i ON i.inst_id = c.host_inst_id 
                WHERE c.competition_id = ?', [$id]);
}

function current_competition_id(): int {
    if (!empty($_GET['competition_id'])) {
        $cid = (int)$_GET['competition_id'];
        if ($cid > 0 && get_competition($cid)) {
            $_SESSION['admin_competition_id'] = $cid;
            return $cid;
        }
    }
    if (!empty($_SESSION['admin_competition_id'])) {
        $cid = (int)$_SESSION['admin_competition_id'];
        if (get_competition($cid)) {
            return $cid;
        }
    }
    $first = one('SELECT competition_id FROM competition ORDER BY status="active" DESC, competition_id ASC LIMIT 1');
    $id = (int)($first['competition_id'] ?? 1);
    $_SESSION['admin_competition_id'] = $id;
    return $id;
}

function current_competition(): ?array {
    return get_competition(current_competition_id());
}

function user_competitions(int $participant_id): array {
    return q('SELECT c.*, i.name AS host_name, cr.registered_at 
              FROM competition_registration cr 
              JOIN competition c ON c.competition_id = cr.competition_id 
              JOIN institution i ON i.inst_id = c.host_inst_id 
              WHERE cr.participant_id = ? 
              ORDER BY c.status = "active" DESC, c.start_date DESC', [$participant_id]);
}

function user_active_competition_id(int $participant_id): int {
    if (!empty($_GET['comp_id'])) {
        $cid = (int)$_GET['comp_id'];
        if ($cid > 0 && get_competition($cid)) {
            $_SESSION['user_competition_id'] = $cid;
            return $cid;
        }
    }
    if (!empty($_SESSION['user_competition_id'])) {
        $cid = (int)$_SESSION['user_competition_id'];
        if (get_competition($cid)) {
            return $cid;
        }
    }
    // Default to the first registered competition for this user
    $firstReg = one('SELECT competition_id FROM competition_registration WHERE participant_id = ? ORDER BY competition_id ASC LIMIT 1', [$participant_id]);
    if ($firstReg) {
        $id = (int)$firstReg['competition_id'];
        $_SESSION['user_competition_id'] = $id;
        return $id;
    }
    // Fallback to active competition
    $first = one('SELECT competition_id FROM competition ORDER BY status="active" DESC, competition_id ASC LIMIT 1');
    $id = (int)($first['competition_id'] ?? 1);
    $_SESSION['user_competition_id'] = $id;
    return $id;
}

