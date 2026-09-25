<?php
$admin_area = true;
require_once '../config/bootstrap.php';
require_admin();

$cid  = current_competition_id();
$comp = current_competition();

$title = 'Manage Assignments · ' . ($comp['name'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete_assignment') {
        $delId = (int)($_POST['assignment_id'] ?? 0);
        if ($delId > 0) {
            try {
                exec_sql('DELETE FROM debate_assignment WHERE assignment_id=?', [$delId]);
                flash('success', 'Assignment #' . $delId . ' deleted.');
                redirect('assignments.php');
            } catch (Throwable $e) {
                $error = 'Failed to delete assignment: ' . $e->getMessage();
            }
        }
    } else {
        $date         = trim($_POST['date'] ?? '');
        $round_id     = (int)($_POST['round_id'] ?? 0);
        $room_id      = (int)($_POST['room_id'] ?? 0);
        $prop_team    = (int)($_POST['prop_team'] ?? 0);
        $opp_team     = (int)($_POST['opp_team'] ?? 0);
        $motion       = trim($_POST['motion'] ?? '');
        $motionStatus = ($_POST['motion_status'] ?? 'draft') === 'released' ? 'released' : 'draft';
        $judges       = $_POST['judges'] ?? [];

        if ($date && $round_id && $room_id && $prop_team && $opp_team && $prop_team !== $opp_team) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO debate_assignment (date, room_id, round_id, motion, motion_status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$date, $room_id, $round_id, $motion ?: null, $motionStatus]);
                $assignment_id = (int)$pdo->lastInsertId();

                $stmtProp = $pdo->prepare("INSERT INTO participates_in (team_id, assignment_id, side) VALUES (?, ?, 'Proposition')");
                $stmtProp->execute([$prop_team, $assignment_id]);

                $stmtOpp = $pdo->prepare("INSERT INTO participates_in (team_id, assignment_id, side) VALUES (?, ?, 'Opposition')");
                $stmtOpp->execute([$opp_team, $assignment_id]);

                if (!empty($judges)) {
                    $stmtJudge = $pdo->prepare("INSERT INTO assignment_judge (assignment_id, participant_id) VALUES (?, ?)");
                    foreach ($judges as $j_id) {
                        $stmtJudge->execute([$assignment_id, (int)$j_id]);
                    }
                }

                $pdo->commit();
                flash('success', 'Debate Assignment #' . $assignment_id . ' created successfully for ' . ($comp['name'] ?? 'tournament') . '.');
                redirect('assignments.php');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Failed to create assignment: ' . $e->getMessage();
            }
        } else {
            $error = 'Please complete all required fields and ensure two distinct teams are selected.';
        }
    }
}

$rounds = q("SELECT * FROM debate_round WHERE competition_id = ? ORDER BY round_num ASC", [$cid]);
$rooms  = q("SELECT * FROM room WHERE competition_id = ? ORDER BY number ASC", [$cid]);
$teams  = q("SELECT * FROM team WHERE competition_id = ? ORDER BY team_name ASC", [$cid]);
$judges = q("SELECT j.participant_id, p.name FROM judge j JOIN participant p ON p.participant_id = j.participant_id ORDER BY p.name ASC");

$existingAssignments = q('SELECT a.assignment_id, a.date, a.motion, a.motion_status,
                          r.round_name, r.round_num,
                          ro.number AS room_number, ro.building,
                          GROUP_CONCAT(DISTINCT CONCAT(t.team_name, " (", pi.side, ")") ORDER BY pi.side SEPARATOR " vs ") AS matchup,
                          GROUP_CONCAT(DISTINCT jp.name ORDER BY jp.name SEPARATOR ", ") AS judge_names
                          FROM debate_assignment a
                          JOIN debate_round r ON r.round_id = a.round_id
                          JOIN room ro ON ro.room_id = a.room_id
                          LEFT JOIN participates_in pi ON pi.assignment_id = a.assignment_id
                          LEFT JOIN team t ON t.team_id = pi.team_id
                          LEFT JOIN assignment_judge aj ON aj.assignment_id = a.assignment_id
                          LEFT JOIN participant jp ON jp.participant_id = aj.participant_id
                          WHERE r.competition_id = ?
                          GROUP BY a.assignment_id
                          ORDER BY a.date DESC, r.round_num ASC, a.assignment_id ASC', [$cid]);

include '../partials/header.php';
?>

<div class="section-head">
  <div>
    <span class="kicker">Admin &middot; Scheduling &middot; <?= e($comp['host_name'] ?? '') ?></span>
    <h1>Debate Assignments &middot; <?= e($comp['name'] ?? '') ?></h1>
    <p class="muted">Schedule matches, assign competition rooms at <strong><?= e($comp['host_name'] ?? 'University') ?></strong>, set proposition &amp; opposition teams, assign judges, and configure motions.</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="flash error"><?= e($error) ?></div>
<?php endif; ?>

<!-- CREATE ASSIGNMENT FORM -->
<div class="card">
  <h3>Create New Debate Assignment (<?= e($comp['name'] ?? '') ?>)</h3>
  <form method="post" action="assignments.php" style="margin-top: 16px;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">

    <div class="form-grid">
      <div class="form-row">
        <label for="date">Debate Date *</label>
        <input type="date" id="date" name="date" required value="<?= date('Y-m-d') ?>">
      </div>

      <div class="form-row">
        <label for="round_id">Tournament Round *</label>
        <select id="round_id" name="round_id" required>
          <option value="">Select Round</option>
          <?php foreach ($rounds as $r): ?>
            <option value="<?= e($r['round_id']) ?>">Round <?= e($r['round_num']) ?> &middot; <?= e($r['round_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label for="room_id">Debate Room (<?= e($comp['host_name'] ?? 'Venue') ?>) *</label>
        <select id="room_id" name="room_id" required>
          <option value="">Select Room</option>
          <?php foreach ($rooms as $rm): ?>
            <option value="<?= e($rm['room_id']) ?>"><?= e($rm['number']) ?> (<?= e($rm['building']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label for="prop_team">Proposition Team (Government) *</label>
        <select id="prop_team" name="prop_team" required>
          <option value="">Select Proposition Team</option>
          <?php foreach ($teams as $t): ?>
            <option value="<?= e($t['team_id']) ?>"><?= e($t['team_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label for="opp_team">Opposition Team *</label>
        <select id="opp_team" name="opp_team" required>
          <option value="">Select Opposition Team</option>
          <?php foreach ($teams as $t): ?>
            <option value="<?= e($t['team_id']) ?>"><?= e($t['team_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label for="motion_status">Motion Visibility Status</label>
        <select id="motion_status" name="motion_status">
          <option value="draft">Draft (Hidden from participants until released)</option>
          <option value="released">Released (Visible on user schedule &amp; dashboard)</option>
        </select>
      </div>

      <div class="form-row full">
        <label>Assign Adjudicators / Judges</label>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; padding: 12px; background: var(--bg-surface); border: 1px solid var(--glass-border); border-radius: var(--radius-md);">
          <?php foreach ($judges as $j): ?>
            <label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 8px;">
              <input type="checkbox" name="judges[]" value="<?= e($j['participant_id']) ?>">
              <span><?= e($j['name']) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if (!$judges): ?>
            <span class="muted" style="font-size:13px;">No judges registered. Add judges in the Manage section.</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row full">
        <label for="motion">Debate Motion / Topic</label>
        <input type="text" id="motion" name="motion" placeholder="e.g. This house believes that Science is a blessing for Humanity">
      </div>
    </div>

    <div style="margin-top: 20px;">
      <button type="submit" class="btn">Create Assignment</button>
    </div>
  </form>
</div>

<!-- EXISTING ASSIGNMENTS LIST -->
<div class="section">
  <div class="section-head">
    <h2>Existing Assignments in <?= e($comp['name'] ?? 'Tournament') ?> (<?= count($existingAssignments) ?>)</h2>
  </div>

  <div class="card table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Round</th>
          <th>Date</th>
          <th>Room</th>
          <th>Matchup</th>
          <th>Judges</th>
          <th>Motion</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($existingAssignments as $a): ?>
          <tr>
            <td><strong>#<?= e($a['assignment_id']) ?></strong></td>
            <td><?= e($a['round_name']) ?><br><span class="badge gray">Round <?= e($a['round_num']) ?></span></td>
            <td><?= e(date('d M Y', strtotime($a['date']))) ?></td>
            <td><?= e($a['room_number']) ?><br><span class="muted" style="font-size:12px;"><?= e($a['building']) ?></span></td>
            <td><strong><?= e($a['matchup'] ?? 'Not set') ?></strong></td>
            <td><span class="muted" style="font-size:13px;"><?= e($a['judge_names'] ?? 'None assigned') ?></span></td>
            <td style="max-width: 200px;">
              <?php if (!empty($a['motion'])): ?>
                <span style="font-size: 13px;"><?= e(mb_strimwidth($a['motion'], 0, 50, '...')) ?></span>
              <?php else: ?>
                <span class="muted" style="font-size: 13px;">No motion set</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($a['motion_status'] === 'released'): ?>
                <span class="badge green">Released</span>
              <?php else: ?>
                <span class="badge yellow">Draft</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="actions">
                <a class="btn small secondary" href="motions.php" title="Edit Motion">Motion</a>
                <form method="post" action="assignments.php" onsubmit="return confirm('Delete Assignment #<?= $a['assignment_id'] ?> and all associated scores?');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete_assignment">
                  <input type="hidden" name="assignment_id" value="<?= $a['assignment_id'] ?>">
                  <button class="btn small danger" type="submit">Del</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$existingAssignments): ?>
          <tr>
            <td colspan="9" class="empty">No debate assignments created yet for this tournament.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../partials/footer.php'; ?>