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

$taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

if ($taskId <= 0 || !in_array($status, ['To Do', 'In Progress', 'Done'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
    exit;
}

$sql = "UPDATE dbo.Workspace_Tasks SET status = ?, updated_at = GETDATE() WHERE task_id = ?";
$stmt = sqlsrv_query($conn, $sql, [$status, $taskId]);

if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to update task status.']);
    exit;
}
sqlsrv_free_stmt($stmt);
echo json_encode(['success' => true]);
sqlsrv_close($conn);
