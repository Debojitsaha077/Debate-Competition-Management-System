<?php
$admin_area = true;
require_once '../config/bootstrap.php';
require_admin();

$title = 'Manage Tournaments & Competitions';
$error = '';
$currentCid = current_competition_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $name        = trim($_POST['name'] ?? '');
            $hostInstId  = (int)($_POST['host_inst_id'] ?? 0);
            $startDate   = trim($_POST['start_date'] ?? '') ?: null;
            $endDate     = trim($_POST['end_date'] ?? '') ?: null;
            $status      = in_array($_POST['status'] ?? '', ['upcoming', 'active', 'completed']) ? $_POST['status'] : 'active';
            $description = trim($_POST['description'] ?? '');

            if ($name === '' || $hostInstId < 1) {
                throw new RuntimeException('Competition name and host institution are required.');
            }

            exec_sql(
                'INSERT INTO competition(name, host_inst_id, start_date, end_date, status, description) VALUES(?, ?, ?, ?, ?, ?)',
                [$name, $hostInstId, $startDate, $endDate, $status, $description ?: null]
            );
            $newId = (int)db()->lastInsertId();
            $_SESSION['admin_competition_id'] = $newId;
            flash('success', 'Tournament "' . $name . '" created and set as active!');
            redirect('competitions.php');
        } elseif ($action === 'edit') {
            $compId      = (int)($_POST['competition_id'] ?? 0);
            $name        = trim($_POST['name'] ?? '');
            $hostInstId  = (int)($_POST['host_inst_id'] ?? 0);
            $startDate   = trim($_POST['start_date'] ?? '') ?: null;
            $endDate     = trim($_POST['end_date'] ?? '') ?: null;
            $status      = in_array($_POST['status'] ?? '', ['upcoming', 'active', 'completed']) ? $_POST['status'] : 'active';
            $description = trim($_POST['description'] ?? '');

            if ($compId < 1 || $name === '' || $hostInstId < 1) {
                throw new RuntimeException('Invalid competition data.');
            }

            exec_sql(
                'UPDATE competition SET name=?, host_inst_id=?, start_date=?, end_date=?, status=?, description=? WHERE competition_id=?',
                [$name, $hostInstId, $startDate, $endDate, $status, $description ?: null, $compId]
            );
            flash('success', 'Tournament details updated.');
            redirect('competitions.php');
        } elseif ($action === 'delete') {
            $compId = (int)($_POST['competition_id'] ?? 0);
            if ($compId > 0) {
                exec_sql('DELETE FROM competition WHERE competition_id=?', [$compId]);
                if (isset($_SESSION['admin_competition_id']) && $_SESSION['admin_competition_id'] == $compId) {
                    unset($_SESSION['admin_competition_id']);
                }
                flash('success', 'Tournament deleted successfully.');
                redirect('competitions.php');
            }
        } elseif ($action === 'select') {
            $compId = (int)($_POST['competition_id'] ?? 0);
            if ($compId > 0 && get_competition($compId)) {
                $_SESSION['admin_competition_id'] = $compId;
                flash('info', 'Active workspace switched to: ' . get_competition($compId)['name']);
                redirect('dashboard.php');
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$institutions = q('SELECT * FROM institution ORDER BY name');

$competitions = q('SELECT c.*, i.name AS host_name, i.type AS host_type,
                   (SELECT COUNT(*) FROM debate_round r WHERE r.competition_id = c.competition_id) AS round_count,
                   (SELECT COUNT(*) FROM room rm WHERE rm.competition_id = c.competition_id) AS room_count,
                   (SELECT COUNT(*) FROM team t WHERE t.competition_id = c.competition_id) AS team_count,
                   (SELECT COUNT(*) FROM competition_registration cr WHERE cr.competition_id = c.competition_id) AS participant_count,
                   (SELECT COUNT(*) FROM debate_assignment a JOIN debate_round r ON r.round_id = a.round_id WHERE r.competition_id = c.competition_id) AS assignment_count
                   FROM competition c 
                   JOIN institution i ON i.inst_id = c.host_inst_id 
                   ORDER BY c.status = "active" DESC, c.start_date DESC, c.name ASC');

include '../partials/header.php';
?>

<div class="section-head">
  <div>
    <span class="kicker">Multi-University System</span>
    <h1>Tournament &amp; Competition Manager</h1>
    <p class="muted">Create, organize, and manage independent debate tournaments hosted by BRAC University, North South University, East West University, or any debate society.</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="flash error"><?= e($error) ?></div>
<?php endif; ?>

<!-- CREATE NEW TOURNAMENT -->
<div class="card" style="margin-bottom: 24px;">
  <div class="section-head" style="margin-bottom: 12px;">
    <div>
      <span class="kicker">New Tournament</span>
      <h2>Register Competition / Event</h2>
    </div>
  </div>

  <form method="post" action="competitions.php">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">

    <div class="form-grid">
      <div class="form-row full">
        <label class="label">Tournament / Competition Name *</label>
        <input type="text" name="name" placeholder="e.g. North South University National IV 2026" required>
      </div>

      <div class="form-row">
        <label class="label">Host Institution (University / Club) *</label>
        <select name="host_inst_id" required>
          <option value="">Select Host Institution</option>
          <?php foreach ($institutions as $inst): ?>
            <option value="<?= $inst['inst_id'] ?>"><?= e($inst['name']) ?> (<?= e($inst['type']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label class="label">Status</label>
        <select name="status">
          <option value="active">Active (Ongoing / In Progress)</option>
          <option value="upcoming">Upcoming (Registration Open)</option>
          <option value="completed">Completed (Archived)</option>
        </select>
      </div>

      <div class="form-row">
        <label class="label">Start Date</label>
        <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
      </div>

      <div class="form-row">
        <label class="label">End Date</label>
        <input type="date" name="end_date">
      </div>

      <div class="form-row full">
        <label class="label">Description / Tournament Rules Overview</label>
        <textarea name="description" rows="2" placeholder="Brief summary of format (e.g. Asian Parliamentary, British Parliamentary, Novice caps)..."></textarea>
      </div>
    </div>

    <div style="margin-top: 18px;">
      <button class="btn" type="submit">Create Tournament</button>
    </div>
  </form>
</div>

<!-- TOURNAMENT CARDS & LIST -->
<div class="section">
  <div class="section-head">
    <h2>All Registered Tournaments (<?= count($competitions) ?>)</h2>
  </div>

  <div class="grid grid-2" style="grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));">
    <?php foreach ($competitions as $c): 
      $isActive = ($c['competition_id'] == $currentCid);
    ?>
      <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; position: relative; <?= $isActive ? 'border: 2px solid var(--accent); box-shadow: 0 0 20px rgba(99, 102, 241, 0.2);' : '' ?>">
        <div>
          <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
            <div>
              <span class="badge <?= $c['status'] === 'active' ? 'green' : ($c['status'] === 'upcoming' ? 'yellow' : 'gray') ?>">
                <?= ucfirst(e($c['status'])) ?>
              </span>
              <?php if ($isActive): ?>
                <span class="badge" style="background: var(--accent); color: #fff; font-weight: 700;">Active Workspace</span>
              <?php endif; ?>
            </div>
            <span class="muted" style="font-size: 12px;">ID #<?= e($c['competition_id']) ?></span>
          </div>

          <h3 style="margin: 4px 0 8px; font-size: 1.2rem; color: var(--text-primary);"><?= e($c['name']) ?></h3>
          
          <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 12px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-bright);">
              <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
              <path d="M6 12v5c3 3 9 3 12 0v-5"/>
            </svg>
            <strong style="font-size: 13px; color: var(--accent-bright);"><?= e($c['host_name']) ?></strong>
            <span class="muted" style="font-size: 12px;">(<?= e($c['host_type']) ?>)</span>
          </div>

          <?php if (!empty($c['description'])): ?>
            <p class="muted" style="font-size: 13px; margin-bottom: 14px; line-height: 1.4;"><?= e($c['description']) ?></p>
          <?php endif; ?>

          <!-- STATS BADGES -->
          <div class="mini-list" style="margin-bottom: 16px;">
            <div class="mini-item">
              <span class="muted">Venue Rooms:</span>
              <strong><?= (int)$c['room_count'] ?> rooms</strong>
            </div>
            <div class="mini-item">
              <span class="muted">Rounds Scheduled:</span>
              <strong><?= (int)$c['round_count'] ?> rounds</strong>
            </div>
            <div class="mini-item">
              <span class="muted">Competing Teams:</span>
              <strong><?= (int)$c['team_count'] ?> teams</strong>
            </div>
            <div class="mini-item">
              <span class="muted">Registered Debaters:</span>
              <strong><?= (int)$c['participant_count'] ?> debaters</strong>
            </div>
            <div class="mini-item">
              <span class="muted">Dates:</span>
              <strong><?= $c['start_date'] ? date('d M Y', strtotime($c['start_date'])) : 'TBD' ?> &ndash; <?= $c['end_date'] ? date('d M Y', strtotime($c['end_date'])) : 'TBD' ?></strong>
            </div>
          </div>
        </div>

        <!-- ACTIONS & EDIT -->
        <div style="border-top: 1px solid var(--glass-border); padding-top: 14px; display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap;">
          <div class="actions">
            <?php if (!$isActive): ?>
              <form method="post" action="competitions.php" style="display: inline;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="select">
                <input type="hidden" name="competition_id" value="<?= $c['competition_id'] ?>">
                <button class="btn small" type="submit">Switch &amp; Manage</button>
              </form>
            <?php else: ?>
              <a href="dashboard.php" class="btn small success">Open Dashboard &rarr;</a>
            <?php endif; ?>

            <button class="btn small secondary" type="button" onclick="document.getElementById('edit-comp-<?= $c['competition_id'] ?>').style.display = document.getElementById('edit-comp-<?= $c['competition_id'] ?>').style.display === 'none' ? 'block' : 'none';">Edit</button>
          </div>

          <form method="post" action="competitions.php" onsubmit="return confirm('Delete tournament <?= e($c['name']) ?> and all associated rounds/rooms/teams?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="competition_id" value="<?= $c['competition_id'] ?>">
            <button class="btn small danger" type="submit">Delete</button>
          </form>
        </div>

        <!-- INLINE EDIT FORM -->
        <div id="edit-comp-<?= $c['competition_id'] ?>" style="display: none; margin-top: 16px; padding: 14px; background: rgba(0,0,0,0.25); border-radius: var(--radius-md); border: 1px solid var(--glass-border);">
          <h4 style="margin-bottom: 10px;">Edit Tournament Details</h4>
          <form method="post" action="competitions.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="competition_id" value="<?= $c['competition_id'] ?>">

            <div class="form-row" style="margin-bottom: 8px;">
              <label class="label">Tournament Name</label>
              <input type="text" name="name" value="<?= e($c['name']) ?>" required>
            </div>

            <div class="form-row" style="margin-bottom: 8px;">
              <label class="label">Host Institution</label>
              <select name="host_inst_id" required>
                <?php foreach ($institutions as $inst): ?>
                  <option value="<?= $inst['inst_id'] ?>" <?= $inst['inst_id'] == $c['host_inst_id'] ? 'selected' : '' ?>>
                    <?= e($inst['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-grid" style="margin-bottom: 8px;">
              <div class="form-row">
                <label class="label">Status</label>
                <select name="status">
                  <option value="active" <?= $c['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="upcoming" <?= $c['status'] === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                  <option value="completed" <?= $c['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
              </div>

              <div class="form-row">
                <label class="label">Start Date</label>
                <input type="date" name="start_date" value="<?= e($c['start_date']) ?>">
              </div>
            </div>

            <div class="form-row" style="margin-bottom: 8px;">
              <label class="label">Description</label>
              <textarea name="description" rows="2"><?= e($c['description']) ?></textarea>
            </div>

            <div style="display: flex; gap: 6px;">
              <button class="btn small" type="submit">Save Changes</button>
              <button class="btn small secondary" type="button" onclick="document.getElementById('edit-comp-<?= $c['competition_id'] ?>').style.display='none';">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include '../partials/footer.php'; ?>
