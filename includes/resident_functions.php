<?php
function getAllResidents($pdo) {
    $stmt = $pdo->query("SELECT * FROM residents ORDER BY room_number ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getResidentById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM residents WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addResident($pdo, $name, $room) {
    $stmt = $pdo->prepare("INSERT INTO residents (name, room_number) VALUES (?, ?)");
    $stmt->execute([$name, $room]);
}

function updateResident($pdo, $id, $name, $room) {
    $stmt = $pdo->prepare("UPDATE residents SET name = ?, room_number = ? WHERE id = ?");
    $stmt->execute([$name, $room, $id]);
}

function deleteResident($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM residents WHERE id = ?");
    $stmt->execute([$id]);
}
