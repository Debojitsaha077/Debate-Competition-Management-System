<?php
$admin_area = true;
require_once '../config/bootstrap.php';
require_admin();

$cid  = current_competition_id();
$comp = current_competition();

$title = 'Manage Tournament & Entities · ' . ($comp['name'] ?? '');
$error = '';
$currentTab = $_GET['tab'] ?? 'add_info';
$search = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $redirectTab = $_POST['tab'] ?? $currentTab;
    
    try {
        switch ($action) {
            case 'institution':
                exec_sql('INSERT INTO institution(name, type) VALUES(?, ?)', [
                    trim($_POST['name']), 
                    trim($_POST['type'])
                ]);
                flash('success', 'Institution added successfully.');
                break;

            case 'edit_institution':
                exec_sql('UPDATE institution SET name=?, type=? WHERE inst_id=?', [
                    trim($_POST['name']),
                    trim($_POST['type']),
                    (int)$_POST['id']
                ]);
                flash('success', 'Institution updated.');
                break;
                
            case 'room':
                exec_sql('INSERT INTO room(competition_id, number, building) VALUES(?, ?, ?)', [
                    $cid,
                    trim($_POST['number']), 
                    trim($_POST['building'])
                ]);
                flash('success', 'Room added to ' . ($comp['name'] ?? 'tournament') . ' successfully.');
                break;

            case 'edit_room':
                exec_sql('UPDATE room SET number=?, building=? WHERE room_id=? AND competition_id=?', [
                    trim($_POST['number']),
                    trim($_POST['building']),
                    (int)$_POST['id'],
                    $cid
                ]);
                flash('success', 'Room updated.');
                break;
                
            case 'team':
                $teamInstId = intval($_POST['inst_id'] ?? 0) ?: null;
                exec_sql('INSERT INTO team(competition_id, team_name, inst_id) VALUES(?, ?, ?)', [
                    $cid,
                    trim($_POST['team_name']),
                    $teamInstId
                ]);
                flash('success', 'Team added to ' . ($comp['name'] ?? 'tournament') . ' successfully.');
                break;

            case 'edit_team':
                $teamInstId = intval($_POST['inst_id'] ?? 0) ?: null;
                exec_sql('UPDATE team SET team_name=?, inst_id=? WHERE team_id=? AND competition_id=?', [
                    trim($_POST['team_name']),
                    $teamInstId,
                    (int)$_POST['id'],
                    $cid
                ]);
                flash('success', 'Team updated.');
                break;
                
            case 'participant':
                $asDebater = !empty($_POST['as_debater']);
                $asJudge   = !empty($_POST['as_judge']);
                
                if ($asDebater && $asJudge) {
                    throw new RuntimeException('EER Specialization is disjoint: A participant cannot be registered as both Debater and Judge in the same record.');
                }
                if ($asDebater && !intval($_POST['team_id'])) {
                    throw new RuntimeException('A Debater must be assigned to an existing Team.');
                }
                
                $pdo = db();
                $pdo->beginTransaction();
                
                $s = $pdo->prepare('INSERT INTO participant(email, name, phone, age, skill_level, inst_id) VALUES(?, ?, ?, ?, ?, ?)');
                $s->execute([
                    trim($_POST['email']),
                    trim($_POST['name']),
                    trim($_POST['phone']) ?: null,
                    intval($_POST['age']) ?: null,
                    trim($_POST['skill_level']) ?: null,
                    intval($_POST['inst_id']) ?: null
                ]);
                
                $pid = (int)$pdo->lastInsertId();
                
                // Automatically register participant to the current competition
                $s = $pdo->prepare('INSERT IGNORE INTO competition_registration(competition_id, participant_id) VALUES(?, ?)');
                $s->execute([$cid, $pid]);
                
                if ($asDebater) {
                    $s = $pdo->prepare('INSERT INTO debater(participant_id, team_id, skill_level) VALUES(?, ?, ?)');
                    $s->execute([
                        $pid, 
                        intval($_POST['team_id']), 
                        trim($_POST['skill_level']) ?: null
                    ]);
                }
                
                if ($asJudge) {
                    $s = $pdo->prepare('INSERT INTO judge(participant_id, judge_experience) VALUES(?, ?)');
                    $s->execute([
                        $pid, 
                        trim($_POST['judge_experience']) ?: 'Certified Judge'
                    ]);
                }
                
                $pdo->commit();
                flash('success', 'Participant registered and enrolled in ' . ($comp['name'] ?? 'tournament') . ' successfully.');
                break;

            case 'assign_role':
                $pid      = (int)($_POST['participant_id'] ?? 0);
                $roleType = $_POST['role_type'] ?? 'Participant';
                $teamId   = (int)($_POST['team_id'] ?? 0);
                $judgeExp = trim($_POST['judge_experience'] ?? '');

                if ($pid < 1) {
                    throw new RuntimeException('Invalid participant selected.');
                }

                $pdo = db();
                $pdo->beginTransaction();

                if ($roleType === 'Debater') {
                    if ($teamId < 1) {
                        throw new RuntimeException('Please select a team to assign the debater.');
                    }
                    // Remove from judge to enforce disjoint specialization
                    $pdo->prepare('DELETE FROM judge WHERE participant_id=?')->execute([$pid]);
                    
                    // Insert or update debater
                    $s = $pdo->prepare('INSERT INTO debater(participant_id, team_id, skill_level) VALUES(?, ?, (SELECT skill_level FROM participant WHERE participant_id=?)) ON DUPLICATE KEY UPDATE team_id=VALUES(team_id)');
                    $s->execute([$pid, $teamId, $pid]);

                    // Auto-enroll in the team\'s tournament if not enrolled
                    $teamComp = one('SELECT competition_id FROM team WHERE team_id=?', [$teamId]);
                    if ($teamComp) {
                        $pdo->prepare('INSERT IGNORE INTO competition_registration(competition_id, participant_id) VALUES(?, ?)')->execute([$teamComp['competition_id'], $pid]);
                    }
                    
                    flash('success', 'Player successfully assigned to team as a Debater!');
                } elseif ($roleType === 'Judge') {
                    // Remove from debater
                    $pdo->prepare('DELETE FROM debater WHERE participant_id=?')->execute([$pid]);
                    
                    $s = $pdo->prepare('INSERT INTO judge(participant_id, judge_experience) VALUES(?, ?) ON DUPLICATE KEY UPDATE judge_experience=VALUES(judge_experience)');
                    $s->execute([$pid, $judgeExp ?: 'Certified Judge']);
                    
                    flash('success', 'Participant role updated to Judge/Adjudicator.');
                } else {
                    // Unassign from both debater and judge
                    $pdo->prepare('DELETE FROM debater WHERE participant_id=?')->execute([$pid]);
                    $pdo->prepare('DELETE FROM judge WHERE participant_id=?')->execute([$pid]);
                    flash('success', 'Participant role reset to General Participant.');
                }

                $pdo->commit();
                break;

            case 'edit_participant':
                exec_sql('UPDATE participant SET name=?, email=?, phone=?, age=?, skill_level=?, inst_id=? WHERE participant_id=?', [
                    trim($_POST['name']),
                    trim($_POST['email']),
                    trim($_POST['phone']) ?: null,
                    intval($_POST['age']) ?: null,
                    trim($_POST['skill_level']) ?: null,
                    intval($_POST['inst_id']) ?: null,
                    (int)$_POST['id']
                ]);
                flash('success', 'Participant updated.');
                break;
                
            case 'delete_team':
                exec_sql('DELETE FROM team WHERE team_id=? AND competition_id=?', [intval($_POST['id']), $cid]);
                flash('success', 'Team deleted.');
                break;
                
            case 'delete_room':
                exec_sql('DELETE FROM room WHERE room_id=? AND competition_id=?', [intval($_POST['id']), $cid]);
                flash('success', 'Room deleted.');
                break;
                
            case 'delete_institution':
                exec_sql('DELETE FROM institution WHERE inst_id=?', [intval($_POST['id'])]);
                flash('success', 'Institution deleted.');
                break;

            case 'delete_participant':
                exec_sql('DELETE FROM participant WHERE participant_id=?', [intval($_POST['id'])]);
                flash('success', 'Participant deleted.');
                break;
        }
        
        redirect('entities.php?tab=' . urlencode($redirectTab));
        
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$insts = q('SELECT * FROM institution ORDER BY name');
$teams = q('SELECT t.*, i.name AS institution_name FROM team t LEFT JOIN institution i ON i.inst_id = t.inst_id WHERE t.competition_id=? ORDER BY t.team_name', [$cid]);
$rooms = q('SELECT * FROM room WHERE competition_id=? ORDER BY building, number', [$cid]);

// All teams across all tournaments (with active tournament first) for team assignment
$allTeams = q('SELECT t.*, c.name AS competition_name, i.name AS host_name 
               FROM team t 
               JOIN competition c ON c.competition_id = t.competition_id 
               JOIN institution i ON i.inst_id = c.host_inst_id 
               ORDER BY (c.competition_id = ?) DESC, c.name, t.team_name', [$cid]);

// Query for participants with optional search filtering
$pSql = 'SELECT p.*, 
        i.name institution_name, 
        d.team_id,
        CASE 
            WHEN d.participant_id IS NOT NULL THEN "Debater" 
            WHEN j.participant_id IS NOT NULL THEN "Judge" 
            ELSE "Participant" 
        END role_name, 
        t.team_name, 
        tc.name AS team_competition_name,
        j.judge_experience,
        (SELECT GROUP_CONCAT(c.name SEPARATOR ", ") FROM competition_registration cr JOIN competition c ON c.competition_id = cr.competition_id WHERE cr.participant_id = p.participant_id) AS enrolled_competitions
        FROM participant p 
        LEFT JOIN institution i ON i.inst_id=p.inst_id 
        LEFT JOIN debater d ON d.participant_id=p.participant_id 
        LEFT JOIN judge j ON j.participant_id=p.participant_id 
        LEFT JOIN team t ON t.team_id=d.team_id 
        LEFT JOIN competition tc ON tc.competition_id=t.competition_id
        WHERE 1=1';

$pParams = [];
if ($search !== '') {
    $pSql .= ' AND (p.name LIKE ? OR p.email LIKE ? OR p.phone LIKE ? OR p.skill_level LIKE ? OR i.name LIKE ? OR t.team_name LIKE ?)';
    $like = "%$search%";
    array_push($pParams, $like, $like, $like, $like, $like, $like);
}

$pSql .= ' ORDER BY p.name ASC';
$people = q($pSql, $pParams);

$totalParticipantsCount = one('SELECT COUNT(*) c FROM participant')['c'] ?? 0;

include '../partials/header.php';
?>

<div class="section-head">
  <div>
    <span class="kicker">Admin &middot; Management &middot; <?= e($comp['host_name'] ?? '') ?></span>
    <h1>Tournament Manager &middot; <?= e($comp['name'] ?? '') ?></h1>
    <p class="muted">Add, update, or remove tournament venue rooms, teams, institutions, and assign participants to competing teams.</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="flash error"><?= e($error) ?></div>
<?php endif; ?>

<!-- SUB-PAGE TABS NAVIGATION -->
<nav class="tab-nav">
  <a href="entities.php?tab=add_info" class="tab-link <?= $currentTab === 'add_info' ? 'active' : '' ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/>
      <line x1="12" y1="8" x2="12" y2="16"/>
      <line x1="8" y1="12" x2="16" y2="12"/>
    </svg>
    1. Add Tournament Infos
  </a>

  <a href="entities.php?tab=register" class="tab-link <?= $currentTab === 'register' ? 'active' : '' ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
      <circle cx="8.5" cy="7" r="4"/>
      <line x1="20" y1="8" x2="20" y2="14"/>
      <line x1="23" y1="11" x2="17" y2="11"/>
    </svg>
    2. Register Participant &amp; Role
  </a>

  <a href="entities.php?tab=entities" class="tab-link <?= $currentTab === 'entities' ? 'active' : '' ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="3" width="7" height="7"/>
      <rect x="14" y="3" width="7" height="7"/>
      <rect x="14" y="14" width="7" height="7"/>
      <rect x="3" y="14" width="7" height="7"/>
    </svg>
    3. Teams &amp; Rooms (<?= count($teams) ?> Teams, <?= count($rooms) ?> Rooms)
  </a>

  <a href="entities.php?tab=participants" class="tab-link <?= $currentTab === 'participants' ? 'active' : '' ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
      <circle cx="9" cy="7" r="4"/>
      <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
    4. All Registered Participants (Assign Teams)
    <span class="tab-count"><?= $totalParticipantsCount ?></span>
  </a>
</nav>

<!-- ============================================================
     TAB 1: ADD TOURNAMENT INFOS (INSTITUTION, ROOM, TEAM)
     ============================================================ -->
<?php if ($currentTab === 'add_info'): ?>
  <div class="grid grid-3">
    <!-- ADD INSTITUTION -->
    <div class="card">
      <div class="kicker">Setup &middot; Step 1</div>
      <h3>Add Global Institution</h3>
      <p class="muted" style="font-size:13px; margin: 4px 0 14px;">Register universities or debate societies.</p>
      
      <form method="post" action="entities.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="institution">
        <input type="hidden" name="tab" value="add_info">
        
        <div class="form-row">
          <label class="label">Institution Name *</label>
          <input type="text" name="name" placeholder="e.g. Dhaka University" required>
        </div>
        
        <div class="form-row" style="margin-top: 10px;">
          <label class="label">Institution Type *</label>
          <input type="text" name="type" placeholder="e.g. University / College / Club" required>
        </div>
        
        <button class="btn" type="submit" style="margin-top: 16px; width: 100%;">Add Institution</button>
      </form>
    </div>

    <!-- ADD ROOM -->
    <div class="card">
      <div class="kicker">Venue &middot; <?= e($comp['host_name'] ?? '') ?></div>
      <h3>Add Room / Venue</h3>
      <p class="muted" style="font-size:13px; margin: 4px 0 14px;">Define debate rooms for <strong><?= e($comp['name'] ?? '') ?></strong>.</p>
      
      <form method="post" action="entities.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="room">
        <input type="hidden" name="tab" value="add_info">
        
        <div class="form-row">
          <label class="label">Room Number / ID *</label>
          <input type="text" name="number" placeholder="e.g. UB0204 / NAC-601" required>
        </div>
        
        <div class="form-row" style="margin-top: 10px;">
          <label class="label">Building / Hall *</label>
          <input type="text" name="building" placeholder="e.g. Main Academic Building" required>
        </div>
        
        <button class="btn" type="submit" style="margin-top: 16px; width: 100%;">Add Room to <?= e($comp['host_name'] ?? 'Tournament') ?></button>
      </form>
    </div>

    <!-- ADD TEAM -->
    <div class="card">
      <div class="kicker">Delegation &middot; <?= e($comp['host_name'] ?? '') ?></div>
      <h3>Add Debate Team</h3>
      <p class="muted" style="font-size:13px; margin: 4px 0 14px;">Create competing teams for <strong><?= e($comp['name'] ?? '') ?></strong>.</p>
      
      <form method="post" action="entities.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="team">
        <input type="hidden" name="tab" value="add_info">
        
        <div class="form-row">
          <label class="label">Team Name *</label>
          <input type="text" name="team_name" placeholder="e.g. Apex Orators" required>
        </div>

        <div class="form-row" style="margin-top: 10px;">
          <label class="label">Affiliated Institution</label>
          <select name="inst_id">
            <option value="">Select University (Optional)</option>
            <?php foreach ($insts as $i): ?>
              <option value="<?= $i['inst_id'] ?>"><?= e($i['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <button class="btn" type="submit" style="margin-top: 16px; width: 100%;">Add Team to <?= e($comp['host_name'] ?? 'Tournament') ?></button>
      </form>
    </div>
  </div>
<?php endif; ?>

<!-- ============================================================
     TAB 2: REGISTER PARTICIPANT & ROLE (DEBATER / JUDGE)
     ============================================================ -->
<?php if ($currentTab === 'register'): ?>
  <div class="card">
    <div class="kicker">Registration &middot; Enrolling in <?= e($comp['name'] ?? '') ?></div>
    <h2>Register Participant &amp; Assign Role</h2>
    <p class="muted" style="margin-bottom: 20px;">
      Create a participant record and automatically enroll them into <strong><?= e($comp['name'] ?? 'the tournament') ?></strong>.
    </p>

    <form method="post" action="entities.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="participant">
      <input type="hidden" name="tab" value="register">

      <div class="form-grid">
        <div class="form-row">
          <label class="label">Full Name *</label>
          <input type="text" name="name" placeholder="e.g. Alfi Shahariar" required>
        </div>

        <div class="form-row">
          <label class="label">Email Address *</label>
          <input type="email" name="email" placeholder="alfi@example.com" required>
        </div>

        <div class="form-row">
          <label class="label">Phone Number</label>
          <input type="text" name="phone" placeholder="01700000000">
        </div>

        <div class="form-row">
          <label class="label">Age</label>
          <input type="number" min="10" max="100" name="age" placeholder="22">
        </div>

        <div class="form-row">
          <label class="label">Home Institution</label>
          <select name="inst_id">
            <option value="">None / Independent</option>
            <?php foreach ($insts as $i): ?>
              <option value="<?= $i['inst_id'] ?>"><?= e($i['name']) ?> (<?= e($i['type']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <label class="label">Skill Level</label>
          <input type="text" name="skill_level" placeholder="Novice / Intermediate / Advanced">
        </div>

        <!-- DEBATER ROLE OPTION -->
        <div class="form-row" style="background: rgba(99, 102, 241, 0.08); padding: 14px; border-radius: var(--radius-md); border: 1px solid rgba(99, 102, 241, 0.25);">
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
            <input type="checkbox" name="as_debater" value="1"> 
            <strong>Register as DEBATER</strong>
          </label>
          <div style="margin-top: 8px;">
            <select name="team_id">
              <option value="">Select Team in <?= e($comp['host_name'] ?? 'Tournament') ?> (Required for Debater)</option>
              <?php foreach ($teams as $t): ?>
                <option value="<?= $t['team_id'] ?>"><?= e($t['team_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- JUDGE ROLE OPTION -->
        <div class="form-row" style="background: rgba(52, 211, 153, 0.08); padding: 14px; border-radius: var(--radius-md); border: 1px solid rgba(52, 211, 153, 0.25);">
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
            <input type="checkbox" name="as_judge" value="1"> 
            <strong>Register as JUDGE / ADJUDICATOR</strong>
          </label>
          <div style="margin-top: 8px;">
            <input type="text" name="judge_experience" placeholder="e.g. 5 years national judging exp">
          </div>
        </div>
      </div>

      <div style="margin-top: 24px;">
        <button class="btn" type="submit">Create Participant &amp; Enroll</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<!-- ============================================================
     TAB 3: TEAMS, ROOMS & INSTITUTIONS TABLES (WITH EDIT & DELETE)
     ============================================================ -->
<?php if ($currentTab === 'entities'): ?>
  <div class="grid grid-3">
    <!-- TEAMS TABLE -->
    <div class="card">
      <div class="section-head" style="margin-bottom: 8px;">
        <div>
          <span class="kicker"><?= e($comp['host_name'] ?? '') ?></span>
          <h3>Teams (<?= count($teams) ?>)</h3>
        </div>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Team Name</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($teams as $t): ?>
              <tr>
                <td>
                  <form method="post" action="entities.php" style="display:flex; flex-direction:column; gap:4px;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="edit_team">
                    <input type="hidden" name="id" value="<?= $t['team_id'] ?>">
                    <input type="hidden" name="tab" value="entities">
                    <input type="text" name="team_name" value="<?= e($t['team_name']) ?>" style="padding:4px 8px; font-size:12px;" required>
                    <select name="inst_id" style="padding:2px 6px; font-size:11px;">
                      <option value="">Affiliation: None</option>
                      <?php foreach ($insts as $i): ?>
                        <option value="<?= $i['inst_id'] ?>" <?= $t['inst_id'] == $i['inst_id'] ? 'selected' : '' ?>><?= e($i['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn small secondary" type="submit" title="Save" style="align-self:flex-start;">Save</button>
                  </form>
                </td>
                <td style="vertical-align:top;">
                  <form method="post" action="entities.php" onsubmit="return confirm('Delete team <?= e($t['team_name']) ?>?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_team">
                    <input type="hidden" name="id" value="<?= $t['team_id'] ?>">
                    <input type="hidden" name="tab" value="entities">
                    <button class="btn small danger" type="submit">Del</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$teams): ?>
              <tr><td colspan="2" class="empty">No teams added for this tournament yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ROOMS TABLE -->
    <div class="card">
      <div class="section-head" style="margin-bottom: 8px;">
        <div>
          <span class="kicker"><?= e($comp['host_name'] ?? '') ?></span>
          <h3>Venue Rooms (<?= count($rooms) ?>)</h3>
        </div>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Room Details</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rooms as $r): ?>
              <tr>
                <td>
                  <form method="post" action="entities.php" style="display:flex; flex-direction:column; gap:4px;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="edit_room">
                    <input type="hidden" name="id" value="<?= $r['room_id'] ?>">
                    <input type="hidden" name="tab" value="entities">
                    <input type="text" name="number" value="<?= e($r['number']) ?>" style="padding:4px 8px; font-size:12px;" placeholder="Room #" required>
                    <input type="text" name="building" value="<?= e($r['building']) ?>" style="padding:4px 8px; font-size:12px;" placeholder="Building" required>
                    <button class="btn small secondary" type="submit" style="align-self:flex-start; margin-top:2px;">Save</button>
                  </form>
                </td>
                <td style="vertical-align:top;">
                  <form method="post" action="entities.php" onsubmit="return confirm('Delete room <?= e($r['number']) ?>?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_room">
                    <input type="hidden" name="id" value="<?= $r['room_id'] ?>">
                    <input type="hidden" name="tab" value="entities">
                    <button class="btn small danger" type="submit">Del</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rooms): ?>
              <tr><td colspan="2" class="empty">No rooms added for this tournament yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- INSTITUTIONS TABLE -->
    <div class="card">
      <div class="section-head" style="margin-bottom: 8px;">
        <div>
          <span class="kicker">Global</span>
          <h3>Institutions (<?= count($insts) ?>)</h3>
        </div>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Institution</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($insts as $i): ?>
              <tr>
                <td>
                  <form method="post" action="entities.php" style="display:flex; flex-direction:column; gap:4px;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="edit_institution">
                    <input type="hidden" name="id" value="<?= $i['inst_id'] ?>">
                    <input type="hidden" name="tab" value="entities">
                    <input type="text" name="name" value="<?= e($i['name']) ?>" style="padding:4px 8px; font-size:12px;" placeholder="Name" required>
                    <input type="text" name="type" value="<?= e($i['type']) ?>" style="padding:4px 8px; font-size:12px;" placeholder="Type" required>
                    <button class="btn small secondary" type="submit" style="align-self:flex-start; margin-top:2px;">Save</button>
                  </form>
                </td>
                <td style="vertical-align:top;">
                  <form method="post" action="entities.php" onsubmit="return confirm('Delete institution <?= e($i['name']) ?>?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_institution">
                    <input type="hidden" name="id" value="<?= $i['inst_id'] ?>">
                    <input type="hidden" name="tab" value="entities">
                    <button class="btn small danger" type="submit">Del</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$insts): ?>
              <tr><td colspan="2" class="empty">No institutions added.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- ============================================================
     TAB 4: ALL REGISTERED PARTICIPANTS WITH SEARCH & TEAM ASSIGNMENT
     ============================================================ -->
<?php if ($currentTab === 'participants'): ?>
  <div class="card">
    <div class="section-head">
      <div>
        <span class="kicker">Directory &middot; Participants &amp; Team Allocations</span>
        <h2>All Registered Participants &amp; Team Assignment</h2>
        <p class="muted">Search across all participants, assign unassigned players (like newly registered debaters) to competing teams, or assign adjudicator roles.</p>
      </div>
    </div>

    <!-- SEARCH BOX -->
    <form method="get" action="entities.php" class="toolbar" style="margin-bottom: 20px;">
      <input type="hidden" name="tab" value="participants">
      <div style="position: relative; flex: 1; max-width: 480px;">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, email, institution, skill level, or team..." style="padding-left: 38px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
      </div>
      <button class="btn" type="submit">Search</button>
      <?php if ($search !== ''): ?>
        <a class="btn secondary" href="entities.php?tab=participants">Clear Search</a>
      <?php endif; ?>
    </form>

    <?php if ($search !== ''): ?>
      <div class="notice" style="margin-bottom: 16px;">
        Showing results matching "<strong><?= e($search) ?></strong>" (<?= count($people) ?> found).
      </div>
    <?php endif; ?>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Name &amp; Contact</th>
            <th>Home University</th>
            <th>Role</th>
            <th>Assigned Team / Details</th>
            <th>Enrolled Tournaments</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($people as $p): ?>
            <tr>
              <td>
                <strong><?= e($p['name']) ?></strong>
                <br>
                <span class="muted" style="font-size:13px;"><?= e($p['email']) ?></span>
                <?php if (!empty($p['phone'])): ?>
                  &middot; <span class="muted" style="font-size:13px;"><?= e($p['phone']) ?></span>
                <?php endif; ?>
                <?php if (!empty($p['skill_level'])): ?>
                  <br><span class="badge gray" style="margin-top:4px;"><?= e($p['skill_level']) ?></span>
                <?php endif; ?>
              </td>
              <td><?= e($p['institution_name'] ?? '—') ?></td>
              <td>
                <span class="badge <?= $p['role_name'] === 'Debater' ? 'green' : ($p['role_name'] === 'Judge' ? '' : 'yellow') ?>">
                  <?= e($p['role_name']) ?>
                </span>
              </td>
              <td>
                <?php if ($p['role_name'] === 'Debater'): ?>
                  <strong>Team:</strong> <?= e($p['team_name'] ?: 'None') ?>
                  <?php if (!empty($p['team_competition_name'])): ?>
                    <br><span class="muted" style="font-size: 11px;">(<?= e($p['team_competition_name']) ?>)</span>
                  <?php endif; ?>
                <?php elseif ($p['role_name'] === 'Judge'): ?>
                  <strong>Exp:</strong> <?= e($p['judge_experience'] ?: 'Standard') ?>
                <?php else: ?>
                  <span class="badge yellow" style="font-size: 11px;">Unassigned to Team</span>
                <?php endif; ?>
              </td>
              <td style="max-width: 200px;">
                <span style="font-size: 12px; color: var(--accent-bright); font-weight: 500;">
                  <?= e($p['enrolled_competitions'] ?? 'No tournaments') ?>
                </span>
              </td>
              <td>
                <div class="actions" style="flex-direction: column; align-items: flex-start; gap: 6px;">
                  <button class="btn small" type="button" onclick="document.getElementById('assign-form-<?= $p['participant_id'] ?>').style.display = (document.getElementById('assign-form-<?= $p['participant_id'] ?>').style.display === 'none' ? 'table-row' : 'none');">
                    <?= $p['role_name'] === 'Participant' ? 'Assign Team' : 'Change Team / Role' ?>
                  </button>

                  <form method="post" action="entities.php" onsubmit="return confirm('Delete participant <?= e($p['name']) ?>?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_participant">
                    <input type="hidden" name="id" value="<?= $p['participant_id'] ?>">
                    <input type="hidden" name="tab" value="participants">
                    <button class="btn small danger" type="submit">Delete</button>
                  </form>
                </div>
              </td>
            </tr>

            <!-- INLINE ASSIGN TEAM / ROLE ROW -->
            <tr id="assign-form-<?= $p['participant_id'] ?>" style="display: none; background: rgba(99, 102, 241, 0.06);">
              <td colspan="6" style="padding: 16px 20px; border-left: 3px solid var(--accent);">
                <form method="post" action="entities.php" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="assign_role">
                  <input type="hidden" name="participant_id" value="<?= $p['participant_id'] ?>">
                  <input type="hidden" name="tab" value="participants">

                  <div style="min-width: 180px;">
                    <label class="label" style="font-size: 12px; font-weight: 700;">Role Type</label>
                    <select name="role_type" id="role-sel-<?= $p['participant_id'] ?>" onchange="toggleRoleFields(<?= $p['participant_id'] ?>)" style="padding: 6px 10px; font-size: 13px;">
                      <option value="Debater" <?= $p['role_name'] === 'Debater' ? 'selected' : '' ?>>Debater (Assign to Team)</option>
                      <option value="Judge" <?= $p['role_name'] === 'Judge' ? 'selected' : '' ?>>Judge / Adjudicator</option>
                      <option value="Participant" <?= $p['role_name'] === 'Participant' ? 'selected' : '' ?>>General Participant (Unassigned)</option>
                    </select>
                  </div>

                  <div id="team-wrap-<?= $p['participant_id'] ?>" style="min-width: 260px; <?= $p['role_name'] === 'Judge' ? 'display:none;' : '' ?>">
                    <label class="label" style="font-size: 12px; font-weight: 700;">Select Team *</label>
                    <select name="team_id" style="padding: 6px 10px; font-size: 13px;">
                      <option value="">-- Choose Team --</option>
                      <?php foreach ($allTeams as $at): ?>
                        <option value="<?= $at['team_id'] ?>" <?= ($p['team_id'] == $at['team_id']) ? 'selected' : '' ?>>
                          <?= e($at['team_name']) ?> (<?= e($at['host_name']) ?> &middot; <?= e($at['competition_name']) ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div id="judge-wrap-<?= $p['participant_id'] ?>" style="min-width: 220px; <?= $p['role_name'] === 'Judge' ? '' : 'display:none;' ?>">
                    <label class="label" style="font-size: 12px; font-weight: 700;">Judge Experience</label>
                    <input type="text" name="judge_experience" value="<?= e($p['judge_experience'] ?? '') ?>" placeholder="e.g. 5 years judging exp" style="padding: 6px 10px; font-size: 13px;">
                  </div>

                  <div style="display: flex; gap: 8px;">
                    <button class="btn small success" type="submit">Save Assignment</button>
                    <button class="btn small secondary" type="button" onclick="document.getElementById('assign-form-<?= $p['participant_id'] ?>').style.display='none';">Cancel</button>
                  </div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$people): ?>
            <tr>
              <td colspan="6" class="empty">No participants found matching your criteria.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
  function toggleRoleFields(pid) {
    const sel = document.getElementById('role-sel-' + pid).value;
    const teamWrap = document.getElementById('team-wrap-' + pid);
    const judgeWrap = document.getElementById('judge-wrap-' + pid);
    if (sel === 'Debater') {
      teamWrap.style.display = 'block';
      judgeWrap.style.display = 'none';
    } else if (sel === 'Judge') {
      teamWrap.style.display = 'none';
      judgeWrap.style.display = 'block';
    } else {
      teamWrap.style.display = 'none';
      judgeWrap.style.display = 'none';
    }
  }
  </script>
<?php endif; ?>

<?php include '../partials/footer.php'; ?>