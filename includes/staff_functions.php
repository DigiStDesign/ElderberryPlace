<?php
// Helpers for managing STAFF users through the new schema:
// - users (role='STAFF')
// - staff_profiles (optional)
// - staff_jobs (lookup)

function addStaffFromForm(PDO $pdo, array $post) {
    $f = readStaffForm($post);
    $errors = validateStaffFormForCreate($pdo, $f);
    if (!empty($errors)) return array('ok' => false, 'errors' => $errors);

    try {
        $pdo->beginTransaction();

        $userId = createStaffUser($pdo, $f);

        if ($f['staff_job_id'] !== '') {
            upsertStaffProfile($pdo, $userId, (int)$f['staff_job_id'], $f['started_on']);
        }

        $pdo->commit();
        return array('ok' => true, 'errors' => array());
    } catch (Exception $e) {
        $pdo->rollBack();
        return array('ok' => false, 'errors' => array('Database error: ' . $e->getMessage()));
    }
}

function updateStaffFromForm(PDO $pdo, array $post) {
    $f = readStaffForm($post);
    if (!isset($post['id']) || !ctype_digit((string)$post['id'])) {
        return array('ok' => false, 'errors' => array('Invalid staff id.'));
    }
    $f['id'] = (int)$post['id'];

    $errors = validateStaffFormForUpdate($pdo, $f);
    if (!empty($errors)) return array('ok' => false, 'errors' => $errors);

    try {
        $pdo->beginTransaction();

        updateStaffUser($pdo, $f);

        // Profile: insert/update or delete if empty selection
        if ($f['staff_job_id'] !== '') {
            upsertStaffProfile($pdo, $f['id'], (int)$f['staff_job_id'], $f['started_on']);
        } else {
            deleteStaffProfile($pdo, $f['id']);
        }

        $pdo->commit();
        return array('ok' => true, 'errors' => array());
    } catch (Exception $e) {
        $pdo->rollBack();
        return array('ok' => false, 'errors' => array('Database error: ' . $e->getMessage()));
    }
}

function deleteStaffUser(PDO $pdo, $userId) {
    // ON DELETE CASCADE on staff_profiles will clean profile automatically
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'STAFF'");
    $stmt->execute(array((int)$userId));
}

function getAllStaffUsers(PDO $pdo) {
    $sql = "
        SELECT
            u.id, u.username, u.full_name, IFNULL(u.email,'') AS email,
            IFNULL(sj.name,'') AS job_name,
            IFNULL(DATE_FORMAT(sp.started_on, '%Y-%m-%d'), '') AS started_on
        FROM users u
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        LEFT JOIN staff_jobs sj ON sj.id = sp.staff_job_id
        WHERE u.role = 'STAFF'
        ORDER BY u.username ASC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStaffUserById(PDO $pdo, $userId) {
    $sql = "
        SELECT
            u.id, u.username, u.full_name, IFNULL(u.email,'') AS email,
            sp.staff_job_id,
            DATE_FORMAT(sp.started_on, '%Y-%m-%d') AS started_on
        FROM users u
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        WHERE u.id = ? AND u.role = 'STAFF'
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array((int)$userId));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
}

function getAllStaffJobs(PDO $pdo) {
    $stmt = $pdo->query("SELECT id, name FROM staff_jobs ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- Internal helpers ---------- */

function readStaffForm(array $post) {
    return array(
        'username'     => trim(isset($post['username']) ? $post['username'] : ''),
        'full_name'    => trim(isset($post['full_name']) ? $post['full_name'] : ''),
        'email'        => trim(isset($post['email']) ? $post['email'] : ''),
        'password'     => isset($post['password']) ? $post['password'] : '',
        'staff_job_id' => isset($post['staff_job_id']) ? $post['staff_job_id'] : '',
        'started_on'   => isset($post['started_on']) ? $post['started_on'] : ''
    );
}

function validateStaffFormForCreate(PDO $pdo, array $f) {
    $errors = array();

    if ($f['username'] === '') $errors[] = 'Username is required.';
    if ($f['full_name'] === '') $errors[] = 'Full name is required.';
    if ($f['password'] === '') $errors[] = 'Temporary password is required.';
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (usernameExists($pdo, $f['username'])) $errors[] = 'Username already exists.';
    if ($f['email'] !== '' && emailExists($pdo, $f['email'])) $errors[] = 'Email already in use.';
    if ($f['staff_job_id'] !== '' && !ctype_digit((string)$f['staff_job_id'])) $errors[] = 'Invalid staff job selection.';
    if ($f['started_on'] !== '' && !isYmdDate($f['started_on'])) $errors[] = 'Start date must be YYYY-MM-DD.';

    return $errors;
}

function validateStaffFormForUpdate(PDO $pdo, array $f) {
    $errors = array();

    if ($f['username'] === '') $errors[] = 'Username is required.';
    if ($f['full_name'] === '') $errors[] = 'Full name is required.';
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

    if (usernameTakenByOther($pdo, $f['id'], $f['username'])) $errors[] = 'Username already exists.';
    if ($f['email'] !== '' && emailTakenByOther($pdo, $f['id'], $f['email'])) $errors[] = 'Email already in use.';
    if ($f['staff_job_id'] !== '' && !ctype_digit((string)$f['staff_job_id'])) $errors[] = 'Invalid staff job selection.';
    if ($f['started_on'] !== '' && !isYmdDate($f['started_on'])) $errors[] = 'Start date must be YYYY-MM-DD.';

    return $errors;
}

function createStaffUser(PDO $pdo, array $f) {
    $sql = "INSERT INTO users (username, full_name, email, password_hash, role, is_active)
            VALUES (?, ?, ?, MD5(?), 'STAFF', 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($f['username'], $f['full_name'], $f['email'], $f['password']));
    return (int)$pdo->lastInsertId();
}

function updateStaffUser(PDO $pdo, array $f) {
    if ($f['password'] !== '') {
        $sql = "UPDATE users SET username=?, full_name=?, email=?, password_hash=MD5(?) WHERE id=? AND role='STAFF'";
        $params = array($f['username'], $f['full_name'], $f['email'], $f['password'], (int)$f['id']);
    } else {
        $sql = "UPDATE users SET username=?, full_name=?, email=? WHERE id=? AND role='STAFF'";
        $params = array($f['username'], $f['full_name'], $f['email'], (int)$f['id']);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function upsertStaffProfile(PDO $pdo, $userId, $jobId, $startedOn) {
    // Try update first
    $sql = "UPDATE staff_profiles SET staff_job_id=?, started_on=? WHERE user_id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array((int)$jobId, ($startedOn !== '' ? $startedOn : null), (int)$userId));

    if ($stmt->rowCount() === 0) {
        $sql = "INSERT INTO staff_profiles (user_id, staff_job_id, started_on) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array((int)$userId, (int)$jobId, ($startedOn !== '' ? $startedOn : null)));
    }
}

function deleteStaffProfile(PDO $pdo, $userId) {
    $stmt = $pdo->prepare("DELETE FROM staff_profiles WHERE user_id=?");
    $stmt->execute(array((int)$userId));
}

function usernameExists(PDO $pdo, $username) {
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username=? LIMIT 1");
    $stmt->execute(array($username));
    return (bool)$stmt->fetchColumn();
}

function emailExists(PDO $pdo, $email) {
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1");
    $stmt->execute(array($email));
    return (bool)$stmt->fetchColumn();
}

function usernameTakenByOther(PDO $pdo, $userId, $username) {
    $sql = "SELECT 1 FROM users WHERE username=? AND id<>? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($username, (int)$userId));
    return (bool)$stmt->fetchColumn();
}

function emailTakenByOther(PDO $pdo, $userId, $email) {
    $sql = "SELECT 1 FROM users WHERE email=? AND id<>? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($email, (int)$userId));
    return (bool)$stmt->fetchColumn();
}

function isYmdDate($s) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
    list($y, $m, $d) = explode('-', $s, 3);
    return checkdate((int)$m, (int)$d, (int)$y);
}
