<?php
// PHP5-compatible helpers for managing VISITOR users

function addVisitor(PDO $pdo, $username, $full_name, $password, $is_active) {
    $username  = trim($username);
    $full_name = trim($full_name);
    $is_active = (int)$is_active;

    if ($username === '' || $full_name === '' || $password === '') {
        return false;
    }

    $sql = "INSERT INTO users (username, full_name, password_hash, role, is_active)
            VALUES (:username, :full_name, :password_hash, 'VISITOR', :is_active)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->bindValue(':full_name', $full_name, PDO::PARAM_STR);
    // Per your demo requirement, we keep md5 (insecure in real life)
    $stmt->bindValue(':password_hash', md5($password), PDO::PARAM_STR);
    $stmt->bindValue(':is_active', $is_active, PDO::PARAM_INT);
    return $stmt->execute();
}

function updateVisitor(PDO $pdo, $id, $username, $full_name, $password, $is_active) {
    $id        = (int)$id;
    $username  = trim($username);
    $full_name = trim($full_name);
    $is_active = (int)$is_active;

    if ($id <= 0 || $username === '' || $full_name === '') {
        return false;
    }

    if ($password !== '') {
        $sql = "UPDATE users
                SET username = :username,
                    full_name = :full_name,
                    password_hash = :password_hash,
                    is_active = :is_active
                WHERE id = :id AND role = 'VISITOR'";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':password_hash', md5($password), PDO::PARAM_STR);
    } else {
        $sql = "UPDATE users
                SET username = :username,
                    full_name = :full_name,
                    is_active = :is_active
                WHERE id = :id AND role = 'VISITOR'";
        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->bindValue(':full_name', $full_name, PDO::PARAM_STR);
    $stmt->bindValue(':is_active', $is_active, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);

    return $stmt->execute();
}

function deleteVisitor(PDO $pdo, $id) {
    $id = (int)$id;
    if ($id <= 0) return false;

    // Hard delete. If you prefer soft delete, switch to: UPDATE users SET is_active=0 ...
    $sql = "DELETE FROM users WHERE id = :id AND role = 'VISITOR'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}

function getAllVisitors(PDO $pdo) {
    $sql = "SELECT id, username, full_name, is_active
            FROM users
            WHERE role = 'VISITOR'
            ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getVisitorById(PDO $pdo, $id) {
    $sql = "SELECT id, username, full_name, is_active
            FROM users
            WHERE id = :id AND role = 'VISITOR'
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
