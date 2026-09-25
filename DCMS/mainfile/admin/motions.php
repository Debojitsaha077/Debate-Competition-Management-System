<?php
$admin_area = true;
require_once '../config/bootstrap.php';
require_admin();

$cid  = current_competition_id();
$comp = current_competition();

$title = 'Motion Management · ' . ($comp['name'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);

    if ($assignmentId > 0) {
        try {
            if ($action === 'update_motion') {
                $motion = trim($_POST['motion'] ?? '');
                exec_sql('UPDATE debate_assignment SET motion=? WHERE assignment_id=?', [$motion ?: null, $assignmentId]);
                flash('success', 'Motion updated for Assignment #' . $assignmentId);
            } elseif ($action === 'release') {
                exec_sql('UPDATE debate_assignment SET motion_status="released" WHERE assignment_id=?', [$assignmentId]);
                flash('success', 'Motion has been released to participants for Assignment #' . $assignmentId);
            } elseif ($action === 'unrelease') {
                exec_sql('UPDATE debate_assignment SET motion_status="draft" WHERE assignment_id=?', [$assignmentId]);
                flash('info', 'Motion status reverted to Draft for Assignment #' . $assignmentId);
            }
            redirect('motions.php');
        } catch (Throwable $e) {
            $error = 'Failed to update motion: ' . $e->getMessage();
        }
    }
}

$assignments = q('SELECT a.assignment_id, a.date, a.motion, a.motion_status,
                  r.round_name, r.round_num, r.start_time,
                  ro.number AS room_number, ro.building,
                  GROUP_CONCAT(CONCAT(t.team_name, " [", pi.side, "]") ORDER BY pi.side SEPARATOR " vs ") AS matchup
                  FROM debate_assignment a
                  JOIN debate_round r ON r.round_id = a.round_id
                  JOIN room ro ON ro.room_id = a.room_id
                  LEFT JOIN participates_in pi ON pi.assignment_id = a.assignment_id
                  LEFT JOIN team t ON t.team_id = pi.team_id
                  WHERE r.competition_id = ?
                  GROUP BY a.assignment_id
                  ORDER BY a.date, r.round_num, a.assignment_id', [$cid]);

include '../partials/header.php';
?>

<div class="section-head">
  <div>
    <span class="kicker">Admin &middot; Tournament Control &middot; <?= e($comp['host_name'] ?? '') ?></span>
    <h1>Motion Management &amp; Release &middot; <?= e($comp['name'] ?? '') ?></h1>
    <p class="muted">Compose debate motions for each assignment in <strong><?= e($comp['name'] ?? '') ?></strong> and release them when the round begins. Released motions become visible to participants.</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="flash error"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid grid-2">
  <?php foreach ($assignments as $a): ?>
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; gap: 10px;">
        <div>
          <span class="badge gray">Assignment #<?= e($a['assignment_id']) ?></span>
          <span class="badge">Round <?= e($a['round_num']) ?> &middot; <?= e($a['round_name']) ?></span>
          <h3 style="margin-top: 6px; font-size: 1.15rem;"><?= e($a['matchup'] ?: 'Matchup pending') ?></h3>
        </div>
        <div>
          <?php if ($a['motion_status'] === 'released'): ?>
            <span class="badge green">Released to Public</span>
          <?php else: ?>
            <span class="badge yellow">Draft (Hidden)</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="muted" style="font-size: 13px; margin-bottom: 14px;">
        Date: <?= e(date('d M Y', strtotime($a['date']))) ?> &middot; Time: <?= e(date('h:i A', strtotime($a['start_time']))) ?> &middot; Room: <?= e($a['room_number']) ?> (<?= e($a['building']) ?>)
      </div>

      <form method="post" action="motions.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="update_motion">
        <input type="hidden" name="assignment_id" value="<?= $a['assignment_id'] ?>">

        <div class="form-row">
          <label class="label">Debate Motion</label>
          <textarea name="motion" rows="3" placeholder="e.g. This house would regulate generative artificial intelligence models..."><?= e($a['motion'] ?? '') ?></textarea>
        </div>

        <div class="actions" style="margin-top: 14px;">
          <button class="btn small" type="submit">Save Motion</button>
        </div>
      </form>

      <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
        <span class="muted" style="font-size: 13px;">
          Visibility: <?= $a['motion_status'] === 'released' ? '<strong style="color:var(--success)">Visible on User Dashboard</strong>' : '<span style="color:var(--warning)">Hidden from participants</span>' ?>
        </span>

        <?php if ($a['motion_status'] === 'released'): ?>
          <form method="post" action="motions.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="unrelease">
            <input type="hidden" name="assignment_id" value="<?= $a['assignment_id'] ?>">
            <button class="btn small secondary" type="submit">Hide Motion (Draft)</button>
          </form>
        <?php else: ?>
          <form method="post" action="motions.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="release">
            <input type="hidden" name="assignment_id" value="<?= $a['assignment_id'] ?>">
            <button class="btn small success" type="submit">Release Motion Now</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$assignments): ?>
    <div class="card empty" style="grid-column: 1 / -1;">
      No debate assignments found for this tournament. <a href="assignments.php" style="color:var(--accent-bright); font-weight:bold;">Create an assignment</a> first.
    </div>
  <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>
