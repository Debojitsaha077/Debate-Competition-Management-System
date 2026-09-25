<?php
require_once 'config/bootstrap.php';

if ($u = current_user()) {
    if ($u['role'] === 'admin') {
        redirect('admin/dashboard.php');
    } else {
        redirect($u['status'] === 'approved' ? 'dashboard.php' : 'pending.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Debate Event &amp; Delegation Manager</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="landing-hero">
    <div class="kicker">Tournament Platform</div>
    <h1>Where Arguments<br>Become Art</h1>
    <p class="muted" style="max-width: 540px; margin-top: 14px; font-size: 1.1rem; line-height: 1.6; position: relative; z-index: 1;">
      An all-in-one tournament management system for university and club debate competitions. Track schedules, motions, pairings, and official speaker scores in real time.
    </p>

    <div class="landing-actions">
      <a class="btn" href="login.php" style="padding: 14px 28px; font-size: 1rem;">Participant Log in</a>
      <a class="btn secondary" href="signup.php" style="padding: 14px 28px; font-size: 1rem;">Register Account</a>
    </div>

    <div style="margin-top: 36px; position: relative; z-index: 1;">
      <a href="admin-login.php" style="color: var(--text-muted); font-size: 13px; text-decoration: none;">
        Administrator Console &rarr;
      </a>
    </div>
  </div>
</body>
</html>
