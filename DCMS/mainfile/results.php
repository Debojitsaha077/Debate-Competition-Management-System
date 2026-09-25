<?php
require_once 'config/bootstrap.php';
require_login();

$u   = current_user();
$pid = (int)$u['participant_id'];

$allComps = all_competitions();
$userCompId = user_active_competition_id($pid);

$selectedCompId = !empty($_GET['comp']) ? (int)$_GET['comp'] : $userCompId;
if (!get_competition($selectedCompId)) {
    $selectedCompId = $userCompId;
}

$comp = get_competition($selectedCompId);
$title = 'Standings & Results · ' . ($comp['name'] ?? 'Leaderboard');

$qterm = trim($_GET['q'] ?? '');

$sql = 'SELECT t.team_id, t.team_name, COALESCE(SUM(tt.total_points), 0) AS total_points,
        COUNT(tt.assignment_id) AS matches_counted
        FROM team t 
        LEFT JOIN team_totals tt ON tt.team_id = t.team_id 
        WHERE t.competition_id = ?
        GROUP BY t.team_id 
        ORDER BY total_points DESC, t.team_name ASC';
$all = q($sql, [$selectedCompId]);

if ($qterm !== '') {
    $all = array_values(array_filter($all, fn($x) => stripos($x['team_name'], $qterm) !== false));
}

$recent = q('SELECT a.assignment_id, a.date, r.round_name, r.round_num, t.team_name, tt.total_points, tt.team_rank 
             FROM team_totals tt 
             JOIN debate_assignment a ON a.assignment_id = tt.assignment_id 
             JOIN debate_round r ON r.round_id = a.round_id 
             JOIN team t ON t.team_id = tt.team_id 
             WHERE r.competition_id = ?
             ORDER BY a.date DESC, r.round_num DESC, tt.team_rank ASC 
             LIMIT 16', [$selectedCompId]);

include 'partials/header.php';
?>

<div class="section-head">
  <div>
    <span class="kicker">Official Tabulation &middot; <?= e($comp['host_name'] ?? '') ?></span>
    <h1>Leaderboard &amp; Standings</h1>
    <p class="muted">Live cumulative tournament rankings and verified debate results for <strong><?= e($comp['name'] ?? '') ?></strong>.</p>
  </div>
</div>

<!-- TOURNAMENT SELECTOR -->
<div class="card" style="margin-bottom: 20px; background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(0,0,0,0.2)); border: 1px solid var(--glass-border);">
  <form method="get" action="results.php" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin: 0;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-bright);">
        <circle cx="12" cy="8" r="7"/>
        <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
      </svg>
      <label style="font-weight: 700; font-size: 14px; color: var(--text-primary);">Tournament Standings:</label>
      <select name="comp" onchange="this.form.submit()" style="padding: 6px 12px; font-size: 14px; font-weight: 600; min-width: 320px;">
        <?php foreach ($allComps as $ac): ?>
          <option value="<?= $ac['competition_id'] ?>" <?= $ac['competition_id'] == $selectedCompId ? 'selected' : '' ?>>
            <?= e($ac['name']) ?> (<?= e($ac['host_name']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<div class="card">
  <form class="toolbar" method="get" action="results.php">
    <input type="hidden" name="comp" value="<?= $selectedCompId ?>">
    <input type="text" name="q" value="<?= e($qterm) ?>" placeholder="Search team by name in <?= e($comp['host_name']) ?>..." style="max-width: 420px;">
    <button class="btn" type="submit">Search Standings</button>
    <?php if ($qterm !== ''): ?>
      <a class="btn secondary" href="results.php?comp=<?= $selectedCompId ?>">Reset Filter</a>
    <?php endif; ?>
  </form>
</div>

<div class="section grid grid-2">
  <!-- CUMULATIVE TEAM RANKINGS -->
  <div class="card">
    <h3><?= e($comp['host_name']) ?> Team Leaderboard</h3>
    <div class="table-wrap" style="margin-top: 14px;">
      <table class="table">
        <thead>
          <tr>
            <th>Rank</th>
            <th>Team</th>
            <th>Matches</th>
            <th>Cumulative Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all as $i => $t): ?>
            <tr>
              <td>
                <span class="badge <?= $i === 0 ? 'green' : ($i < 3 ? '' : 'gray') ?>">
                  #<?= $i + 1 ?>
                </span>
              </td>
              <td><strong><?= e($t['team_name']) ?></strong></td>
              <td><?= e($t['matches_counted']) ?></td>
              <td>
                <strong style="color: var(--accent-bright); font-size: 1.05rem;">
                  <?= e(number_format((float)$t['total_points'], 2)) ?> pts
                </strong>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$all): ?>
            <tr>
              <td colspan="4" class="empty">No teams registered or match totals scored for this tournament yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- RECENT DEBATE RESULTS -->
  <div class="card">
    <h3>Recent Match Outcomes</h3>
    <div class="table-wrap" style="margin-top: 14px;">
      <table class="table">
        <thead>
          <tr>
            <th>Round</th>
            <th>Team</th>
            <th>Outcome</th>
            <th>Points</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td>
                <strong><?= e($r['round_name']) ?></strong>
                <br>
                <span class="muted" style="font-size:12px;"><?= e(date('d M Y', strtotime($r['date']))) ?></span>
              </td>
              <td><strong><?= e($r['team_name']) ?></strong></td>
              <td>
                <span class="badge <?= $r['team_rank'] == 1 ? 'green' : 'gray' ?>">
                  <?= $r['team_rank'] == 1 ? 'Victory (Rank #1)' : 'Rank #' . e($r['team_rank'] ?? '—') ?>
                </span>
              </td>
              <td><strong><?= e($r['total_points']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recent): ?>
            <tr>
              <td colspan="4" class="empty">No match scores submitted yet for this tournament.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>
