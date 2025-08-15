<?php
// includes/layout.php
require_once __DIR__ . '/auth.php'; // needs current_user(), current_role(), start_secure_session()

function renderHeader($title = "Elderberry Place")
{
    start_secure_session();
    $user = current_user();
    $role = $user ? $user['role'] : 'GUEST';
    $bodyClass = 'role-' . strtolower($role);

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="{$bodyClass}">
<header>
    <h1>Aged Care Service Portal</h1>
    <h2>{$title}</h2>
HTML;

    renderNav($role, $user);
}

function renderFooter()
{
    echo <<<HTML
<footer>
    <p>&copy; 2025 Digi St. Design</p>
</footer>
</body>
</html>
HTML;
}

function renderNav($role = 'GUEST', $user = null)
{
    // Build absolute base like "http://localhost:8080"
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $origin = $scheme . '://' . $_SERVER['HTTP_HOST'];

    // If your app lives in a subfolder, keep it here (usually '' at root)
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if ($basePath === '/' || $basePath === '\\') $basePath = '';
    $BASE = $origin . $basePath;          
    $API  = $origin . '/api';             // absolute API base

    $current = basename($_SERVER["PHP_SELF"]);
    $phpName = $user ? htmlspecialchars($user['full_name']) : '';
    $phpRole = $user ? htmlspecialchars($role) : 'GUEST';

    echo '<nav><ul style="list-style:none;padding:0;display:flex;gap:12px;align-items:center;">';

    echo navLink($BASE . '/index.php',  'Home',    $current);
    echo navLink($BASE . '/contact.php','Contact', $current);

    echo '<li data-role-only="ADMIN" style="display:none;">' . linkHtml($BASE . '/setup_db.php', 'Setup DB', $current) . '</li>';

    // ADMIN
    echo '<li data-role-only="ADMIN" style="display:none;">' . linkHtml($BASE . '/staff.php',              'Staff',                     $current) . '</li>';
    echo '<li data-role-only="ADMIN" style="display:none;">' . linkHtml($BASE . '/residents.php',          'Residents',                 $current) . '</li>';
    echo '<li data-role-only="ADMIN" style="display:none;">' . linkHtml($BASE . '/visitors.php',           'Visitors',                  $current) . '</li>';
    echo '<li data-role-only="ADMIN" style="display:none;">' . linkHtml($BASE . '/relationships.php',      'Relationships',             $current) . '</li>';
    echo '<li data-role-only="ADMIN" style="display:none;">' . linkHtml($BASE . '/services.php',           'Services',                  $current) . '</li>';
    echo '<li data-role-only="ADMIN" style="display:none;">' . linkHtml($BASE . '/sessions.php',           'Service Sessions',          $current) . '</li>';
    echo '<li data-role-only="ADMIN" style="display:none;">' . linkHtml($BASE . '/manage_categories.php',  'Manage Service Categories', $current) . '</li>';

    // STAFF
    echo '<li data-role-only="STAFF" style="display:none;">' . linkHtml($BASE . '/roster.php', 'Roster', $current) . '</li>';

    // RESIDENT
    echo '<li data-role-only="RESIDENT" style="display:none;">' . linkHtml($BASE . '/visitations.php', 'My Visits',        $current) . '</li>';
    echo '<li data-role-only="RESIDENT" style="display:none;">' . linkHtml($BASE . '/sessions.php',    'Book a Service',   $current) . '</li>';
    echo '<li data-role-only="RESIDENT" style="display:none;">' . linkHtml($BASE . '/my_services.php', 'My Services',      $current) . '</li>';

    // VISITOR
    echo '<li data-role-only="VISITOR" style="display:none;">' . linkHtml($BASE . '/book_visit.php', 'Book a Visit', $current) . '</li>';

    // Right side
    echo '<li style="margin-left:auto;"></li>';
    echo '<li id="nav-user-label" class="nav-right" style="opacity:0.8; display:' . ($user ? 'list-item' : 'none') . ';">'
       . ($user ? ($phpName . ' (' . $phpRole . ')') : '')
       . '</li>';
    echo '<li id="nav-login"  style="display:' . ($user ? 'none' : 'list-item') . ';">' . linkHtml($BASE . '/login.php',  'Login',  $current) . '</li>';
    echo '<li id="nav-logout" style="display:' . ($user ? 'list-item' : 'none') . ';">' . linkHtml($BASE . '/logout.php', 'Logout', $current) . '</li>';

    echo '</ul></nav>';

    // Sync with API session (absolute /api)
    ?>
    <script>
    (function(){
      var API_BASE = "<?php echo $API; ?>";

      function q(sel){ return document.querySelector(sel); }
      function qa(sel){ return Array.prototype.slice.call(document.querySelectorAll(sel)); }

      function xhrGET(url, cb){
        var x = new XMLHttpRequest();
        x.open('GET', url, true);
        x.withCredentials = true;
        x.onreadystatechange = function(){
          if (x.readyState === 4){
            var res = null; try { res = JSON.parse(x.responseText); } catch(e){}
            cb(res);
          }
        };
        x.send();
      }

      xhrGET(API_BASE + "/index.php?r=/v1/me&__ts=" + Date.now(), function(res){
        var user = (res && res.ok && res.data && res.data.user) ? res.data.user : null;
        var role = user ? user.role : 'GUEST';

        qa('[data-role-only]').forEach(function(el){
          var need = el.getAttribute('data-role-only');
          el.style.display = (need === role) ? '' : 'none';
        });

        var lbl = q('#nav-user-label'), login = q('#nav-login'), logout = q('#nav-logout');
        if (user){
          if (lbl){ lbl.textContent = (user.full_name || user.username) + ' (' + role + ')'; lbl.style.display='list-item'; }
          if (login)  login.style.display = 'none';
          if (logout) logout.style.display = 'list-item';
        } else {
          if (lbl)    lbl.style.display = 'none';
          if (login)  login.style.display = 'list-item';
          if (logout) logout.style.display = 'none';
        }
      });
    })();
    </script>
    <?php
}

function navLink($href, $label, $current)
{
    return '<li>' . linkHtml($href, $label, $current) . '</li>';
}

function linkHtml($href, $label, $current)
{
    $isActive = (basename(parse_url($href, PHP_URL_PATH)) === $current) ? ' class="active"' : '';
    return '<a href="' . htmlspecialchars($href) . '"' . $isActive . '>' . htmlspecialchars($label) . '</a>';
}
