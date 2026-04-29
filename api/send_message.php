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
$body = isset($_POST['message']) ? trim($_POST['message']) : '';
$userId = (int)$_SESSION['user_id'];

if ($projectId <= 0 || empty($body)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
    exit;
}

$sql = "INSERT INTO dbo.Workspace_Messages (project_id, sender_user_id, message_body) VALUES (?, ?, ?)";
$stmt = sqlsrv_query($conn, $sql, [$projectId, $userId, $body]);

if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to send message.']);
    exit;
}
sqlsrv_free_stmt($stmt);
echo json_encode(['success' => true]);
sqlsrv_close($conn);
