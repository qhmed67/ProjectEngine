<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Developer') {
    echo json_encode(['success' => false, 'error' => 'Permission denied. Only developers can manage tasks.']);
    exit;
}

$taskId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($taskId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid task ID.']);
    exit;
}

$sql = "DELETE FROM dbo.Workspace_Tasks WHERE task_id = ?";
$stmt = sqlsrv_query($conn, $sql, [$taskId]);

if ($stmt) {
    echo json_encode(['success' => true, 'message' => 'Task deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to delete task.']);
}

sqlsrv_close($conn);
