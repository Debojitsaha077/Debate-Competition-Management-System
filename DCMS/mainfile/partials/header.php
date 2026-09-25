<?php
require_once __DIR__ . '/../config/bootstrap.php';
$u       = current_user();
$title   = $title ?? 'Debate Event & Delegation Manager';
$isAdmin = isset($admin_area) && $admin_area === true;
$prefix  = $isAdmin ? '../' : '';
$currFile = basename($_SERVER['PHP_SELF'] ?? '');

$pendingCount = 0;
$allComps = [];
$currComp = null;

try {
    $allComps = all_competitions();
} catch (Throwable $e) {
    $allComps = [];
}

if ($u && $u['role'] === 'admin' && $isAdmin) {
    try {
        $pRow = one('SELECT COUNT(*) c FROM auth_user WHERE status="pending"');
        $pendingCount = (int)($pRow['c'] ?? 0);
        $currComp = current_competition();
    } catch (Throwable $e) {
        $pendingCount = 0;
    }
} elseif ($u && $u['role'] === 'user' && !$isAdmin) {
    $userActiveCompId = user_active_competition_id($u['participant_id'] ?? 0);
    $currComp = get_competition($userActiveCompId);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $prefix ?>assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="wrap nav">
    <a class="brand" href="<?= $prefix ?><?= $isAdmin ? 'dashboard.php' : 'dashboard.php' ?>">
      <div class="brand-logo animated-logo">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          <path d="M8 9h8"/>
          <path d="M8 13h5"/>
        </svg>
      </div>
      <span class="brand-title">Debate<span class="brand-accent">Manager</span></span>
    </a>

    <!-- ACTIVE COMPETITION SWITCHER (ADMIN) -->
    <?php if ($isAdmin && $u && $u['role'] === 'admin' && !empty($allComps)): ?>
      <div class="comp-switcher" style="display: flex; align-items: center; gap: 8px; margin-left: 12px;">
        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--accent-bright);">Tournament:</span>
        <form method="get" action="<?= e($_SERVER['PHP_SELF']) ?>" style="display: inline-block; margin: 0;">
          <?php foreach ($_GET as $gk => $gv): if ($gk !== 'competition_id'): ?>
            <input type="hidden" name="<?= e($gk) ?>" value="<?= e($gv) ?>">
          <?php endif; endforeach; ?>
          <select name="competition_id" onchange="this.form.submit()" style="padding: 4px 10px; font-size: 13px; border-radius: var(--radius-sm); background: var(--bg-surface); border: 1px solid var(--accent); color: var(--text-primary); font-weight: 600; cursor: pointer; max-width: 280px; text-overflow: ellipsis;">
            <?php foreach ($allComps as $ac): ?>
              <option value="<?= $ac['competition_id'] ?>" <?= ($currComp && $currComp['competition_id'] == $ac['competition_id']) ? 'selected' : '' ?>>
                <?= e($ac['name']) ?> (<?= e($ac['host_name']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    <?php endif; ?>

    <div class="nav-links">
      <?php if ($u && $u['role'] === 'user' && !$isAdmin): ?>
        <a href="dashboard.php" class="<?= $currFile === 'dashboard.php' ? 'active' : '' ?>">Overview</a>
        <a href="schedule.php" class="<?= in_array($currFile, ['schedule.php', 'debate.php']) ? 'active' : '' ?>">Schedule</a>
        <a href="results.php" class="<?= $currFile === 'results.php' ? 'active' : '' ?>">Results</a>
        <a href="profile.php" class="<?= $currFile === 'profile.php' ? 'active' : '' ?>">Profile</a>
        <a class="btn small" href="logout.php">Log out</a>
      <?php elseif ($u && $u['role'] === 'admin' && $isAdmin): ?>
        <a href="competitions.php" class="<?= $currFile === 'competitions.php' ? 'active' : '' ?>">Tournaments</a>
        <a href="dashboard.php" class="<?= $currFile === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="rounds.php" class="<?= $currFile === 'rounds.php' ? 'active' : '' ?>">Rounds</a>
        <a href="assignments.php" class="<?= $currFile === 'assignments.php' ? 'active' : '' ?>">Assignments</a>
        <a href="entities.php" class="<?= $currFile === 'entities.php' ? 'active' : '' ?>">Manage</a>
        <a href="motions.php" class="<?= $currFile === 'motions.php' ? 'active' : '' ?>">Motions</a>
        <a href="scores.php" class="<?= $currFile === 'scores.php' ? 'active' : '' ?>">Scores</a>
        <a href="users.php" class="<?= $currFile === 'users.php' ? 'active' : '' ?>">
          Users
          <?php if ($pendingCount > 0): ?>
            <span class="nav-badge"><?= $pendingCount ?></span>
          <?php endif; ?>
        </a>
        <a class="btn small" href="../logout.php">Log out</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="wrap page">
  <div class="flash-slot">
    <?php if ($f = pull_flash()): ?>
      <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endif; ?>
  </div>

