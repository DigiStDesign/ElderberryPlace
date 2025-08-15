<?php
// CRUD helpers for RESIDENT users and their resident_profiles

function addResidentFromForm(PDO $pdo, array $post) {
    $f = readResidentForm($post);
    $errors = validateResidentCreate($pdo, $f);
    if (!empty($errors)) return array('ok' => false, 'errors' => $errors);

    try {
        $pdo->beginTransaction();

        $userId = createResidentUser($pdo, $f);
        upsertResidentProfile($pdo, $userId, $f['room_number'], $f['dob']);

        $pdo->commit();
        return array('ok' => true, 'errors' => array());
    } catch (Exception $e) {
        $pdo->rollBack();
        return array('ok' => false, 'errors' => array('Database error: ' . $e->getMessage()));
    }
}

function updateResidentFromForm(PDO $pdo, array $post) {
    if (!isset($post['id']) || !ctype_digit((string)$post['id'])) {
        return array('ok' => false, 'errors' => array('Invalid resident id.'));
    }
    $f = readResidentForm($post);
    $f['id'] = (int)$post['id'];
    $errors = validateResidentUpdate($pdo, $f);
    if (!empty($errors)) return array('ok' => false, 'errors' => $errors);

    try {
        $pdo->beginTransaction();

        updateResidentUser($pdo, $f);
        upsertResidentProfile($pdo, $f['id'], $f['room_number'], $f['dob']);

        $pdo->commit();
        return array('ok' => true, 'errors' => array());
    } catch (Exception $e) {
        $pdo->rollBack();
        return array('ok' => false, 'errors' => array('Database error: ' . $e->getMessage()));
    }
}

function deleteResidentUser(PDO $pdo, $userId) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'RESIDENT'");
    $stmt->execute(array((int)$userId));
}

function getAllResidentUsers(PDO $pdo) {
    $sql = "
        SELECT
            u.id,
            u.username,
            u.full_name,
            IFNULL(rp.room_number, '') AS room_number,
            IFNULL(DATE_FORMAT(rp.dob, '%Y-%m-%d'), '') AS dob
        FROM users u
        LEFT JOIN resident_profiles rp ON rp.user_id = u.id
        WHERE u.role = 'RESIDENT'
        ORDER BY u.username ASC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getResidentUserById(PDO $pdo, $userId) {
    $sql = "
        SELECT
            u.id,
            u.username,
            u.full_name,
            IFNULL(u.email,'') AS email,
            IFNULL(rp.room_number,'') AS room_number,
            DATE_FORMAT(rp.dob, '%Y-%m-%d') AS dob
        FROM users u
        LEFT JOIN resident_profiles rp ON rp.user_id = u.id
        WHERE u.id = ? AND u.role = 'RESIDENT'
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array((int)$userId));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
}

/* ---------- internals ---------- */

function readResidentForm(array $post) {
    return array(
        'username'    => trim(isset($post['username']) ? $post['username'] : ''),
        'full_name'   => trim(isset($post['full_name']) ? $post['full_name'] : ''),
        'email'       => trim(isset($post['email']) ? $post['email'] : ''),
        'password'    => isset($post['password']) ? $post['password'] : '',
        'room_number' => trim(isset($post['room_number']) ? $post['room_number'] : ''),
        'dob'         => trim(isset($post['dob']) ? $post['dob'] : '')
    );
}

function validateResidentCreate(PDO $pdo, array $f) {
    $errors = array();
    if ($f['username'] === '') $errors[] = 'Username is required.';
    if ($f['full_name'] === '') $errors[] = 'Full name is required.';
    if ($f['password'] === '') $errors[] = 'Temporary password is required.';
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
    if (usernameExists($pdo, $f['username'])) $errors[] = 'Username already exists.';
    if ($f['email'] !== '' && emailExists($pdo, $f['email'])) $errors[] = 'Email already in use.';
    if ($f['dob'] !== '' && !isYmdDate($f['dob'])) $errors[] = 'DOB must be YYYY-MM-DD.';
    return $errors;
}

function validateResidentUpdate(PDO $pdo, array $f) {
    $errors = array();
    if ($f['username'] === '') $errors[] = 'Username is required.';
    if ($f['full_name'] === '') $errors[] = 'Full name is required.';
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
    if (usernameTakenByOther($pdo, $f['id'], $f['username'])) $errors[] = 'Username already exists.';
    if ($f['email'] !== '' && emailTakenByOther($pdo, $f['id'], $f['email'])) $errors[] = 'Email already in use.';
    if ($f['dob'] !== '' && !isYmdDate($f['dob'])) $errors[] = 'DOB must be YYYY-MM-DD.';
    return $errors;
}

function createResidentUser(PDO $pdo, array $f) {
    $sql = "INSERT INTO users (username, full_name, email, password_hash, role, is_active)
            VALUES (?, ?, ?, MD5(?), 'RESIDENT', 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($f['username'], $f['full_name'], $f['email'], $f['password']));
    return (int)$pdo->lastInsertId();
}

function updateResidentUser(PDO $pdo, array $f) {
    if ($f['password'] !== '') {
        $sql = "UPDATE users SET username=?, full_name=?, email=?, password_hash=MD5(?) WHERE id=? AND role='RESIDENT'";
        $params = array($f['username'], $f['full_name'], $f['email'], $f['password'], (int)$f['id']);
    } else {
        $sql = "UPDATE users SET username=?, full_name=?, email=? WHERE id=? AND role='RESIDENT'";
        $params = array($f['username'], $f['full_name'], $f['email'], (int)$f['id']);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function upsertResidentProfile(PDO $pdo, $userId, $roomNumber, $dob) {
    $sql = "UPDATE resident_profiles SET room_number=?, dob=? WHERE user_id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(($roomNumber !== '' ? $roomNumber : null),
                         ($dob !== '' ? $dob : null),
                         (int)$userId));
    if ($stmt->rowCount() === 0) {
        $sql = "INSERT INTO resident_profiles (user_id, room_number, dob) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array((int)$userId,
                             ($roomNumber !== '' ? $roomNumber : null),
                             ($dob !== '' ? $dob : null)));
    }
}

/* shared small utilities (duplicate-safe if you already have them elsewhere) */

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
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username=? AND id<>? LIMIT 1");
    $stmt->execute(array($username, (int)$userId));
    return (bool)$stmt->fetchColumn();
}

function emailTakenByOther(PDO $pdo, $userId, $email) {
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email=? AND id<>? LIMIT 1");
    $stmt->execute(array($email, (int)$userId));
    return (bool)$stmt->fetchColumn();
}

function isYmdDate($s) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
    list($y,$m,$d) = explode('-', $s, 3);
    return checkdate((int)$m, (int)$d, (int)$y);
}
