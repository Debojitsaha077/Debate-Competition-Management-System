<?php
require_once 'config/bootstrap.php';

$u = current_user();

if (!$u) {
    redirect('login.php');
}

if ($u['role'] === 'admin') {
    redirect('admin/dashboard.php');
}

if ($u['status'] === 'approved') {
    redirect('dashboard.php');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Awaiting Approval · Debate Manager</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="auth-shell">
    <div class="auth" style="text-align: center;">
      <div style="width: 60px; height: 60px; border-radius: 50%; background: var(--warning-bg); color: var(--warning); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
      </div>

      <span class="kicker">Account Status</span>
      <h1>Awaiting Admin Approval</h1>
      <p class="muted" style="margin-top: 8px; line-height: 1.6;">
        Your account registration has been submitted and is currently pending administrator confirmation.
      </p>

      <div class="notice" style="margin: 20px 0; text-align: left;">
        Once an admin approves your account, you will have full access to view your team assignments, debate rooms, judges, released motions, and live scores.
      </div>

      <a class="btn secondary" href="logout.php" style="width: 100%;">Log out &amp; return to login</a>
    </div>
  </div>
</body>
</html>
