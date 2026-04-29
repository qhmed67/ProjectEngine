<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($projectId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid project ID']);
    exit;
}

$sql = "SELECT m.message_id, m.sender_user_id, m.message_body, 
               CONVERT(VARCHAR, m.sent_at, 120) as sent_at,
               COALESCE(d.full_name, c.company_name, 'User') as sender_name 
        FROM dbo.Workspace_Messages m
        INNER JOIN dbo.Users u ON m.sender_user_id = u.user_id
        LEFT JOIN dbo.Developers d ON u.user_id = d.dev_id
        LEFT JOIN dbo.Clients c ON u.user_id = c.client_id
        WHERE m.project_id = ?
        ORDER BY m.sent_at ASC";
        
$stmt = sqlsrv_query($conn, $sql, [$projectId]);
$messages = [];

if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['is_mine'] = ($row['sender_user_id'] == $_SESSION['user_id']);
        $messages[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

echo json_encode(['success' => true, 'messages' => $messages]);
sqlsrv_close($conn);
