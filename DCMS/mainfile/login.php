<?php
require_once 'config/bootstrap.php';

if (current_user()) {
    redirect(current_user()['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $u = one(
        'SELECT au.*, p.name FROM auth_user au LEFT JOIN participant p ON p.participant_id=au.participant_id WHERE au.email=? AND au.role="user"',
        [$email]
    );

    if ($u && password_verify($pass, $u['password_hash'])) {
        if ($u['status'] === 'pending') {
            $error = 'Your account is pending admin approval. Please check back later.';
        } elseif ($u['status'] === 'rejected') {
            $error = 'Your registration was rejected by an administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = $u;
            redirect('dashboard.php');
        }
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Login · Debate Manager</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="auth-shell">
    <div class="auth">
      <span class="kicker">Debate Event Manager</span>
      <h1>User Login</h1>
      <p class="muted">Sign in to view your team's debates, motions, and official results.</p>

      <?php if ($f = pull_flash()): ?>
        <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="flash error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="form-row">
          <label class="label">Email Address</label>
          <input type="email" name="email" placeholder="you@example.com" required autofocus>
        </div>

        <div class="form-row">
          <label class="label">Password</label>
          <input type="password" name="password" placeholder="Enter your password" required>
        </div>

        <button class="btn" type="submit">Log in</button>
      </form>

      <div class="auth-foot">
        New participant? <a href="signup.php">Create an account</a>
        <br>
        <a href="admin-login.php" style="display:inline-block; margin-top:10px; color:var(--text-muted); font-weight:normal; font-size:13px;">Admin console login &rarr;</a>
      </div>
    </div>
  </div>
</body>
</html>