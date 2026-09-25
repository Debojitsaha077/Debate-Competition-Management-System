<?php
require_once 'config/bootstrap.php';

if (current_user() && current_user()['role'] === 'admin') {
    redirect('admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $u = one(
        'SELECT au.*, p.name FROM auth_user au LEFT JOIN participant p ON p.participant_id=au.participant_id WHERE au.email=? AND au.role="admin"',
        [$email]
    );

    if ($u && password_verify($pass, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = $u;
        redirect('admin/dashboard.php');
    }

    $error = 'Invalid administrator credentials.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login · Debate Manager</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="auth-shell">
    <div class="auth">
      <span class="kicker">Restricted Console</span>
      <h1>Administrator Login</h1>
      <p class="muted">Access the tournament management console to control rounds, assignments, motions, user confirmations, and scores.</p>

      <?php if ($error): ?>
        <div class="flash error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="admin-login.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="form-row">
          <label class="label">Admin Email</label>
          <input type="email" name="email" placeholder="admin@debate.local" required autofocus>
        </div>

        <div class="form-row">
          <label class="label">Password</label>
          <input type="password" name="password" placeholder="Enter admin password" required>
        </div>

        <button class="btn" type="submit">Enter admin console</button>
      </form>

      <div class="auth-foot">
        <a href="login.php">&larr; Back to participant login</a>
      </div>
    </div>
  </div>
</body>
</html>
