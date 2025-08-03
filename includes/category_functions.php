<?php

function getAllCategories(PDO $pdo)
{
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addCategory(PDO $pdo, $name)
{
    $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->execute([trim($name)]);
    return "Category added.";
}

function updateCategory(PDO $pdo, $id, $name)
{
    $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
    $stmt->execute([trim($name), (int)$id]);
    return "Category updated.";
}

function deleteCategory(PDO $pdo, $id)
{
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([(int)$id]);
        return "Category deleted.";
    } catch (PDOException $e) {
        return "Cannot delete category — it may be in use.";
    }
}
