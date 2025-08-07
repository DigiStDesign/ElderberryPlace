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

                    <p><strong>Scheduled Sessions:</strong>
                        <?php if ($service['scheduled_count'] > 0): ?>
                            <a href="view_service_sessions.php?service_id=<?= $service['id'] ?>">
                                <?= $service['scheduled_count'] ?> scheduled
                            </a>
                        <?php else: ?>
                            None yet
                        <?php endif; ?>
                    </p>

                <p><strong>Resident Bookings:</strong>
                    <?php if ($service['resident_count'] > 0): ?>
                        <a href="view_service_residents.php?service_id=<?= $service['id'] ?>">
                            <?= $service['resident_count'] ?> booked
                        </a>
                    <?php else: ?>
                        None yet
                    <?php endif; ?>
                </p>



                    <p><a href="edit_service.php?id=<?= $service['id'] ?>">Edit</a></p>
                    <p><a href="schedule_staff.php?service_id=<?= $service['id'] ?>">Schedule This Service (Staff)</a></p>
                    <p><a href="schedule_resident.php?service_id=<?= $service['id'] ?>">Schedule This Service (Resident)</a></p>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php renderFooter(); ?>