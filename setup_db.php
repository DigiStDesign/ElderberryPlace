<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$messages = [];
$sqlFiles = [
    'staff' => 'sql/init_staff_db.sql',
#    'assignments' => 'sql/init_staff_assignments_db.sql',
    'services' => 'sql/init_services_db.sql',
#    'categories' => 'sql/init_categories_db.sql',

    'drop_services' => 'sql/drop_services_db.sql'
];

if (isset($_GET['run']) && isset($sqlFiles[$_GET['run']])) {
    $fileKey = $_GET['run'];
    $filePath = __DIR__ . '/' . $sqlFiles[$fileKey];

    if (!file_exists($filePath)) {
        $messages[] = "❌ File not found: $filePath";
    } else {
        $sql = file_get_contents($filePath);
        try {
            $pdo->exec($sql);
            $messages[] = "✅ Successfully ran: " . basename($filePath);
        } catch (PDOException $e) {
            $messages[] = "❌ Error running " . basename($filePath) . ": " . $e->getMessage();
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drop_services'])) {
    $filePath = __DIR__ . '/sql/drop_services_db.sql';

    if (!file_exists($filePath)) {
        $messages[] = "❌ Drop script not found: $filePath";
    } else {
        $sql = file_get_contents($filePath);
        try {
            $pdo->exec($sql);
            $messages[] = "✅ Successfully dropped service-related tables.";
        } catch (PDOException $e) {
            $messages[] = "❌ Error dropping tables: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Setup Database</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 2em;
        }

        button {
            padding: 10px 20px;
            margin: 5px;
        }

        .msg {
            margin-top: 1em;
            padding: 10px;
            background: #f4f4f4;
            border-left: 5px solid #ccc;
        }
    </style>
</head>

<body>
    <h1>Database Setup</h1>
    <p>Click a button below to run a SQL setup script:</p>

    <form method="get">
        <button type="submit" name="run" value="staff">Run: Staff Table</button>
        <button type="submit" name="run" value="services">Run: Services Table</button>
    <!--    <button type="submit" name="run" value="assignments">Run: Staff Assignments Table</button>
        <button type="submit" name="run" value="categories">Run: Categories Table</button>
    -->
    </form>

    <h3>Drop DB</h3>
    <form method="post">
        <button type="submit" name="drop_services">Drop Service-Related Tables</button>
    </form>

    <?php renderMessages($messages); ?>
</body>

</html>