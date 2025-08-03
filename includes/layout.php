<?php
function renderHeader($title = "Elderberry Place")
{
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>$title</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1>Aged Care Service Portal</h1>
    <h2>$title</h2>
HTML;
    renderNav();

}

function renderNav()
{
    $current = basename($_SERVER["PHP_SELF"]);
    echo '<nav>';
    echo '<a href="index.php"' . ($current === "index.php" ? ' class="active"' : '') . '>Home</a>';
    echo '<a href="setup_db.php"' . ($current === "setup_db.php" ? ' class="active"' : '') . '>DB Setup</a>';
    echo '<a href="staff.php"' . ($current === "staff.php" ? ' class="active"' : '') . '>Staff</a>';
    echo '<a href="services.php"' . ($current === "services.php" ? ' class="active"' : '') . '>Services</a>';
    echo '<a href="add_service.php"' . ($current === "add_service.php" ? ' class="active"' : '') . '>Add Service</a>';
    echo '<a href="manage_categories.php"' . ($current === "manage_categories.php" ? ' class="active"' : '') . '>Manage Service Categories</a>';

    // Add more links later as needed
    echo '</nav>';
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
