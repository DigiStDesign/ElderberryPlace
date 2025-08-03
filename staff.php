<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';

$stmt = $pdo->query("SELECT * FROM staff ORDER BY name");
$staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderHeader("Staff Directory");
?>

<main>
    <h2>Staff Members</h2>

    <?php if (empty($staff_list)): ?>
        <p>No staff found in the database.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($staff_list as $staff): ?>
                <li>
                    <strong><?= htmlspecialchars($staff['name']) ?></strong>
                    (<?= htmlspecialchars($staff['role']) ?>)
                    — <?= htmlspecialchars($staff['email']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p><a href="add_staff.php">➕ Add Staff Member</a></p>
</main>

<?php renderFooter(); ?>
