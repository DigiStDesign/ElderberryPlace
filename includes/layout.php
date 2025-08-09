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

function renderNav($role = 'GUEST', $user = null)
{
    $current = basename($_SERVER["PHP_SELF"]);

    echo '<nav><ul style="list-style:none;padding:0;display:flex;gap:12px;">';

    echo navLink('index.php', 'Home', $current);
    echo navLink('contact.php', 'Contact', $current);
    echo navLink('setup_db.php', 'Setup DB', $current);

    // Role-specific items
    if ($role === 'ADMIN') {
        echo navLink('staff.php', 'Staff', $current);
        echo navLink('residents.php', 'Residents', $current);
        echo navLink('visitors.php', 'Visitors', $current);
        echo navLink('services.php', 'Services', $current);
        echo navLink('add_service.php', 'Add Service', $current);
        echo navLink('manage_categories.php', 'Manage Service Categories', $current);
    }
    if ($role === 'STAFF') {
        echo navLink('roster.php', 'Roster', $current);

    } elseif ($role === 'RESIDENT') {
        echo navLink('visitations.php', 'My Visits', $current);
        echo navLink('services.php', 'Request a Service', $current);
        echo navLink('my_services.php', 'My Services', $current);

    } elseif ($role === 'VISITOR') {
        echo navLink('book_visit.php', 'Book a Visit', $current);
    } else {
        // Guest
    }

    // Right side: login/logout + who’s signed in
    echo '<li style="margin-left:auto;"></li>';
    if ($user) {
        $label = htmlspecialchars($user['full_name']) . ' (' . htmlspecialchars($role) . ')';
        echo '<li class="nav-right" style="opacity:0.8;">' . $label . '</li>';
        echo navLink('logout.php', 'Logout', $current);
    } else {
        echo navLink('login.php', 'Login', $current);
    }

    echo '</ul></nav>';
}

function navLink($href, $label, $current)
{
    $isActive = ($current === $href) ? ' class="active"' : '';
    return '<li><a href="' . htmlspecialchars($href) . '"' . $isActive . '>' . htmlspecialchars($label) . '</a></li>';
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
