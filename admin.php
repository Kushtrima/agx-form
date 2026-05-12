<?php
/**
 * Admin view — list all submissions, expand for detail.
 * Protected by HTTP Basic auth. Override AGX_ADMIN_USER / AGX_ADMIN_PASS env vars.
 */

require_once __DIR__ . '/includes/config.php';

// ---------- Safety: refuse to serve admin if the default password is still
// in place AND the request is coming from outside localhost. This prevents
// an accidental deploy with the placeholder credentials.
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal  = in_array($remoteIp, ['127.0.0.1', '::1', 'localhost'], true);
if (AGX_ADMIN_PASS === 'change-me' && !$isLocal) {
    http_response_code(503);
    echo '<h1>503 — Admin not configured</h1>';
    echo '<p>Set the <code>AGX_ADMIN_PASS</code> environment variable before exposing this endpoint.</p>';
    exit;
}

// ---------- HTTP Basic auth ----------
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW']   ?? '';
if (!hash_equals(AGX_ADMIN_USER, $user) || !hash_equals(AGX_ADMIN_PASS, $pass)) {
    header('WWW-Authenticate: Basic realm="AGX Admin"');
    http_response_code(401);
    echo '<h1>401 Unauthorized</h1>';
    exit;
}

// ---------- Load + reverse-sort submissions (newest first) ----------
$submissions = [];
if (is_file(AGX_SUBMISSIONS_FILE)) {
    foreach (file(AGX_SUBMISSIONS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) $submissions[] = $row;
    }
    $submissions = array_reverse($submissions);
}

// ---------- Pretty labels (re-use options.json) ----------
$options = agx_options();
$labelMap = [];
foreach (['damage_types', 'features', 'crack_sizes', 'service_modes', 'payment_modes', 'time_slots'] as $bucket) {
    foreach (($options[$bucket] ?? []) as $opt) {
        $labelMap[$opt['value']] = $opt['label'];
    }
}
function label_for(string $v, array $map): string {
    return $map[$v] ?? $v;
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AGX — Submissions</title>
  <link rel="stylesheet" href="assets/css/form.css">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
  <div class="page-wrap admin-wrap">
    <header class="admin-header">
      <h1>AGX submissions</h1>
      <p class="admin-meta"><?= count($submissions) ?> total — newest first</p>
    </header>

    <?php if (empty($submissions)): ?>
      <div class="empty-state">
        <p>No submissions yet.</p>
      </div>
    <?php else: ?>
      <table class="submissions-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Received</th>
            <th>Vehicle</th>
            <th>Glasses</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($submissions as $sub):
            $v = $sub['vehicle'] ?? [];
            $d = $sub['damages'] ?? [];
            $s = $sub['service'] ?? [];
            $vehicleLine = trim(
              ($v['selected_year']  ?? '') . ' ' .
              ($v['selected_brand'] ?? '') . ' ' .
              ($v['selected_model'] ?? '')
            );
            $created = '';
            try { $created = (new DateTime($sub['created_at'] ?? 'now'))->format('Y-m-d H:i'); } catch (\Throwable $e) {}
          ?>
            <tr class="row-summary">
              <td class="cell-id"><?= h(substr($sub['id'] ?? '', 0, 18)) ?>…</td>
              <td><?= h($created) ?></td>
              <td>
                <?= h($vehicleLine ?: '—') ?>
                <?php if (!empty($v['vin_optional'])): ?>
                  <span class="vin">VIN: <?= h($v['vin_optional']) ?></span>
                <?php endif; ?>
              </td>
              <td><?= count($d) ?></td>
              <td><button class="btn btn-ghost btn-toggle" type="button" data-target="detail-<?= h($sub['id'] ?? '') ?>">View</button></td>
            </tr>
            <tr class="row-detail" id="detail-<?= h($sub['id'] ?? '') ?>" hidden>
              <td colspan="5">
                <?php $c = $sub['contact'] ?? []; if (!empty($c)): ?>
                  <div class="service-summary contact-summary">
                    <strong>Contact:</strong>
                    <?= h(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?> ·
                    <a href="mailto:<?= h($c['email'] ?? '') ?>"><?= h($c['email'] ?? '') ?></a> ·
                    <a href="tel:<?= h($c['phone'] ?? '') ?>"><?= h($c['phone'] ?? '') ?></a> ·
                    ZIP <?= h($c['zip'] ?? '') ?>
                    <?php if (!empty($c['notes'])): ?>
                      <div class="contact-notes"><em><?= nl2br(h($c['notes'])) ?></em></div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($s)): ?>
                  <div class="service-summary">
                    <strong>Service:</strong>
                    <?= h(label_for((string)($s['service_mode'] ?? '—'), $labelMap)) ?> ·
                    <?= h(label_for((string)($s['payment_mode'] ?? '—'), $labelMap)) ?>
                    <?php if (!empty($s['insurance_provider'])): ?>
                      (<?= h($s['insurance_provider']) ?>)
                    <?php endif; ?>
                    · <?= h($s['preferred_date'] ?? '—') ?>
                    · <?= h(label_for((string)($s['preferred_time'] ?? '—'), $labelMap)) ?>
                  </div>
                <?php endif; ?>
                <div class="detail-grid">
                  <?php foreach ($d as $glassId => $rec): ?>
                    <article class="glass-card">
                      <header>
                        <h3><?= h($rec['name'] ?? $glassId) ?></h3>
                        <code><?= h($glassId) ?></code>
                      </header>
                      <dl>
                        <dt>Damage</dt>
                        <dd><?= h(label_for((string)($rec['damage_type'] ?? '—'), $labelMap)) ?></dd>
                        <dt>Size</dt>
                        <dd><?= h(label_for((string)($rec['crack_size'] ?? '—'), $labelMap)) ?></dd>
                        <dt>Features</dt>
                        <dd>
                          <?php $f = $rec['features'] ?? []; ?>
                          <?= $f ? h(implode(', ', array_map(fn($x) => label_for($x, $labelMap), $f))) : '—' ?>
                        </dd>
                        <dt>Notes</dt>
                        <dd><?= !empty($rec['notes']) ? nl2br(h($rec['notes'])) : '—' ?></dd>
                      </dl>
                      <?php $photos = $rec['photos'] ?? []; if ($photos): ?>
                        <div class="glass-photos">
                          <?php foreach ($photos as $p): ?>
                            <a href="<?= h($p['url'] ?? '#') ?>" target="_blank" rel="noopener">
                              <img src="<?= h($p['url'] ?? '') ?>" alt="<?= h($p['name'] ?? 'photo') ?>" loading="lazy">
                            </a>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </article>
                  <?php endforeach; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <script>
    document.querySelectorAll(".btn-toggle").forEach((b) => {
      b.addEventListener("click", () => {
        const row = document.getElementById(b.dataset.target);
        if (!row) return;
        row.hidden = !row.hidden;
        b.textContent = row.hidden ? "View" : "Hide";
      });
    });
  </script>
</body>
</html>
