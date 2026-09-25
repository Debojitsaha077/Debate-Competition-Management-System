<?php
$admin_area = true;
require_once '../config/bootstrap.php';
require_admin();

$cid  = current_competition_id();
$comp = current_competition();

$title = 'Manage Rounds · ' . ($comp['name'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            exec_sql(
                'INSERT INTO debate_round(competition_id, round_name, round_num, start_time) VALUES(?, ?, ?, ?)',
                [
                    $cid,
                    trim($_POST['round_name']),
                    intval($_POST['round_num']),
                    str_replace('T', ' ', trim($_POST['start_time']))
                ]
            );
            flash('success', 'New round added for ' . ($comp['name'] ?? 'tournament') . '.');
        } elseif ($action === 'edit') {
            exec_sql(
                'UPDATE debate_round SET round_name=?, round_num=?, start_time=? WHERE round_id=? AND competition_id=?',
                [
                    trim($_POST['round_name']),
                    intval($_POST['round_num']),
                    str_replace('T', ' ', trim($_POST['start_time'])),
                    intval($_POST['round_id']),
                    $cid
                ]
            );
            flash('success', 'Round updated.');
        } elseif ($action === 'delete') {
            exec_sql('DELETE FROM debate_round WHERE round_id=? AND competition_id=?', [intval($_POST['round_id']), $cid]);
            flash('success', 'Round deleted.');
        }
        redirect('rounds.php');
    } catch (Throwable $e) {
        $error = 'Round operation failed: ' . $e->getMessage();
    }
}

$rows = q('SELECT r.*, COUNT(a.assignment_id) assignment_count 
           FROM debate_round r 
           LEFT JOIN debate_assignment a ON a.round_id = r.round_id 
           WHERE r.competition_id = ?
           GROUP BY r.round_id 
           ORDER BY r.round_num', [$cid]);

include '../partials/header.php';
?>

<div class="section-head">
  <div>
    <span class="kicker">Admin &middot; Tournament Stages &middot; <?= e($comp['host_name'] ?? '') ?></span>
    <h1>Debate Rounds &middot; <?= e($comp['name'] ?? '') ?></h1>
    <p class="muted">Define competition rounds (e.g. Opening, Octo Finals, Quarter Finals, Grand Final) and their scheduled start times for this tournament.</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="flash error"><?= e($error) ?></div>
<?php endif; ?>

<div class="two-col">
  <!-- ADD ROUND -->
  <div class="card">
    <h3>Add Tournament Round</h3>
    <form method="post" action="rounds.php" style="margin-top: 14px;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">

      <div class="form-row">
        <label class="label">Round Name *</label>
        <input type="text" name="round_name" placeholder="e.g. Quarter Finals" required>
      </div>

      <div class="form-row" style="margin-top: 10px;">
        <label class="label">Round Number (Sequence) *</label>
        <input type="number" min="1" max="99" name="round_num" placeholder="e.g. 2" required>
      </div>

      <div class="form-row" style="margin-top: 10px;">
        <label class="label">Start Date &amp; Time *</label>
        <input type="datetime-local" name="start_time" required>
      </div>

      <button class="btn" type="submit" style="margin-top: 16px;">Create Round</button>
    </form>
  </div>

  <!-- EXISTING ROUNDS & INLINE EDIT -->
  <div class="card">
    <h3>Existing Rounds in <?= e($comp['host_name'] ?? 'Tournament') ?></h3>
    <div class="table-wrap" style="margin-top: 12px;">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Name &amp; Schedule</th>
            <th>Debates</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><span class="badge"><?= e($r['round_num']) ?></span></td>
              <td>
                <form method="post" action="rounds.php" style="display:flex; flex-direction:column; gap:4px;">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="edit">
                  <input type="hidden" name="round_id" value="<?= $r['round_id'] ?>">

                  <input type="text" name="round_name" value="<?= e($r['round_name']) ?>" style="padding:4px 8px; font-size:13px;" required>
                  <div style="display:flex; gap:6px;">
                    <input type="number" name="round_num" value="<?= e($r['round_num']) ?>" style="width:70px; padding:4px 6px; font-size:12px;" title="Round #" required>
                    <input type="datetime-local" name="start_time" value="<?= date('Y-m-d\TH:i', strtotime($r['start_time'])) ?>" style="padding:4px 6px; font-size:12px;" required>
                    <button class="btn small secondary" type="submit">Save</button>
                  </div>
                </form>
              </td>
              <td><span class="badge gray"><?= e($r['assignment_count']) ?></span></td>
              <td style="vertical-align:top;">
                <form method="post" action="rounds.php" onsubmit="return confirm('Delete round <?= e($r['round_name']) ?> and its assignments?');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="round_id" value="<?= $r['round_id'] ?>">
                  <button class="btn small danger" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="4" class="empty">No tournament rounds defined for this competition yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../partials/footer.php'; ?>
