<?php
session_start();

/* ===== LOAD DB (YOUR REAL FILE) ===== */
require_once __DIR__ . '/config/db.php';

/* ===== SIMPLE ADMIN CHECK (NO auth.php) ===== */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: index.php');
    exit;
}

/* ===== VALIDATE INPUT ===== */
$action = $_POST['action'] ?? '';
$student_ids = $_POST['student_ids'] ?? [];
$class_id = (int)($_POST['class_id'] ?? 0);

if (empty($student_ids)) {
    die('No students selected');
}

/* ===== PREPARE PLACEHOLDERS ===== */
$placeholders = implode(',', array_fill(0, count($student_ids), '?'));
$types = str_repeat('i', count($student_ids));

/* ===== GRADUATE ===== */
if ($action === 'graduate') {

    $sql = "
        UPDATE students
        SET status = 'Graduated',
            class_id = NULL
        WHERE id IN ($placeholders)
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$student_ids);
    $stmt->execute();
}

/* ===== PROMOTE ===== */
if ($action === 'promote') {

    // find next class (simple rule: next higher id)
    $next = $conn->query("
        SELECT id FROM classes
        WHERE id > $class_id
        ORDER BY id ASC
        LIMIT 1
    ")->fetch_assoc();

    if ($next) {
        $sql = "
            UPDATE students
            SET class_id = ?
            WHERE id IN ($placeholders)
              AND status = 'Active'
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i' . $types, $next['id'], ...$student_ids);
        $stmt->execute();
    }
}

/* ===== REDIRECT BACK ===== */
header("Location: admin_student_lifecycle.php?class_id=$class_id&done=1");
exit;
