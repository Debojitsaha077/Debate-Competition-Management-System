<?php
require_once 'config/bootstrap.php';
require_login();

$u   = current_user();
$pid = (int)$u['participant_id'];

$allComps = all_competitions();
$userCompId = user_active_competition_id($pid);

// Selected competition in schedule filter
$selectedCompId = !empty($_GET['comp']) ? (int)$_GET['comp'] : $userCompId;
if (!get_competition($selectedCompId)) {
    $selectedCompId = $userCompId;
}

$comp = get_competition($selectedCompId);

$title = 'Tournament Schedule · ' . ($comp['name'] ?? 'Debate Matches');

$search = trim($_GET['q'] ?? '');
$round  = (int)($_GET['round'] ?? 0);
$room   = (int)($_GET['room'] ?? 0);
$side   = trim($_GET['side'] ?? '');
$date   = trim($_GET['date'] ?? '');

$sql = 'SELECT a.*, r.round_name, r.round_num, r.start_time, ro.number AS room_number, ro.building,
        GROUP_CONCAT(CONCAT(t.team_name, " [", pi.side, "]") ORDER BY pi.side SEPARATOR " vs ") AS matchup 
        FROM debate_assignment a 
        JOIN debate_round r ON r.round_id = a.round_id 
        JOIN room ro ON ro.room_id = a.room_id 
        LEFT JOIN participates_in pi ON pi.assignment_id = a.assignment_id 
        LEFT JOIN team t ON t.team_id = pi.team_id 
        WHERE r.competition_id = ?';

$params = [$selectedCompId];

if ($search !== '') {
    $sql .= ' AND (t.team_name LIKE ? OR r.round_name LIKE ? OR ro.number LIKE ? OR ro.building LIKE ? OR CAST(a.assignment_id AS CHAR) LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like);
}

if ($round) {
    $sql .= ' AND a.round_id = ?';
    $params[] = $round;
}

if ($room) {
    $sql .= ' AND a.room_id = ?';
    $params[] = $room;
}

if ($side) {
    $sql .= ' AND pi.side = ?';
    $params[] = $side;
}

if ($date) {
    $sql .= ' AND a.date = ?';
    $params[] = $date;
}

$sql .= ' GROUP BY a.assignment_id ORDER BY a.date, r.start_time, a.assignment_id';
$rows = q($sql, $params);

// Scoped strictly to the selected tournament/university
$rounds = q('SELECT * FROM debate_round WHERE competition_id = ? ORDER BY round_num', [$selectedCompId]);
$rooms  = q('SELECT * FROM room WHERE competition_id = ? ORDER BY building, number', [$selectedCompId]);

include 'partials/header.php';
?>

<div class="section-head">
  <div>
    <span class="kicker">Schedule &middot; Match Finder</span>
    <h1>Tournament Schedule &amp; Pairings</h1>
    <p class="muted">Search debate matches, venues, and released motions for any university tournament.</p>
  </div>
</div>

<!-- TOURNAMENT SELECTOR BAR -->
<div class="card" style="margin-bottom: 20px; background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(0,0,0,0.2)); border: 1px solid var(--glass-border);">
  <form method="get" action="schedule.php" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin: 0;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-bright);">
        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
        <path d="M6 12v5c3 3 9 3 12 0v-5"/>
      </svg>
      <label style="font-weight: 700; font-size: 14px; color: var(--text-primary);">Select Tournament / University:</label>
      <select name="comp" onchange="this.form.submit()" style="padding: 6px 12px; font-size: 14px; font-weight: 600; min-width: 320px;">
        <?php foreach ($allComps as $ac): ?>
          <option value="<?= $ac['competition_id'] ?>" <?= $ac['competition_id'] == $selectedCompId ? 'selected' : '' ?>>
            <?= e($ac['name']) ?> &middot; <?= e($ac['host_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <span class="badge green">Venue: <?= e($comp['host_name'] ?? 'University') ?></span>
    </div>
  </form>
</div>

<!-- SEARCH & FILTER FORM -->
<div class="card">
  <form class="search-grid" method="get" action="schedule.php">
    <input type="hidden" name="comp" value="<?= $selectedCompId ?>">
    
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search team, room, round, ID...">

    <select name="round">
      <option value="">All Rounds in <?= e($comp['host_name']) ?></option>
      <?php foreach ($rounds as $r): ?>
        <option value="<?= $r['round_id'] ?>" <?= $round == $r['round_id'] ? 'selected' : '' ?>>
          Round <?= e($r['round_num']) ?> &middot; <?= e($r['round_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <!-- CRITICAL REQUIREMENT: Rooms ONLY show the selected university's rooms -->
    <select name="room">
      <option value="">All Rooms in <?= e($comp['host_name']) ?></option>
      <?php foreach ($rooms as $rm): ?>
        <option value="<?= $rm['room_id'] ?>" <?= $room == $rm['room_id'] ? 'selected' : '' ?>>
          <?= e($rm['number']) ?> (<?= e($rm['building']) ?>)
        </option>
      <?php endforeach; ?>
    </select>

    <select name="side">
      <option value="">Either Side</option>
      <option value="Proposition" <?= $side === 'Proposition' ? 'selected' : '' ?>>Proposition</option>
      <option value="Opposition" <?= $side === 'Opposition' ? 'selected' : '' ?>>Opposition</option>
    </select>

    <div style="display: flex; gap: 8px;">
      <input type="date" name="date" value="<?= e($date) ?>" style="min-width: 140px;">
      <button class="btn" type="submit">Filter</button>
    </div>
  </form>
</div>

<!-- RESULTS TABLE -->
<div class="section">
  <div class="section-head">
    <h2><?= e($comp['name']) ?> Matches (<?= count($rows) ?>)</h2>
  </div>

  <div class="card table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Round</th>
          <th>Date &amp; Time</th>
          <th>Venue Room (<?= e($comp['host_name']) ?>)</th>
          <th>Matchup</th>
          <th>Motion</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong>#<?= e($r['assignment_id']) ?></strong></td>
            <td>
              <strong><?= e($r['round_name']) ?></strong>
              <br>
              <span class="badge gray">Round <?= e($r['round_num']) ?></span>
            </td>
            <td>
              <?= e(date('d M Y', strtotime($r['date']))) ?>
              <br>
              <span class="muted"><?= e(date('h:i A', strtotime($r['start_time']))) ?></span>
            </td>
            <td>
              <strong><?= e($r['room_number']) ?></strong>
              <br>
              <span class="muted"><?= e($r['building']) ?></span>
            </td>
            <td>
              <strong><?= e($r['matchup'] ?? 'Not assigned') ?></strong>
            </td>
            <td style="max-width: 220px;">
              <?php if ($r['motion_status'] === 'released' && !empty($r['motion'])): ?>
                <span title="<?= e($r['motion']) ?>">
                  <?= e(mb_strimwidth($r['motion'], 0, 55, '...')) ?>
                </span>
              <?php else: ?>
                <span class="muted" style="font-size: 13px; font-style: italic;">Locked until round release</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn small secondary" href="debate.php?id=<?= $r['assignment_id'] ?>">View Debate</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="7" class="empty">No debate matches scheduled in this tournament matching those filter criteria.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'partials/footer.php'; ?>
