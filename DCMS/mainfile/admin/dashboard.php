<?php
$admin_area = true;
require_once '../config/bootstrap.php';
require_admin();

$cid  = current_competition_id();
$comp = current_competition();

$title = 'Admin Dashboard · ' . ($comp['name'] ?? 'Tournaments');

$stats = [
    'participants'  => one('SELECT COUNT(*) c FROM competition_registration WHERE competition_id=?', [$cid])['c'] ?? 0,
    'teams'         => one('SELECT COUNT(*) c FROM team WHERE competition_id=?', [$cid])['c'] ?? 0,
    'rounds'        => one('SELECT COUNT(*) c FROM debate_round WHERE competition_id=?', [$cid])['c'] ?? 0,
    'rooms'         => one('SELECT COUNT(*) c FROM room WHERE competition_id=?', [$cid])['c'] ?? 0,
    'assignments'   => one('SELECT COUNT(*) c FROM debate_assignment a JOIN debate_round r ON r.round_id = a.round_id WHERE r.competition_id=?', [$cid])['c'] ?? 0,
    'scores'        => one('SELECT COUNT(*) c FROM score_entry se JOIN debate_assignment a ON a.assignment_id = se.assignment_id JOIN debate_round r ON r.round_id = a.round_id WHERE r.competition_id=?', [$cid])['c'] ?? 0,
    'pending_users' => one('SELECT COUNT(*) c FROM auth_user WHERE status="pending"')['c'] ?? 0,
    'total_comps'   => one('SELECT COUNT(*) c FROM competition')['c'] ?? 0,
];

$up = q('SELECT a.assignment_id, a.date, a.motion, a.motion_status, r.round_name, r.round_num, ro.number, ro.building,
         GROUP_CONCAT(CONCAT(t.team_name, " (", pi.side, ")") ORDER BY pi.side SEPARATOR " vs ") matchup 
         FROM debate_assignment a 
         JOIN debate_round r ON r.round_id = a.round_id 
         JOIN room ro ON ro.room_id = a.room_id 
         LEFT JOIN participates_in pi ON pi.assignment_id = a.assignment_id 
         LEFT JOIN team t ON t.team_id = pi.team_id 
         WHERE r.competition_id = ?
         GROUP BY a.assignment_id 
         ORDER BY a.date, r.start_time 
         LIMIT 8', [$cid]);

include '../partials/header.php';
?>

<!-- ACTIVE TOURNAMENT BANNER -->
<div class="card" style="margin-bottom: 24px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(139, 92, 246, 0.06)); border: 1px solid var(--accent); padding: 20px 24px;">
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
        <span class="badge" style="background: var(--accent); color: #fff; font-weight: 700;">Selected Tournament</span>
        <span class="badge <?= ($comp['status'] ?? '') === 'active' ? 'green' : 'yellow' ?>"><?= ucfirst(e($comp['status'] ?? 'Active')) ?></span>
      </div>
      <h1 style="margin: 0; font-size: 1.6rem; color: var(--text-primary);"><?= e($comp['name'] ?? 'All Tournaments') ?></h1>
      <p class="muted" style="margin: 4px 0 0; font-size: 14px;">
        Host: <strong style="color: var(--accent-bright);"><?= e($comp['host_name'] ?? 'Global') ?></strong> &middot;
        Venue: <strong><?= e($comp['host_name'] ?? '') ?> Campus</strong> &middot;
        Dates: <strong><?= !empty($comp['start_date']) ? date('d M Y', strtotime($comp['start_date'])) : 'TBD' ?> &ndash; <?= !empty($comp['end_date']) ? date('d M Y', strtotime($comp['end_date'])) : 'TBD' ?></strong>
      </p>
    </div>
    <div class="actions">
      <a href="competitions.php" class="btn secondary small">Switch / All Tournaments (<?= $stats['total_comps'] ?>)</a>
      <a href="assignments.php" class="btn small">Schedule Debate</a>
    </div>
  </div>
</div>

<div class="section-head">
  <div>
    <span class="kicker">Tournament Stats &middot; <?= e($comp['host_name'] ?? '') ?></span>
    <h2>Active Tournament Metrics</h2>
    <p class="muted">Live overview of registrations, venues, teams, debate rounds, assignments, and score submissions for this tournament.</p>
  </div>
  <div class="actions">
    <a href="users.php" class="btn small <?= $stats['pending_users'] > 0 ? 'warning' : 'secondary' ?>" style="<?= $stats['pending_users'] > 0 ? 'background: var(--warning-bg); color: var(--warning); border: 1px solid rgba(251,191,36,0.3);' : '' ?>">
      Review Users (<?= (int)$stats['pending_users'] ?> Pending)
    </a>
    <a href="motions.php" class="btn small secondary">Release Motions</a>
  </div>
</div>

<div class="grid grid-5" style="grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));">
  <div class="card stat">
    <div class="label">Registered Debaters</div>
    <div class="value"><?= e($stats['participants']) ?></div>
  </div>
  <div class="card stat">
    <div class="label">Competing Teams</div>
    <div class="value"><?= e($stats['teams']) ?></div>
  </div>
  <div class="card stat">
    <div class="label">Debate Rounds</div>
    <div class="value"><?= e($stats['rounds']) ?></div>
  </div>
  <div class="card stat">
    <div class="label">University Rooms</div>
    <div class="value"><?= e($stats['rooms']) ?></div>
  </div>
  <div class="card stat">
    <div class="label">Debate Matches</div>
    <div class="value"><?= e($stats['assignments']) ?></div>
  </div>
  <div class="card stat">
    <div class="label">Scores Logged</div>
    <div class="value"><?= e($stats['scores']) ?></div>
  </div>
  <div class="card stat <?= $stats['pending_users'] > 0 ? 'warning-accent' : '' ?>">
    <div class="label">Pending Users</div>
    <div class="value"><?= e($stats['pending_users']) ?></div>
  </div>
</div>

<div class="section">
  <div class="section-head">
    <h2>Recent Debate Assignments &middot; <?= e($comp['name'] ?? '') ?></h2>
    <a href="assignments.php" class="btn small secondary">View all &amp; create</a>
  </div>

  <div class="card table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Round</th>
          <th>Date</th>
          <th>Venue Room</th>
          <th>Matchup</th>
          <th>Motion Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($up as $a): ?>
          <tr>
            <td><strong>#<?= e($a['assignment_id']) ?></strong></td>
            <td><?= e($a['round_name']) ?> <span class="badge gray">R<?= $a['round_num'] ?></span></td>
            <td><?= e(date('d M Y', strtotime($a['date']))) ?></td>
            <td><?= e($a['number']) ?> <span class="muted">(<?= e($a['building']) ?>)</span></td>
            <td><?= e($a['matchup'] ?? 'Matchup pending') ?></td>
            <td>
              <?php if ($a['motion_status'] === 'released'): ?>
                <span class="badge green">Released</span>
              <?php else: ?>
                <span class="badge yellow">Draft</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$up): ?>
          <tr>
            <td colspan="6" class="empty">No assignments created yet for this tournament. <a href="assignments.php" style="color:var(--accent-bright); font-weight:600;">Create one now</a>.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../partials/footer.php'; ?>