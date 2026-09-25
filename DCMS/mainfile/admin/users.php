<?php
$admin_area = true;
require_once '../config/bootstrap.php';
require_admin();

$title = 'Manage Users & Registrations';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId > 0) {
        try {
            if ($action === 'approve') {
                exec_sql('UPDATE auth_user SET status="approved" WHERE user_id=?', [$userId]);
                flash('success', 'User account approved successfully.');
            } elseif ($action === 'reject') {
                exec_sql('UPDATE auth_user SET status="rejected" WHERE user_id=?', [$userId]);
                flash('success', 'User account status set to rejected.');
            }
            redirect('users.php');
        } catch (Throwable $e) {
            $error = 'Failed to update user: ' . $e->getMessage();
        }
    }
}

$users = q('SELECT au.user_id, au.email, au.role, au.status, au.created_at,
            p.participant_id, p.name, p.phone, p.skill_level,
            i.name AS institution_name,
            (SELECT GROUP_CONCAT(c.name SEPARATOR ", ") FROM competition_registration cr JOIN competition c ON c.competition_id = cr.competition_id WHERE cr.participant_id = p.participant_id) AS registered_competitions
            FROM auth_user au
            LEFT JOIN participant p ON p.participant_id = au.participant_id
            LEFT JOIN institution i ON i.inst_id = p.inst_id
            ORDER BY
              CASE au.status WHEN "pending" THEN 0 WHEN "approved" THEN 1 ELSE 2 END,
              au.created_at DESC');

$pendingUsers = array_filter($users, fn($u) => $u['status'] === 'pending');

include '../partials/header.php';
?>

<div class="section-head">
  <div>
    <span class="kicker">Admin &middot; Access Control</span>
    <h1>User Registrations &amp; Accounts</h1>
    <p class="muted">Review pending participant accounts across all university tournaments, approve verified debaters/judges, or revoke access.</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="flash error"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!empty($pendingUsers)): ?>
  <div class="section">
    <div class="section-head">
      <h2>Pending Confirmation <span class="badge yellow"><?= count($pendingUsers) ?> Waiting</span></h2>
    </div>

    <div class="card table-wrap" style="border-color: rgba(251, 191, 36, 0.3);">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Affiliation</th>
            <th>Tournament Registered</th>
            <th>Contact</th>
            <th>Registered At</th>
            <th>Decision</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingUsers as $pu): ?>
            <tr>
              <td><strong><?= e($pu['name'] ?? '—') ?></strong></td>
              <td><?= e($pu['email']) ?></td>
              <td><?= e($pu['institution_name'] ?? 'Not specified') ?></td>
              <td>
                <span class="badge green" style="font-size: 11px;">
                  <?= e($pu['registered_competitions'] ?? 'General Signup') ?>
                </span>
              </td>
              <td><?= e($pu['phone'] ?? '—') ?></td>
              <td><?= e(date('d M Y, h:i A', strtotime($pu['created_at']))) ?></td>
              <td>
                <div class="actions">
                  <form method="post" action="users.php" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="user_id" value="<?= $pu['user_id'] ?>">
                    <button class="btn small success" type="submit">Approve</button>
                  </form>

                  <form method="post" action="users.php" style="display:inline;" onsubmit="return confirm('Reject this user registration?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="user_id" value="<?= $pu['user_id'] ?>">
                    <button class="btn small danger" type="submit">Reject</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="section">
  <div class="section-head">
    <h2>All User Accounts</h2>
  </div>

  <div class="card table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>User ID</th>
          <th>Name / Account</th>
          <th>Home University</th>
          <th>Enrolled Competitions</th>
          <th>Role</th>
          <th>Status</th>
          <th>Registered</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $row): ?>
          <tr>
            <td>#<?= e($row['user_id']) ?></td>
            <td>
              <strong><?= e($row['name'] ?: 'Administrator') ?></strong>
              <br>
              <span class="muted" style="font-size: 13px;"><?= e($row['email']) ?></span>
            </td>
            <td><?= e($row['institution_name'] ?? '—') ?></td>
            <td style="max-width: 220px;">
              <span style="font-size: 12px; color: var(--accent-bright); font-weight: 500;">
                <?= e($row['registered_competitions'] ?? 'None') ?>
              </span>
            </td>
            <td>
              <span class="badge <?= $row['role'] === 'admin' ? '' : 'gray' ?>">
                <?= ucfirst(e($row['role'])) ?>
              </span>
            </td>
            <td>
              <?php if ($row['status'] === 'approved'): ?>
                <span class="badge green">Approved</span>
              <?php elseif ($row['status'] === 'pending'): ?>
                <span class="badge yellow">Pending</span>
              <?php else: ?>
                <span class="badge red">Rejected</span>
              <?php endif; ?>
            </td>
            <td><?= e(date('d M Y', strtotime($row['created_at']))) ?></td>
            <td>
              <?php if ($row['role'] !== 'admin'): ?>
                <?php if ($row['status'] === 'approved'): ?>
                  <form method="post" action="users.php" onsubmit="return confirm('Revoke approval for this user?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                    <button class="btn small danger" type="submit">Revoke</button>
                  </form>
                <?php else: ?>
                  <form method="post" action="users.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                    <button class="btn small success" type="submit">Approve</button>
                  </form>
                <?php endif; ?>
              <?php else: ?>
                <span class="muted" style="font-size: 12px;">Protected Admin</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../partials/footer.php'; ?>
