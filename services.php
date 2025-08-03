<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/service_functions.php';

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $services = getAllServicesWithStaff($pdo, $search);
} catch (PDOException $e) {
    die("Error loading services: " . $e->getMessage());
}

renderHeader("Service Management");
?>

<main>
    <h2>Available Services</h2>

    <form method="get" style="margin-bottom: 1em;">
        <input type="text" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
            placeholder="Search by name, category, or staff..." size="40">
        <button type="submit">Search</button>
    </form>

    <?php if (empty($services)): ?>
        <p>No services found in the database.</p>
    <?php else: ?>
        <div class="service-grid">
            <?php foreach ($services as $service): ?>
                <div class="card <?= strtolower($service['status']) ?>">
                    <h3><?= htmlspecialchars($service['name']) ?></h3>
                    <p><strong>Category:</strong>
                        <?= htmlspecialchars(isset($service['category_name']) ? $service['category_name'] : 'Uncategorised') ?>
                    </p>
                    <p><strong>Description:</strong> <?= htmlspecialchars($service['description']) ?></p>
                    <p><strong>Cost:</strong> $<?= number_format($service['cost'], 2) ?> per session</p>
                    <p><strong>Frequency:</strong> <?= htmlspecialchars($service['frequency']) ?></p>
                    <p><strong>Duration:</strong>
                        <?= formatDuration($service['duration_minutes_min'], $service['duration_minutes_max']) ?></p>
                    <p><strong>Status:</strong> <?= htmlspecialchars($service['status']) ?></p>

                    <p><strong>Assigned Staff:</strong><br>
                        <?= !empty($service['assigned_staff'])
                            ? htmlspecialchars(implode(', ', $service['assigned_staff']))
                            : 'None' ?>
                    </p>

                    <p><a href="edit_service.php?id=<?= $service['id'] ?>">Edit</a></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php renderFooter(); ?>