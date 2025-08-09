<?php
// includes/session.php
// Basic session handling (PHP 5.4)

function start_secure_session() {
    if (session_id() === '') {
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }

        session_name('AGEDCARESESSID');
        session_start();

        if (empty($_SESSION['__init'])) {
            session_regenerate_id(true);
            $_SESSION['__init'] = time();
        }
    }
}
