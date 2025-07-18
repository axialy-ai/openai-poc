<?php
// /index.php
require_once 'includes/auth.php';
requireAuth();

/* ---------------- Config ---------------- */
require_once 'includes/Config.php';
use AxiaBA\Config\Config;
$config = Config::getInstance();

/* ------ Get logged-in username (legacy mysqli block) ------ */
$loggedInUsername = '';
if (isset($_SESSION['user_id'])) {
    require_once 'includes/db_connection.php';
    $stmt = $conn->prepare(
        "SELECT username FROM ui_users WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $loggedInUsername = htmlspecialchars($row['username'],
                                             ENT_QUOTES, 'UTF-8');
    }
    $stmt->close();
}

/* ------ JSON configuration paths ------ */
$menuJsonPath           = __DIR__.'/config/control-panel-menu.json';
$accountActionsJsonPath = __DIR__.'/config/account-actions.json';
$helpSupportJsonPath    = __DIR__.'/config/support-actions.json';

/* defaults */
$menuOptions         = [];
$viewsDropdownConfig = [
    'backgroundImage'  => '/assets/img/AxiaBA-Umbrella.png',
    'backgroundOpacity'=> 0.8
];
$accountActions      = [];
$helpSupportActions  = [];

/* helper */
function loadJsonConfig(string $path): ?array {
    if (!is_file($path)) {
        error_log("JSON file not found: $path");
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON parse error at $path: ".json_last_error_msg());
        return null;
    }
    return $data;
}

/* load configs */
if ($tmp = loadJsonConfig($menuJsonPath)) {
    $menuOptions         = $tmp['menuOptions']  ?? [];
    $viewsDropdownConfig = $tmp['viewsDropdown']?? $viewsDropdownConfig;
}
if ($tmp = loadJsonConfig($accountActionsJsonPath)) {
    $accountActions = $tmp['accountActions'] ?? [];
}
if ($tmp = loadJsonConfig($helpSupportJsonPath)) {
    $helpSupportActions = $tmp['supportActions'] ?? [];
}

/* version string */
$appVersion = $config->get('app_version') ?: '1.x.x';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0">
  <title>AxiaBA - Business Analysis</title>
  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
        rel="stylesheet">
  <!-- UI stylesheets -->
  <link rel="stylesheet" href="assets/css/desktop.css"              id="layout-css">
  <link rel="stylesheet" href="assets/css/generate-tab.css"        id="generate-tab-css">
  <link rel="stylesheet" href="assets/css/home-tab.css"            id="home-tab-css">
  <link rel="stylesheet" href="assets/css/refine-tab.css"          id="refine-tab-css">
  <link rel="stylesheet" href="assets/css/refine-tab-overlays.css">
  <link rel="stylesheet" href="assets/css/dashboard-tab.css">
  <link rel="stylesheet" href="assets/css/overlay.css"             id="overlay-css">
  <link rel="stylesheet" href="assets/css/support-tickets.css">
  <link rel="stylesheet" href="assets/css/content-review.css"      id="content-review-css">
  <link rel="stylesheet" href="assets/css/content-revision.css"    id="content-revision-css">
  <link rel="stylesheet" href="assets/css/settings-tab.css"        id="settings-tab-css">
  <link rel="stylesheet" href="assets/css/publish-tab.css"         id="publish-tab-css">
  <link rel="stylesheet" href="assets/css/account-actions.css"     id="account-actions-css">
</head>
<body>
<div class="page-container">
  <!-- ############ HEADER ############ -->
  <header class="page-header">
    <div class="product-logo" style="position:relative;display:inline-block;">
      <a href="https://axialy.ai" target="_blank" rel="noopener">
        <img src="assets/img/product_logo.png" alt="AxiaBA Logo"
             style="height:44px;vertical-align:middle;">
      </a>
      <?php if ($appVersion): ?>
        <span style="
          position:absolute;bottom:0;left:calc(100% + 6px);
          font-size:.618rem;color:#caa23a;">v<?=htmlspecialchars($appVersion)?></span>
      <?php endif; ?>
    </div>

    <!-- Views dropdown (middle) -->
    <div class="views-dropdown-container">
      <div class="views-dropdown" id="views-dropdown">
        <select id="views-menu" aria-label="Select View"
                data-background-image="<?=htmlspecialchars($viewsDropdownConfig['backgroundImage'])?>"
                data-background-opacity="<?=htmlspecialchars($viewsDropdownConfig['backgroundOpacity'])?>">
          <?php if ($menuOptions): foreach ($menuOptions as $i=>$m): ?>
            <?php
              $sel   = $i===0 ? 'selected' : '';
              $name  = htmlspecialchars($m['name']);
              $target= htmlspecialchars($m['target']);
              $bgImg = htmlspecialchars($m['backgroundImage'] ?? '/assets/img/AxiaBA-Umbrella.png');
              $bgOp  = htmlspecialchars($m['backgroundOpacity'] ?? '0.8');
            ?>
            <option value="<?=$target?>" <?=$sel?>
                    data-background-image="<?=$bgImg?>"
                    data-background-opacity="<?=$bgOp?>"><?=$name?></option>
          <?php endforeach; else: ?>
            <option value="">No views available</option>
          <?php endif;?>
        </select>
      </div>
    </div>

    <!-- Right-side icons -->
    <div class="header-right-icons">
      <!-- Settings / account -->
      <div class="settings">
        <span class="icon settings-icon" data-tooltip="Account actions"
              role="button" tabindex="0" aria-label="Account actions">⚙️</span>
        <div class="settings-dropdown">
          <div class="user-info">
            <span class="dropdown-username"><?=$loggedInUsername?></span>
          </div>
          <ul>
            <?php if ($accountActions):
              foreach ($accountActions as $a):
                if (empty($a['active'])) continue;
                $label = htmlspecialchars($a['label']);
                $type  = $a['actionType'] ?? '';
                $act   = htmlspecialchars($a['action']);
                if ($type==='js') {
                    $href='#'; $onclick="onclick=\"$act\"";
                } elseif ($type==='link') {
                    $href=$act; $onclick='';
                } else { continue; }
            ?>
              <li><a href="<?=$href?>" <?=$onclick?>><?=$label?></a></li>
            <?php endforeach; else: ?>
              <li>No account actions available.</li>
            <?php endif;?>
          </ul>
        </div>
      </div>

      <!-- Help -->
      <div class="help">
        <span class="icon help-icon" data-tooltip="Help &amp; Support"
              role="button" tabindex="0" aria-label="Help & Support">❓</span>
        <div class="help-dropdown">
          <ul>
            <?php if ($helpSupportActions):
              foreach ($helpSupportActions as $h):
                if (empty($h['active'])) continue;
                $label = htmlspecialchars($h['label']);
                $type  = $h['actionType'] ?? '';
                $act   = htmlspecialchars($h['action']);
                if ($type==='js') {
                    $href='#'; $onclick="onclick=\"$act\"";
                } elseif ($type==='link') {
                    $href=$act; $onclick='target="_blank"';
                } else { continue; }
            ?>
              <li><a href="<?=$href?>" <?=$onclick?>><?=$label?></a></li>
            <?php endforeach; else: ?>
              <li>No help/support actions available.</li>
            <?php endif;?>
          </ul>
        </div>
      </div>
    </div>
  </header>

  <!-- ribbons & panels -->
  <div class="upper-ribbon"></div>
  <div class="panel-container">
    <nav class="control-panel expanded" role="navigation" aria-label="Control Panel">
      <div class="panel-title">Views</div>
      <div class="pin-toggle pinned" id="pin-icon-control" role="button"
           tabindex="0" aria-label="Toggle Control Panel"></div>
      <div class="collapsed-title">Control Panel</div>
      <ul class="tab-options" role="tablist">
        <?php if ($menuOptions): foreach ($menuOptions as $i=>$m): ?>
          <?php
            $active = $i===0 ? ' active' : '';
            $name   = htmlspecialchars($m['name']);
            $tooltip= htmlspecialchars($m['tooltip']);
            $target = htmlspecialchars($m['target']);
            $bgImg  = htmlspecialchars($m['backgroundImage'] ?? '/assets/img/AxiaBA-Umbrella.png');
            $bgOp   = htmlspecialchars($m['backgroundOpacity'] ?? '0.8');
          ?>
          <li class="list-group-item<?=$active?>" title="<?=$tooltip?>"
              data-target="<?=$target?>"
              data-background-image="<?=$bgImg?>"
              data-background-opacity="<?=$bgOp?>"
              role="tab" tabindex="0"><?=$name?></li>
        <?php endforeach; else: ?>
          <li class="list-group-item" role="tab" tabindex="0">
            No menu options available.
          </li>
        <?php endif;?>
      </ul>
    </nav>
    <main class="overview-panel" id="overview-panel" role="main"></main>
  </div>
  <div class="lower-ribbon"></div>
  <footer class="page-footer">&copy; 2024 AxiaBA</footer>
</div>

<!-- global JS config -->
<script>
window.AxiaBAConfig = {
  api_base_url: "<?=rtrim($config->get('api_base_url') ?: '', '/')?>",
  app_base_url: "<?=rtrim($config->get('app_base_url') ?: '', '/')?>",
  api_key     : "<?=htmlspecialchars($config->get('internal_api_key') ?: '')?>"
};
</script>

<!-- vendor JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- UI scripts -->
<script src="js/input-text.js"></script>
<script src="js/focus-areas.js"></script>
<script src="js/export-csv.js"></script>
<script src="js/overlay.js"></script>
<script src="js/dynamic-ribbons.js"></script>
<script src="js/process-feedback.js"></script>
<script src="js/generate/save-data-enhancement.js"></script>
<script src="js/focus-area-version.js"></script>
<script src="js/home/home-tab.js"></script>
<script src="js/account-actions.js"></script>
<script src="js/support-tickets.js"></script>
<script src="js/content-review.js"></script>
<script src="js/content-revision.js"></script>
<script src="js/apply-revisions-handler.js"></script>
<script src="js/collate-feedback.js"></script>
<script src="js/new-focus-area-overlay.js"></script>
<script src="js/update-overview-panel.js"></script>
<script src="js/modules/subscription-validation-module.js"></script>
<script src="js/modules/tab-navigation-module.js"></script>
<script src="js/modules/ui-utils-module.js"></script>
<script src="js/layout.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  fetch('/get_user_email.php')
    .then(r => r.json())
    .then(d => { window.currentUserEmail = d.status==='success' ? d.email : 'noreply@example.com'; })
    .catch(() => { window.currentUserEmail = 'noreply@example.com'; });
});
</script>
</body>
</html>
