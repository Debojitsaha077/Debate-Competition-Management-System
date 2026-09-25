<?php
require_once 'config/bootstrap.php';
require_login();

$u     = current_user();
$pid   = (int)$u['participant_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $age   = intval($_POST['age'] ?? 0);
        $skill = trim($_POST['skill_level'] ?? '');

        if ($name === '') {
            $error = 'Name cannot be empty.';
        } else {
            try {
                exec_sql(
                    'UPDATE participant SET name=?, phone=?, age=?, skill_level=? WHERE participant_id=?',
                    [$name, $phone ?: null, $age ?: null, $skill ?: null, $pid]
                );
                // Refresh session name if needed
                $_SESSION['user']['name'] = $name;
                flash('success', 'Profile updated successfully.');
                redirect('profile.php');
            } catch (Throwable $e) {
                $error = 'Failed to update profile: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'enroll_competition') {
        $targetCompId = (int)($_POST['competition_id'] ?? 0);
        if ($targetCompId > 0 && get_competition($targetCompId)) {
            try {
                exec_sql('INSERT IGNORE INTO competition_registration(competition_id, participant_id) VALUES(?, ?)', [$targetCompId, $pid]);
                $_SESSION['user_competition_id'] = $targetCompId;
                flash('success', 'You have been registered for ' . get_competition($targetCompId)['name'] . '!');
                redirect('profile.php');
            } catch (Throwable $e) {
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $currPass = $_POST['current_password'] ?? '';
        $newPass  = $_POST['new_password'] ?? '';
        $confPass = $_POST['confirm_password'] ?? '';

        $authRow = one('SELECT password_hash FROM auth_user WHERE user_id=?', [$u['user_id']]);

        if (!$authRow || !password_verify($currPass, $authRow['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPass !== $confPass) {
            $error = 'New passwords do not match.';
        } else {
            try {
                exec_sql(
                    'UPDATE auth_user SET password_hash=? WHERE user_id=?',
                    [password_hash($newPass, PASSWORD_DEFAULT), $u['user_id']]
                );
                flash('success', 'Password changed successfully.');
                redirect('profile.php');
            } catch (Throwable $e) {
                $error = 'Failed to change password: ' . $e->getMessage();
            }
        }
    }
}

$p = one(
    'SELECT p.*, i.name AS institution_name, i.type AS institution_type,
            d.team_id, t.team_name, d.skill_level AS debater_skill,
            j.judge_experience,
            CASE 
                WHEN d.participant_id IS NOT NULL THEN "Debater" 
                WHEN j.participant_id IS NOT NULL THEN "Judge" 
                ELSE "Participant" 
            END AS role_name
     FROM participant p 
     LEFT JOIN institution i ON i.inst_id = p.inst_id 
     LEFT JOIN debater d ON d.participant_id = p.participant_id 
     LEFT JOIN team t ON t.team_id = d.team_id 
     LEFT JOIN judge j ON j.participant_id = p.participant_id 
     WHERE p.participant_id = ?',
    [$pid]
);

$myComps  = user_competitions($pid);
$allComps = all_competitions();
$myCompIds = array_column($myComps, 'competition_id');
$unregisteredComps = array_filter($allComps, fn($c) => !in_array($c['competition_id'], $myCompIds));

$title = 'My Profile · Tournament Registrations';

include 'partials/header.php';
?>

<div class="section-head">
  <div>
    <span class="kicker">Account Settings</span>
    <h1>Participant Profile &amp; Tournament Registrations</h1>
    <p class="muted">Manage your personal contact details, view enrolled university debate tournaments, or register for upcoming competitions.</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="flash error"><?= e($error) ?></div>
<?php endif; ?>

<div class="two-col">
  <!-- EDIT PROFILE DETAILS -->
  <div class="card">
    <h3>Edit Personal Details</h3>
    <form method="post" action="profile.php" style="margin-top: 16px;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="update_profile">

      <div class="form-row">
        <label class="label">Full Name *</label>
        <input type="text" name="name" value="<?= e($p['name']) ?>" required>
      </div>

      <div class="form-row" style="margin-top: 12px;">
        <label class="label">Email Address (Registered Account)</label>
        <input type="email" value="<?= e($p['email']) ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
      </div>

      <div class="form-grid" style="margin-top: 12px;">
        <div class="form-row">
          <label class="label">Contact Phone</label>
          <input type="text" name="phone" value="<?= e($p['phone'] ?? '') ?>" placeholder="017...">
        </div>

        <div class="form-row">
          <label class="label">Age</label>
          <input type="number" name="age" value="<?= e($p['age'] ?? '') ?>" min="10" max="100">
        </div>
      </div>

      <div class="form-row" style="margin-top: 12px;">
        <label class="label">Skill Level</label>
        <input type="text" name="skill_level" value="<?= e($p['skill_level'] ?? '') ?>" placeholder="e.g. Advanced">
      </div>

      <div class="form-row" style="margin-top: 12px;">
        <label class="label">Home University / Affiliation</label>
        <input type="text" value="<?= e($p['institution_name'] ?: 'None specified') ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
      </div>

      <button class="btn" type="submit" style="margin-top: 18px;">Save Profile Changes</button>
    </form>
  </div>

  <!-- TOURNAMENTS & ROLE & PASSWORD -->
  <div style="display: flex; flex-direction: column; gap: 20px;">
    <!-- ENROLLED TOURNAMENTS CARD -->
    <div class="card">
      <span class="kicker">Multi-University Competitions</span>
      <h3 style="margin-top: 4px;">My Tournament Registrations</h3>
      
      <div class="mini-list" style="margin-top: 14px;">
        <?php foreach ($myComps as $mc): ?>
          <div class="mini-item" style="flex-direction: column; align-items: flex-start; gap: 4px; padding: 8px 0; border-bottom: 1px solid var(--glass-border);">
            <div style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
              <strong><?= e($mc['name']) ?></strong>
              <span class="badge <?= $mc['status'] === 'active' ? 'green' : 'yellow' ?>"><?= ucfirst(e($mc['status'])) ?></span>
            </div>
            <span class="muted" style="font-size: 12px;">
              Hosted by <strong><?= e($mc['host_name']) ?></strong> &middot; Registered on <?= date('d M Y', strtotime($mc['registered_at'])) ?>
            </span>
          </div>
        <?php endforeach; ?>
        <?php if (!$myComps): ?>
          <div class="empty">Not enrolled in any tournaments yet.</div>
        <?php endif; ?>
      </div>

      <?php if (!empty($unregisteredComps)): ?>
        <form method="post" action="profile.php" style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--glass-border);">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="enroll_competition">
          <label class="label" style="font-size: 12px; margin-bottom: 6px;">Register for another tournament:</label>
          <div style="display: flex; gap: 6px;">
            <select name="competition_id" required style="font-size: 13px;">
              <option value="">Choose competition...</option>
              <?php foreach ($unregisteredComps as $uc): ?>
                <option value="<?= $uc['competition_id'] ?>"><?= e($uc['name']) ?> (<?= e($uc['host_name']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <button class="btn small" type="submit">Join</button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <!-- TOURNAMENT ROLE CARD -->
    <div class="card">
      <span class="kicker">Tournament Role</span>
      <h3 style="margin-top: 4px;"><?= e($p['role_name']) ?></h3>

      <div class="mini-list" style="margin-top: 14px;">
        <div class="mini-item">
          <span class="muted">Home University</span>
          <strong><?= e($p['institution_name'] ?: 'Independent') ?></strong>
        </div>
        <div class="mini-item">
          <span class="muted">Assigned Team</span>
          <strong><?= e($p['team_name'] ?: 'Not on a team') ?></strong>
        </div>
        <?php if ($p['role_name'] === 'Judge'): ?>
          <div class="mini-item">
            <span class="muted">Experience</span>
            <strong><?= e($p['judge_experience'] ?: 'Standard') ?></strong>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- CHANGE PASSWORD CARD -->
    <div class="card">
      <h3>Change Password</h3>
      <form method="post" action="profile.php" style="margin-top: 14px;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="change_password">

        <div class="form-row">
          <label class="label">Current Password</label>
          <input type="password" name="current_password" placeholder="Enter current password" required>
        </div>

        <div class="form-row" style="margin-top: 10px;">
          <label class="label">New Password (min 8 chars)</label>
          <input type="password" name="new_password" minlength="8" placeholder="Enter new password" required>
        </div>

        <div class="form-row" style="margin-top: 10px;">
          <label class="label">Confirm New Password</label>
          <input type="password" name="confirm_password" minlength="8" placeholder="Repeat new password" required>
        </div>

        <button class="btn secondary" type="submit" style="margin-top: 16px;">Update Password</button>
      </form>
    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>
