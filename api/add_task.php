<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$desc = isset($_POST['description']) ? trim($_POST['description']) : '';

if ($projectId <= 0 || empty($title)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
    exit;
}

$sql = "INSERT INTO dbo.Workspace_Tasks (project_id, title, description, status) VALUES (?, ?, ?, 'To Do')";
$stmt = sqlsrv_query($conn, $sql, [$projectId, $title, $desc]);

if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to create task.']);
    exit;
}
sqlsrv_free_stmt($stmt);
echo json_encode(['success' => true]);
sqlsrv_close($conn);
