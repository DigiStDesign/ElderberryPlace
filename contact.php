<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';

renderHeader("Contact Us");
?>

<main>
    <h2>Contact Information</h2>
    <p>If you have any questions or would like to get in touch, please use the information below:</p>

    <ul>
        <li><strong>Phone:</strong> (03) 9123 4567</li>
        <li><strong>Email:</strong> admin@elderberryplace.org.au</li>
        <li><strong>Address:</strong> 123 Elderberry Lane, Tranquility VIC 3901</li>
        <li><strong>Hours:</strong> Monday to Friday, 9:00 AM – 5:00 PM</li>
    </ul>

    <p>We aim to respond to all inquiries within one business day.</p>
</main>

<?php renderFooter(); ?>
