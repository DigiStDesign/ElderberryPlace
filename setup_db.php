<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';


$messages = [];
$sqlFiles = [
# This can either work with an array of individual scripts, or one monolithic init script.
#    'staff' => 'sql/init_staff_db.sql',
#    'assignments' => 'sql/init_staff_assignments_db.sql',
    'init' => 'sql/init_db.sql',
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



renderHeader("Database Setup");
?>

<main>
    <section style="padding: 2em;">
        <h2>Database Setup</h2>
        <p>Click a button below to run a SQL setup script:</p>

        <form method="get" style="margin-bottom: 1em;">
            <button type="submit" name="run" value="init">Run: Init DB</button>
        </form>

        <!-- Optional drop form -->
        <!--
        <h3>Drop DB</h3>
        <form method="post">
            <button type="submit" name="drop_services">Drop Service-Related Tables</button>
        </form>
        -->

        <?php renderMessages($messages); ?>
    </section>
</main>

<?php renderFooter(); ?>