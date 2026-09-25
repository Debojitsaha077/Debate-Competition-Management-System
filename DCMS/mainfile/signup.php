<?php
require_once 'config/bootstrap.php';

if (current_user()) {
    redirect(current_user()['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $age     = (int)($_POST['age'] ?? 0);
    $inst    = (int)($_POST['inst_id'] ?? 0);
    $compId  = (int)($_POST['competition_id'] ?? 0);
    $skill   = trim($_POST['skill_level'] ?? '');
    $pass    = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8 || $inst < 1 || $compId < 1) {
        $error = 'Please complete all required fields including choosing a tournament to enter. Password must be at least 8 characters.';
    } elseif (one('SELECT user_id FROM auth_user WHERE email=?', [$email])) {
        $error = 'An account with this email address already exists.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $s = $pdo->prepare('INSERT INTO participant(email, name, phone, age, skill_level, inst_id) VALUES(?, ?, ?, ?, ?, ?)');
            $s->execute([$email, $name, $phone ?: null, $age ?: null, $skill ?: null, $inst]);

            $pid = (int)$pdo->lastInsertId();

            $s = $pdo->prepare('INSERT INTO auth_user(participant_id, email, password_hash, role, status) VALUES(?, ?, ?, "user", "pending")');
            $s->execute([$pid, $email, password_hash($pass, PASSWORD_DEFAULT)]);

            // Register for the selected competition
            $s = $pdo->prepare('INSERT INTO competition_registration(competition_id, participant_id) VALUES(?, ?)');
            $s->execute([$compId, $pid]);

            $pdo->commit();
            flash('success', 'Account created and registered for tournament! An administrator will confirm your account shortly.');
            redirect('login.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Could not create account: ' . $e->getMessage();
        }
    }
}

$insts = q('SELECT * FROM institution ORDER BY name');
$comps = all_competitions();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Account · Debate Manager</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="auth-shell">
    <div class="auth" style="width: min(560px, 100%);">
      <span class="kicker">Multi-University Debate Portal</span>
      <h1>Create account &amp; Register</h1>
      <p class="muted">Register as a debater or judge to participate in any university's debate competition.</p>

      <?php if ($error): ?>
        <div class="flash error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="signup.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="form-row">
          <label class="label">Full Name *</label>
          <input type="text" name="name" placeholder="e.g. Alfi Shahariar" required>
        </div>

        <div class="form-row">
          <label class="label">Email Address *</label>
          <input type="email" name="email" placeholder="you@university.edu" required>
        </div>

        <div class="form-grid">
          <div class="form-row">
            <label class="label">Phone Number</label>
            <input type="text" name="phone" placeholder="01700000000">
          </div>

          <div class="form-row">
            <label class="label">Age</label>
            <input type="number" min="10" max="100" name="age" placeholder="22">
          </div>

          <div class="form-row">
            <label class="label">My University / Institution *</label>
            <select name="inst_id" required>
              <option value="">Select Your University</option>
              <?php foreach ($insts as $i): ?>
                <option value="<?= $i['inst_id'] ?>"><?= e($i['name']) ?> (<?= e($i['type']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-row">
            <label class="label">Skill Level</label>
            <input type="text" name="skill_level" placeholder="Novice / Intermediate / Advanced">
          </div>
        </div>

        <div class="form-row" style="background: rgba(99, 102, 241, 0.08); padding: 14px; border-radius: var(--radius-md); border: 1px solid rgba(99, 102, 241, 0.3);">
          <label class="label" style="color: var(--accent-bright); font-weight: 700;">Target Debate Competition *</label>
          <select name="competition_id" required style="font-weight: 600;">
            <option value="">Select Competition to Enter</option>
            <?php foreach ($comps as $c): ?>
              <option value="<?= $c['competition_id'] ?>">
                <?= e($c['name']) ?> &middot; Hosted by <?= e($c['host_name']) ?> (<?= ucfirst(e($c['status'])) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <span class="muted" style="font-size: 12px; display: block; margin-top: 6px;">
            You can register for any university's tournament regardless of your home institution.
          </span>
        </div>

        <div class="form-row">
          <label class="label">Password (min 8 characters) *</label>
          <input type="password" name="password" minlength="8" placeholder="Create a secure password" required>
        </div>

        <button class="btn" type="submit">Submit registration</button>
      </form>

      <div class="auth-foot">
        Already registered? <a href="login.php">Log in here</a>
      </div>
    </div>
  </div>
</body>
</html>