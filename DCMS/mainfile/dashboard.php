<?php
require_once 'config/bootstrap.php';
require_login();

$u = current_user();
$pid = (int)$u['participant_id'];

// Handle enrollment into another competition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll_competition') {
    check_csrf();
    $targetCompId = (int)($_POST['competition_id'] ?? 0);
    if ($targetCompId > 0 && get_competition($targetCompId)) {
        exec_sql('INSERT IGNORE INTO competition_registration(competition_id, participant_id) VALUES(?, ?)', [$targetCompId, $pid]);
        $_SESSION['user_competition_id'] = $targetCompId;
        flash('success', 'Successfully registered for ' . get_competition($targetCompId)['name'] . '!');
        redirect('dashboard.php?comp_id=' . $targetCompId);
    }
}

$userCompId = user_active_competition_id($pid);
$activeComp = get_competition($userCompId);
$myComps    = user_competitions($pid);
$allComps   = all_competitions();

$title = 'Participant Dashboard · ' . ($activeComp['name'] ?? 'Debate Portal');

// Fetch participant profile info along with Debater/Team linkage in the active competition
$profile = one(
    'SELECT p.*, i.name AS institution_name, d.team_id, t.team_name, d.skill_level AS debater_skill
     FROM participant p
     LEFT JOIN institution i ON i.inst_id = p.inst_id
     LEFT JOIN debater d ON d.participant_id = p.participant_id
     LEFT JOIN team t ON t.team_id = d.team_id AND t.competition_id = ?
     WHERE p.participant_id = ?',
    [$userCompId, $pid]
);

$teamDebates = [];
$teamResults = [];

if (!empty($profile['team_id'])) {
    // 1. Debates involving this user's team in active competition
    $teamDebates = q(
        'SELECT a.assignment_id, a.date, a.motion, a.motion_status,
                r.round_name, r.round_num, r.start_time,
                ro.number AS room_number, ro.building,
                pi.side AS my_side,
                opp_pi.side AS opp_side,
                opp_t.team_name AS opponent_name,
                GROUP_CONCAT(DISTINCT jp.name ORDER BY jp.name SEPARATOR ", ") AS judge_names
         FROM participates_in pi
         JOIN debate_assignment a ON a.assignment_id = pi.assignment_id
         JOIN debate_round r ON r.round_id = a.round_id
         JOIN room ro ON ro.room_id = a.room_id
         LEFT JOIN participates_in opp_pi ON opp_pi.assignment_id = a.assignment_id AND opp_pi.team_id != pi.team_id
         LEFT JOIN team opp_t ON opp_t.team_id = opp_pi.team_id
         LEFT JOIN assignment_judge aj ON aj.assignment_id = a.assignment_id
         LEFT JOIN participant jp ON jp.participant_id = aj.participant_id
         WHERE pi.team_id = ? AND r.competition_id = ?
         GROUP BY a.assignment_id
         ORDER BY a.date, r.start_time',
        [$profile['team_id'], $userCompId]
    );

    // 2. Results & totals for this user's team in active competition
    $teamResults = q(
        'SELECT tt.total_points, tt.team_rank, tt.assignment_id,
                r.round_name, r.round_num, a.date
         FROM team_totals tt
         JOIN debate_assignment a ON a.assignment_id = tt.assignment_id
         JOIN debate_round r ON r.round_id = a.round_id
         WHERE tt.team_id = ? AND r.competition_id = ?
         ORDER BY a.date DESC, r.round_num DESC',
        [$profile['team_id'], $userCompId]
    );
}

// Active Tournament Snapshot stats
$counts = [
    'rounds'  => one('SELECT COUNT(*) c FROM debate_round WHERE competition_id=?', [$userCompId])['c'] ?? 0,
    'debates' => one('SELECT COUNT(*) c FROM debate_assignment a JOIN debate_round r ON r.round_id = a.round_id WHERE r.competition_id=?', [$userCompId])['c'] ?? 0,
    'teams'   => one('SELECT COUNT(*) c FROM team WHERE competition_id=?', [$userCompId])['c'] ?? 0,
    'rooms'   => one('SELECT COUNT(*) c FROM room WHERE competition_id=?', [$userCompId])['c'] ?? 0,
];

// Available competitions user is NOT yet enrolled in
$myCompIds = array_column($myComps, 'competition_id');
$unregisteredComps = array_filter($allComps, fn($c) => !in_array($c['competition_id'], $myCompIds));

include 'partials/header.php';
?>

<!-- TOURNAMENT SELECTION TABS -->
<?php if (!empty($myComps)): ?>
  <div style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; background: rgba(0,0,0,0.2); padding: 12px 18px; border-radius: var(--radius-md); border: 1px solid var(--glass-border);">
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
      <span style="font-size: 13px; font-weight: 700; color: var(--accent-bright); text-transform: uppercase; letter-spacing: 0.05em;">My Enrolled Tournaments:</span>
      <?php foreach ($myComps as $mc): ?>
        <a href="dashboard.php?comp_id=<?= $mc['competition_id'] ?>" 
           class="btn small <?= $mc['competition_id'] == $userCompId ? '' : 'secondary' ?>" 
           style="font-size: 12px;">
          <?= e($mc['name']) ?> (<?= e($mc['host_name']) ?>)
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($unregisteredComps)): ?>
      <div>
        <form method="post" action="dashboard.php" style="display: flex; gap: 6px; align-items: center; margin: 0;">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="enroll_competition">
          <select name="competition_id" required style="padding: 4px 8px; font-size: 12px; max-width: 240px;">
            <option value="">+ Register for another University Tournament</option>
            <?php foreach ($unregisteredComps as $uc): ?>
              <option value="<?= $uc['competition_id'] ?>">
                <?= e($uc['name']) ?> (<?= e($uc['host_name']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <button class="btn small secondary" type="submit">Join</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- HERO SECTION -->
<div class="hero">
  <div class="hero-card">
    <div>
      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
        <span class="kicker" style="margin-bottom: 0;">Participant Portal</span>
        <span class="badge green"><?= e($activeComp['host_name'] ?? 'Tournament') ?></span>
      </div>
      <h1>Welcome, <?= e($profile['name'] ?? $u['name'] ?? 'Debater') ?>!</h1>
      <p class="muted" style="margin-top: 6px;">
        Active Tournament: <strong style="color: var(--accent-bright); font-size: 1.05rem;"><?= e($activeComp['name'] ?? 'Open Debate') ?></strong>
        <br>
        <?php if (!empty($profile['team_name'])): ?>
          Competing with Team <strong><?= e($profile['team_name']) ?></strong> &middot; Affiliated with <strong><?= e($profile['institution_name'] ?: 'Independent') ?></strong>.
        <?php else: ?>
          Home University / Affiliation: <strong><?= e($profile['institution_name'] ?: 'Independent') ?></strong>.
        <?php endif; ?>
      </p>
    </div>

    <div class="actions" style="margin-top: 20px;">
      <a class="btn" href="schedule.php?comp=<?= $userCompId ?>">Browse <?= e($activeComp['host_name'] ?? '') ?> Schedule</a>
      <a class="btn secondary" href="results.php?comp=<?= $userCompId ?>">View Standings</a>
    </div>
  </div>

  <div class="card stat-card" style="display: flex; flex-direction: column; justify-content: space-between;">
    <div>
      <span class="kicker"><?= e($activeComp['host_name'] ?? '') ?> Snapshot</span>
      <div class="mini-list" style="margin-top: 10px;">
        <div class="mini-item">
          <span class="muted">Rounds</span>
          <strong><?= e($counts['rounds']) ?></strong>
        </div>
        <div class="mini-item">
          <span class="muted">Total Debates</span>
          <strong><?= e($counts['debates']) ?></strong>
        </div>
        <div class="mini-item">
          <span class="muted">Competing Teams</span>
          <strong><?= e($counts['teams']) ?></strong>
        </div>
        <div class="mini-item">
          <span class="muted">Venue Rooms</span>
          <strong><?= e($counts['rooms']) ?></strong>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MY TEAM'S DEBATES SECTION -->
<?php if (!empty($profile['team_id'])): ?>
  <div class="section">
    <div class="section-head">
      <div>
        <h2>My Team's Matchups &amp; Released Motions &middot; <?= e($activeComp['host_name'] ?? '') ?></h2>
        <p class="muted">All scheduled rounds for team <strong><?= e($profile['team_name']) ?></strong> in <strong><?= e($activeComp['name'] ?? '') ?></strong>.</p>
      </div>
      <a href="schedule.php?comp=<?= $userCompId ?>" class="btn small secondary">View Full Schedule</a>
    </div>

    <div class="grid grid-2">
      <?php foreach ($teamDebates as $d): ?>
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-left: 4px solid <?= $d['my_side'] === 'Proposition' ? 'var(--success)' : 'var(--accent-deep)' ?>;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
              <span class="badge">Round <?= e($d['round_num']) ?> &middot; <?= e($d['round_name']) ?></span>
              <span class="badge <?= $d['my_side'] === 'Proposition' ? 'green' : 'gray' ?>">
                Your Side: <?= e($d['my_side']) ?>
              </span>
            </div>

            <h3 style="margin-top: 4px; font-size: 1.25rem;">
              vs. <?= e($d['opponent_name'] ?: 'To Be Announced') ?>
            </h3>

            <div class="muted" style="font-size: 13px; margin: 8px 0 14px;">
              <strong>Date:</strong> <?= e(date('l, d M Y', strtotime($d['date']))) ?> &middot;
              <strong>Time:</strong> <?= e(date('h:i A', strtotime($d['start_time']))) ?>
              <br>
              <strong>Venue:</strong> Room <?= e($d['room_number']) ?> (<?= e($d['building']) ?>) &middot; <?= e($activeComp['host_name'] ?? '') ?>
            </div>

            <!-- MOTION BANNER -->
            <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--glass-border); border-radius: var(--radius-md); padding: 14px; margin-bottom: 14px;">
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                <span class="kicker" style="margin-bottom: 0;">Debate Motion</span>
                <?php if ($d['motion_status'] === 'released'): ?>
                  <span class="badge green" style="font-size: 11px;">Released by Adjudicators</span>
                <?php else: ?>
                  <span class="badge yellow" style="font-size: 11px;">Unreleased / Locked</span>
                <?php endif; ?>
              </div>

              <?php if ($d['motion_status'] === 'released' && !empty($d['motion'])): ?>
                <p style="font-size: 0.95rem; font-weight: 600; color: var(--text-primary); line-height: 1.5; margin: 0;">
                  &ldquo;<?= e($d['motion']) ?>&rdquo;
                </p>
              <?php else: ?>
                <p class="muted" style="font-size: 0.9rem; font-style: italic; margin: 0;">
                  The motion for this round has not been released yet by the tournament adjudicators.
                </p>
              <?php endif; ?>
            </div>

            <div style="font-size: 13px; margin-bottom: 14px;">
              <span class="muted">Assigned Judges:</span>
              <strong><?= e($d['judge_names'] ?: 'Pending panel allocation') ?></strong>
            </div>
          </div>

          <div style="padding-top: 12px; border-top: 1px solid var(--glass-border); display: flex; justify-content: flex-end;">
            <a class="btn small secondary" href="debate.php?id=<?= $d['assignment_id'] ?>">View Debate Details &amp; Scores &rarr;</a>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (!$teamDebates): ?>
        <div class="card empty" style="grid-column: 1 / -1;">
          No matches scheduled for your team in this tournament yet.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- MY RESULTS & STANDINGS -->
  <?php if (!empty($teamResults)): ?>
    <div class="section">
      <div class="section-head">
        <h2>My Team's Official Results &middot; <?= e($activeComp['host_name'] ?? '') ?></h2>
        <a href="results.php?comp=<?= $userCompId ?>" class="btn small secondary">Full Tournament Standings</a>
      </div>

      <div class="card table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Round</th>
              <th>Date</th>
              <th>Total Points Awarded</th>
              <th>Round Outcome</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($teamResults as $res): ?>
              <tr>
                <td><strong><?= e($res['round_name']) ?></strong> <span class="badge gray">Round <?= e($res['round_num']) ?></span></td>
                <td><?= e(date('d M Y', strtotime($res['date']))) ?></td>
                <td><strong style="font-size: 1.05rem; color: var(--accent-bright);"><?= e($res['total_points']) ?> pts</strong></td>
                <td>
                  <span class="badge <?= $res['team_rank'] == 1 ? 'green' : 'gray' ?>">
                    <?= $res['team_rank'] == 1 ? 'Rank #1 &middot; Victory' : 'Rank #' . e($res['team_rank'] ?? '—') ?>
                  </span>
                </td>
                <td>
                  <a class="btn small secondary" href="debate.php?id=<?= $res['assignment_id'] ?>">Speaker Breakdown</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

<?php else: ?>
  <!-- NON-DEBATER / GENERAL PARTICIPANT NOTICE -->
  <div class="section card" style="text-align: center; padding: 40px 24px;">
    <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(99,102,241,0.15); color: var(--accent); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
    </div>
    <h2>Registered for <?= e($activeComp['name'] ?? 'Tournament') ?></h2>
    <p class="muted" style="max-width: 520px; margin: 8px auto 20px;">
      You are enrolled in this tournament hosted by <strong><?= e($activeComp['host_name'] ?? 'University') ?></strong>. To debate in official rounds, the tournament administrators will allocate you to a team delegation.
    </p>
    <div class="actions" style="justify-content: center;">
      <a class="btn" href="schedule.php?comp=<?= $userCompId ?>">Browse Tournament Schedule</a>
      <a class="btn secondary" href="profile.php">View Profile &amp; Enrollments</a>
    </div>
  </div>
<?php endif; ?>

<?php include 'partials/footer.php'; ?>
