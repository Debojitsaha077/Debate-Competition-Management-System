<?php
$admin_area = true;
require_once '../config/bootstrap.php';
require_admin();

$cid  = current_competition_id();
$comp = current_competition();

$title = 'Scores & Rankings · ' . ($comp['name'] ?? '');
$error = '';

function recalc_rank(int $assignment_id): void {
    $rows = q(
        'SELECT team_id FROM team_totals WHERE assignment_id=? ORDER BY total_points DESC, team_id',
        [$assignment_id]
    );
    foreach ($rows as $i => $r) {
        exec_sql(
            'UPDATE team_totals SET team_rank=? WHERE team_id=? AND assignment_id=?',
            [$i + 1, $r['team_id'], $assignment_id]
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'score') {
            exec_sql(
                'INSERT INTO score_entry(speaker_score, participant_id, team_id, assignment_id) 
                 VALUES(?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE speaker_score=VALUES(speaker_score), team_id=VALUES(team_id)',
                [
                    floatval($_POST['speaker_score']),
                    intval($_POST['participant_id']),
                    intval($_POST['team_id']),
                    intval($_POST['assignment_id'])
                ]
            );
            flash('success', 'Speaker score saved.');
        } elseif ($action === 'total') {
            $assignmentId = intval($_POST['assignment_id']);
            exec_sql(
                'INSERT INTO team_totals(team_id, assignment_id, total_points, team_rank) 
                 VALUES(?, ?, ?, NULL) 
                 ON DUPLICATE KEY UPDATE total_points=VALUES(total_points)',
                [
                    intval($_POST['team_id']),
                    $assignmentId,
                    floatval($_POST['total_points'])
                ]
            );
            recalc_rank($assignmentId);
            flash('success', 'Official team total saved and team ranks recalculated.');
        }
        redirect('scores.php');
    } catch (Throwable $e) {
        $error = 'Score recording failed: ' . $e->getMessage();
    }
}

$assignments = q('SELECT a.assignment_id, a.date, r.round_name, r.round_num 
                  FROM debate_assignment a 
                  JOIN debate_round r ON r.round_id = a.round_id 
                  WHERE r.competition_id = ?
                  ORDER BY a.date, r.round_num, a.assignment_id', [$cid]);

$debaters = q('SELECT d.participant_id, p.name, d.team_id, t.team_name 
               FROM debater d 
               JOIN participant p ON p.participant_id = d.participant_id 
               LEFT JOIN team t ON t.team_id = d.team_id 
               WHERE t.competition_id = ? OR d.participant_id IN (SELECT participant_id FROM competition_registration WHERE competition_id = ?)
               ORDER BY p.name', [$cid, $cid]);

$teams = q('SELECT * FROM team WHERE competition_id = ? ORDER BY team_name', [$cid]);

$scores = q('SELECT se.*, p.name, t.team_name, r.round_name, se.assignment_id 
             FROM score_entry se 
             JOIN participant p ON p.participant_id = se.participant_id 
             JOIN team t ON t.team_id = se.team_id 
             JOIN debate_assignment a ON a.assignment_id = se.assignment_id 
             JOIN debate_round r ON r.round_id = a.round_id 
             WHERE r.competition_id = ?
             ORDER BY se.assignment_id, p.name', [$cid]);

$totals = q('SELECT tt.*, t.team_name, r.round_name 
             FROM team_totals tt 
             JOIN team t ON t.team_id = tt.team_id 
             JOIN debate_assignment a ON a.assignment_id = tt.assignment_id 
             JOIN debate_round r ON r.round_id = a.round_id 
             WHERE r.competition_id = ?
             ORDER BY tt.assignment_id, tt.team_rank', [$cid]);

include '../partials/header.php';
?>

<div class="section-head">
  <div>
    <span class="kicker">Admin &middot; Tabulation &middot; <?= e($comp['host_name'] ?? '') ?></span>
    <h1>Scores &amp; Official Team Totals &middot; <?= e($comp['name'] ?? '') ?></h1>
    <p class="muted">Enter debater speaker points and official team debate totals for <strong><?= e($comp['name'] ?? '') ?></strong>. Team ranks are automatically computed within each assignment.</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="flash error"><?= e($error) ?></div>
<?php endif; ?>

<!-- INPUT FORMS -->
<div class="grid grid-2">
  <!-- SPEAKER SCORE FORM -->
  <div class="card">
    <h3>Enter / Update Speaker Score</h3>
    <form method="post" action="scores.php" style="margin-top: 14px;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="score">

      <div class="form-row">
        <label class="label">Debate Assignment</label>
        <select name="assignment_id" required>
          <option value="">Select Assignment</option>
          <?php foreach ($assignments as $a): ?>
            <option value="<?= $a['assignment_id'] ?>">#<?= $a['assignment_id'] ?> &middot; Round <?= $a['round_num'] ?> (<?= e($a['round_name']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row" style="margin-top: 10px;">
        <label class="label">Debater</label>
        <select name="participant_id" required>
          <option value="">Select Debater</option>
          <?php foreach ($debaters as $d): ?>
            <option value="<?= $d['participant_id'] ?>"><?= e($d['name']) ?><?= $d['team_name'] ? ' &middot; ' . e($d['team_name']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row" style="margin-top: 10px;">
        <label class="label">Team</label>
        <select name="team_id" required>
          <option value="">Select Team</option>
          <?php foreach ($teams as $t): ?>
            <option value="<?= $t['team_id'] ?>"><?= e($t['team_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row" style="margin-top: 10px;">
        <label class="label">Speaker Score (0 - 100)</label>
        <input type="number" step="0.01" min="0" max="100" name="speaker_score" placeholder="e.g. 78.50" required>
      </div>

      <button class="btn" type="submit" style="margin-top: 16px;">Save Speaker Score</button>
    </form>
  </div>

  <!-- TEAM TOTAL FORM -->
  <div class="card">
    <h3>Enter / Update Team Total Points</h3>
    <form method="post" action="scores.php" style="margin-top: 14px;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="total">

      <div class="form-row">
        <label class="label">Debate Assignment</label>
        <select name="assignment_id" required>
          <option value="">Select Assignment</option>
          <?php foreach ($assignments as $a): ?>
            <option value="<?= $a['assignment_id'] ?>">#<?= $a['assignment_id'] ?> &middot; Round <?= $a['round_num'] ?> (<?= e($a['round_name']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row" style="margin-top: 10px;">
        <label class="label">Team</label>
        <select name="team_id" required>
          <option value="">Select Team</option>
          <?php foreach ($teams as $t): ?>
            <option value="<?= $t['team_id'] ?>"><?= e($t['team_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row" style="margin-top: 10px;">
        <label class="label">Official Total Points</label>
        <input type="number" step="0.01" min="0" name="total_points" placeholder="e.g. 155.00" required>
      </div>

      <button class="btn" type="submit" style="margin-top: 16px;">Save Team Total &amp; Compute Rank</button>
    </form>
  </div>
</div>

<!-- DATA TABLES -->
<div class="section grid grid-2">
  <!-- SPEAKER SCORES TABLE -->
  <div class="card">
    <h3>Published Speaker Scores (<?= count($scores) ?>)</h3>
    <div class="table-wrap" style="margin-top: 12px;">
      <table class="table">
        <thead>
          <tr>
            <th>Assignment</th>
            <th>Debater</th>
            <th>Team</th>
            <th>Score</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($scores as $s): ?>
            <tr>
              <td>#<?= e($s['assignment_id']) ?> &middot; <span class="muted"><?= e($s['round_name']) ?></span></td>
              <td><strong><?= e($s['name']) ?></strong></td>
              <td><?= e($s['team_name']) ?></td>
              <td><span class="badge green"><strong><?= e($s['speaker_score']) ?></strong></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$scores): ?>
            <tr>
              <td colspan="4" class="empty">No individual speaker scores recorded yet for this tournament.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- TEAM TOTALS TABLE -->
  <div class="card">
    <h3>Official Team Totals &amp; Ranks</h3>
    <div class="table-wrap" style="margin-top: 12px;">
      <table class="table">
        <thead>
          <tr>
            <th>Assignment</th>
            <th>Team</th>
            <th>Points</th>
            <th>Rank</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($totals as $tot): ?>
            <tr>
              <td>#<?= e($tot['assignment_id']) ?> &middot; <span class="muted"><?= e($tot['round_name']) ?></span></td>
              <td><strong><?= e($tot['team_name']) ?></strong></td>
              <td><strong><?= e($tot['total_points']) ?></strong></td>
              <td>
                <span class="badge <?= $tot['team_rank'] == 1 ? 'green' : 'gray' ?>">
                  Rank #<?= e($tot['team_rank'] ?? '—') ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$totals): ?>
            <tr>
              <td colspan="4" class="empty">No team totals submitted yet for this tournament.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../partials/footer.php'; ?>
