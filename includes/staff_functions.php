<?php
function getAllStaff($pdo) {
    $stmt = $pdo->query("SELECT * FROM staff ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStaffById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addStaff($pdo, $name, $role, $email) {
    $stmt = $pdo->prepare("INSERT INTO staff (name, role, email) VALUES (?, ?, ?)");
    $stmt->execute([$name, $role, $email]);
}

function updateStaff($pdo, $id, $name, $role, $email) {
    $stmt = $pdo->prepare("UPDATE staff SET name = ?, role = ?, email = ? WHERE id = ?");
    $stmt->execute([$name, $role, $email, $id]);
}

function deleteStaff($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
    $stmt->execute([$id]);
}
