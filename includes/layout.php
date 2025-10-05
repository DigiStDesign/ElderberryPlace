<?php
// includes/layout.php
require_once __DIR__ . '/auth.php';

function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/** Build absolute base like https://host/subdir (PHP 5.4 safe) */
function base_url(){
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if ($dir === '/' || $dir === '\\') $dir = '';
    return $scheme . '://' . $host . $dir;
}
function asset_url($path){ return rtrim(base_url(), '/') . '/' . ltrim($path, '/'); }

/* ------------------------- Header + Nav ------------------------- */
function renderHeader($title = 'Elderberry Place', $showHero = false){
    start_secure_session();
    $user = current_user();
    $role = $user ? (isset($user['role']) ? $user['role'] : 'GUEST') : 'GUEST';
    $bodyClass = 'role-' . strtolower($role);

    $BASE = base_url();
    $API  = $BASE . '/api';
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo e($title); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="<?php echo asset_url('assets/ebfavicon.png'); ?>">
  <link rel="apple-touch-icon" href="<?php echo asset_url('assets/ebicon.png'); ?>">
  <link rel="stylesheet" href="<?php echo asset_url('assets/style.css'); ?>?v=5">
  <link rel="stylesheet" href="<?php echo asset_url('assets/footer.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body id="top" class="<?php echo e($bodyClass); ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="stickynav">
  <nav class="navbar">
    <!-- Brand (left) -->
    <a class="brand" href="<?php echo $BASE; ?>/">
      <img src="<?php echo asset_url('assets/ebicon.png'); ?>" alt="Elderberry Place">
      <span>Elderberry Place</span>
    </a>

    <!-- Center links (pills) -->
    <div class="nav-links">
      <a class="btn btn--secondary" href="<?php echo $BASE; ?>/index.php">
        <i class="fa-solid fa-house"></i> Home
      </a>

      <!-- Services (always available) -->
      <!-- <div class="dropdown">
        <button type="button" class="btn btn--secondary dropbtn">
          <i class="fa-solid fa-user-nurse"></i> Services ▾
        </button>
        <div class="dropdown-content">
          <ul class="dropdown-list">
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/services.php">All Services</a></li>
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/residential.php">Residential Care</a></li>
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/homecare.php">Home Care</a></li>
          </ul>
        </div>
      </div> -->

      <a class="btn btn--secondary" href="<?php echo $BASE; ?>/contact.php">
        <i class="fa-regular fa-envelope"></i> Contact
      </a>

      <!-- ADMIN -->
      <div class="dropdown role-gate" data-role-only="ADMIN">
        <button type="button" class="btn btn--secondary dropbtn">
          <i class="fa-solid fa-screwdriver-wrench"></i> Manage ▾
        </button>
        <div class="dropdown-content">
          <ul class="dropdown-list">
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/setup_db.php">Setup DB</a></li>
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/staff.php">Staff</a></li>
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/residents.php">Residents</a></li>
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/visitors.php">Visitors</a></li>
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/relationships.php">Relationships</a></li>
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/billing.php">Billing</a></li>
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/services.php">Services (Admin)</a></li>
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/sessions.php">Service Sessions</a></li>
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/manage_categories.php">Manage Service Categories</a></li>
                  <li><a class="btn btn--secondary" href="<?= $BASE ?>/med_console.php">
        <i class="fa-solid fa-capsules"></i> Medication Console
      </a></li>
      <li><a class="btn btn--secondary" href="<?= $BASE ?>/incidents.php">
        <i class="fa-regular fa-file-lines"></i> Incidents
      </a></li>
          </ul>
        </div>
      </div>

      <!-- STAFF -->
      <div class="dropdown role-gate" data-role-only="STAFF">
        <button type="button" class="btn btn--secondary dropbtn">
          <i class="fa-regular fa-calendar"></i> Staff ▾
        </button>
        <div class="dropdown-content">
          <ul class="dropdown-list">
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/roster.php">Roster</a></li>
                  <li><a class="btn btn--secondary" href="<?= $BASE ?>/med_console.php">
        <i class="fa-solid fa-capsules"></i> Medication Console
      </a></li>
      <li><a class="btn btn--secondary" href="<?= $BASE ?>/incidents.php">
        <i class="fa-regular fa-file-lines"></i> Incidents
      </a></li>
    </ul>
  </div>
</div>
          </ul>
        </div>
      </div>

      <!-- RESIDENT -->
      <div class="dropdown role-gate" data-role-only="RESIDENT">
        <button type="button" class="btn btn--secondary dropbtn">
          <i class="fa-regular fa-user"></i> My Pages ▾
        </button>
        <div class="dropdown-content">
          <ul class="dropdown-list">
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/visitations.php">My Visits</a></li>
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/sessions.php">Book a Service</a></li>
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/my_services.php">My Services</a></li>
          </ul>
        </div>
      </div>

      <!-- VISITOR -->
      <div class="dropdown role-gate" data-role-only="VISITOR">
        <button type="button" class="btn btn--secondary dropbtn">
          <i class="fa-regular fa-id-badge"></i> Visitor ▾
        </button>
        <div class="dropdown-content">
          <ul class="dropdown-list">
            <li><a class="btn btn--secondary" href="<?php echo $BASE; ?>/book_visit.php">Book a Visit</a></li>
          </ul>
        </div>
      </div>

      <!-- Login / Logout -->
      <div class="dropdown login">
        <button type="button" class="btn btn--secondary dropbtn">
          <i class="fa-regular fa-user"></i> Login ▾
        </button>
        <div class="dropdown-content">
          <ul class="dropdown-list">
            <li id="nav-login"><a class="btn btn--primary" href="<?php echo $BASE; ?>/login.php">Sign in</a></li>
            <li id="nav-logout" style="display:none;"><a class="btn btn--secondary" href="<?php echo $BASE; ?>/logout.php">Log out</a></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Search (right) -->
    <form class="site-search" action="<?php echo $BASE; ?>/search.php" method="get" role="search">
      <input type="search" name="q" placeholder="Search..." aria-label="Search">
      <button type="submit"><i class="fa-solid fa-arrow-right"></i><span> Go</span></button>
    </form>
  </nav>
</header>

<?php if ($showHero): ?>
  <div class="hero-box">
    <img class="hero-img" src="<?php echo asset_url('assets/ebheader.svg'); ?>" alt="Residents and carers">
  </div>
<?php endif; ?>

<main id="main" class="container">
<?php
    renderNavRuntimeJs($API);
}

/* ------------------------- Footer ------------------------- */
function renderFooter(){
    $BASE = base_url();
    ?>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a class="brand brand--footer" href="<?php echo $BASE; ?>/" aria-label="Home">
        <img src="<?php echo asset_url('assets/ebicon.png'); ?>" alt="">
        <span class="brand-text">Elderberry Place</span>
      </a>
      <p class="tagline">Home. Community. Connection.</p>
    </div>

    <nav class="footer-links" aria-label="Footer">
      <h3>Pages</h3>
      <a href="<?php echo $BASE; ?>/">Home</a>
      <a href="<?php echo $BASE; ?>/residential.php">Residential Care</a>
      <a href="<?php echo $BASE; ?>/homecare.php">Home Care</a>
      <a href="<?php echo $BASE; ?>/contact.php">Contact</a>
    </nav>

    <div class="footer-contact">
      <h3>Contact</h3>
      <p>Email: <a href="mailto:hello@elderberryplace.example">hello@elderberryplace.example</a></p>
      <p>Phone: (00) 0000 0000</p>
      <a class="btn btn--primary" href="mailto:hello@elderberryplace.example">
        <i class="fa-solid fa-envelope"></i> Email us
      </a>
    </div>
  </div>

  <div class="footer-bottom">
    <div class="container fb-row">
      <small>© <?php echo date('Y'); ?> Elderberry Place. All rights reserved.</small>
      <a class="back-top" href="#top">Back to top ↑</a>
    </div>
  </div>
</footer>
</body>
</html>
<?php
}

/* ------------------------- Runtime Nav JS ------------------------- */
function renderNavRuntimeJs($API){
?>
<script>
// Dropdown toggle + outside click close
document.addEventListener('click', function (e) {
  var btn = e.target.closest('.dropbtn');
  var open = document.querySelectorAll('.dropdown.open');
  for (var i=0;i<open.length;i++){ if (!open[i].contains(e.target)) open[i].classList.remove('open'); }
  if (btn){ btn.closest('.dropdown').classList.toggle('open'); }
});

// Session/role sync
(function () {
  var API_BASE = <?php echo json_encode($API); ?>;
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

  xhrGET(API_BASE + "/index.php?r=/v1/me&__ts=" + Date.now(), function (res) {
    var user = (res && res.ok && res.data && res.data.user) ? res.data.user : null;
    var role = user ? user.role : 'GUEST';

    // role-gated menus — show only when allowed
    var nodes = document.querySelectorAll('[data-role-only]');
    for (var i=0;i<nodes.length;i++){
      var need = nodes[i].getAttribute('data-role-only');
      if (need === role) nodes[i].classList.add('is-on');
      else nodes[i].classList.remove('is-on');
    }

    // login/logout items
    var login  = document.getElementById('nav-login');
    var logout = document.getElementById('nav-logout');
    if (user){ if (login) login.style.display='none'; if (logout) logout.style.display=''; }
    else     { if (login) login.style.display='';     if (logout) logout.style.display='none'; }
  });
})();
</script>
<?php
}
