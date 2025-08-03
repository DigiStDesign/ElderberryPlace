<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';


$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = isset($_POST['name']) ? trim($_POST['name']) : '';
    $role  = isset($_POST['role']) ? trim($_POST['role']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if ($name === '') $errors[] = "Name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO staff (name, role, email) VALUES (?, ?, ?)");
        $stmt->execute([$name, $role, $email]);
        header("Location: staff.php");
        exit;
    }
}

renderHeader("Add Staff Member");
?>

<main>
    <h2>Add New Staff Member</h2>

    <?php if (!empty($errors)): ?>
        <div style="color:red;">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <label>Name:<br>
            <input type="text" name="name" required>
        </label><br><br>

        <label>Role:<br>
            <input type="text" name="role">
        </label><br><br>

        <label>Email:<br>
            <input type="email" name="email" required>
        </label><br><br>

        <button type="submit">Add Staff</button>
    </form>
</main>

<?php renderFooter(); ?>