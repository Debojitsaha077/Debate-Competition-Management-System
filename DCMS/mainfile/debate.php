<?php
require_once 'config/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$a = one(
    'SELECT a.*, r.round_name, r.round_num, r.start_time, ro.number AS room_number, ro.building,
            c.competition_id, c.name AS competition_name, i.name AS host_name
     FROM debate_assignment a 
     JOIN debate_round r ON r.round_id = a.round_id 
     JOIN room ro ON ro.room_id = a.room_id 
     JOIN competition c ON c.competition_id = r.competition_id
     JOIN institution i ON i.inst_id = c.host_inst_id
     WHERE a.assignment_id = ?',
    [$id]
);

if (!$a) {
    http_response_code(404);
    exit('Debate match not found.');
}


$teams = q(
    'SELECT t.*, pi.side 
     FROM participates_in pi 
     JOIN team t ON t.team_id = pi.team_id 
     WHERE pi.assignment_id = ? 
     ORDER BY pi.side',
    [$id]
);

$judges = q(
    'SELECT p.name, p.email, j.judge_experience 
     FROM assignment_judge aj 
     JOIN judge j ON j.participant_id = aj.participant_id 
     JOIN participant p ON p.participant_id = j.participant_id 
     WHERE aj.assignment_id = ?',
    [$id]
);

$scores = q(
    'SELECT p.name, t.team_name, se.speaker_score 
     FROM score_entry se 
     JOIN participant p ON p.participant_id = se.participant_id 
     JOIN team t ON t.team_id = se.team_id 
     WHERE se.assignment_id = ? 
     ORDER BY t.team_name, p.name',
    [$id]
);

$totals = q(
    'SELECT tt.total_points, tt.team_rank, t.team_name 
     FROM team_totals tt 
     JOIN team t ON t.team_id = tt.team_id 
     WHERE tt.assignment_id = ? 
     ORDER BY tt.team_rank, tt.total_points DESC',
    [$id]
);

$title = 'Debate #' . $id . ' · ' . $a['round_name'];

include 'partials/header.php';
?>

<div class="section-head">
  <div>
    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
      <span class="badge" style="background: var(--accent); color: #fff; font-weight: 700;"><?= e($a['competition_name']) ?></span>
      <span class="badge green">Venue: <?= e($a['host_name']) ?></span>
    </div>
    <h1><?= e($a['round_name']) ?> <span class="badge">Round <?= e($a['round_num']) ?></span></h1>
    <p class="muted">
      <?= e(date('l, d F Y', strtotime($a['date']))) ?> &middot;
      <?= e(date('h:i A', strtotime($a['start_time']))) ?> &middot;
      Room <?= e($a['room_number']) ?>, <?= e($a['building']) ?> (<?= e($a['host_name']) ?>)
    </p>
  </div>
  <a class="btn secondary" href="schedule.php?comp=<?= $a['competition_id'] ?>">&larr; Back to Schedule</a>
</div>

<!-- MOTION CARD -->
<div class="card" style="margin-bottom: 24px; border-color: rgba(99, 102, 241, 0.25);">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
    <span class="kicker" style="margin-bottom: 0;">Official Motion</span>
    <?php if ($a['motion_status'] === 'released'): ?>
      <span class="badge green">Released by Adjudicators</span>
    <?php else: ?>
      <span class="badge yellow">Draft / Unreleased</span>
    <?php endif; ?>
  </div>

  <?php if ($a['motion_status'] === 'released' && !empty($a['motion'])): ?>
    <p style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary); line-height: 1.5; margin: 4px 0 0;">
      &ldquo;<?= e($a['motion']) ?>&rdquo;
    </p>
  <?php else: ?>
    <p class="muted" style="font-size: 1rem; font-style: italic; margin: 4px 0 0;">
      The motion for this debate match has not been released yet by tournament administration.
    </p>
  <?php endif; ?>
</div>

<!-- TEAMS & JUDGES GRID -->
<div class="grid grid-2">
  <div class="card">
    <h3>Competing Teams &amp; Allocations</h3>
    <div class="mini-list" style="margin-top: 14px;">
      <?php foreach ($teams as $t): ?>
        <div class="mini-item">
          <strong><?= e($t['team_name']) ?></strong>
          <span class="badge <?= $t['side'] === 'Proposition' ? 'green' : 'gray' ?>">
            <?= e($t['side']) ?>
          </span>
        </div>
      <?php endforeach; ?>
      <?php if (!$teams): ?>
        <div class="empty">Teams not yet assigned.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h3>Panel of Adjudicators</h3>
    <div class="mini-list" style="margin-top: 14px;">
      <?php foreach ($judges as $j): ?>
        <div class="mini-item">
          <div>
            <strong><?= e($j['name']) ?></strong>
            <br>
            <span class="muted" style="font-size: 13px;"><?= e($j['judge_experience']) ?></span>
          </div>
          <span class="badge gray"><?= e($j['email']) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$judges): ?>
        <div class="empty">No judges allocated for this match.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- SCORES & TOTALS SECTION -->
<div class="section grid grid-2">
  <div class="card">
    <h3>Speaker Scores</h3>
    <div class="table-wrap" style="margin-top: 14px;">
      <table class="table">
        <thead>
          <tr>
            <th>Debater</th>
            <th>Team</th>
            <th>Points</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($scores as $s): ?>
            <tr>
              <td><strong><?= e($s['name']) ?></strong></td>
              <td><?= e($s['team_name']) ?></td>
              <td><span class="badge green"><strong><?= e($s['speaker_score']) ?></strong></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$scores): ?>
            <tr>
              <td colspan="3" class="empty">Speaker scores have not been published yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h3>Official Team Outcome</h3>
    <div class="table-wrap" style="margin-top: 14px;">
      <table class="table">
        <thead>
          <tr>
            <th>Rank</th>
            <th>Team</th>
            <th>Total Points</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($totals as $t): ?>
            <tr>
              <td>
                <span class="badge <?= $t['team_rank'] == 1 ? 'green' : 'gray' ?>">
                  Rank #<?= e($t['team_rank'] ?? '—') ?>
                </span>
              </td>
              <td><strong><?= e($t['team_name']) ?></strong></td>
              <td><strong><?= e($t['total_points']) ?> pts</strong></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$totals): ?>
            <tr>
              <td colspan="3" class="empty">Official totals not submitted yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>
