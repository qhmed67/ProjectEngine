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

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

if ($role === 'Client') {
    // For clients, we get the project and its current application status (if any)
    $sql = "SELECT p.project_id, p.title, p.required_level, p.status, 
                   d.full_name as partner_name, pa.status as app_status
            FROM dbo.Projects p
            LEFT JOIN dbo.Project_Applications pa ON p.project_id = pa.project_id 
                   AND pa.status IN ('Applied', 'Pending', 'Accepted', 'Rejected')
            LEFT JOIN dbo.Developers d ON pa.dev_id = d.dev_id
            WHERE p.project_id = ? AND p.client_id = ?
            ORDER BY pa.applied_at DESC"; // Get the most recent application
} else {
    $sql = "SELECT p.project_id, p.title, p.required_level, p.status, 
                   c.company_name as partner_name, pa.status as app_status
            FROM dbo.Projects p
            INNER JOIN dbo.Project_Applications pa ON p.project_id = pa.project_id
            INNER JOIN dbo.Clients c ON p.client_id = c.client_id
            WHERE p.project_id = ? AND pa.dev_id = ? AND pa.status = 'Accepted'";
}

$stmt = sqlsrv_query($conn, $sql, [$projectId, $userId]);
if ($stmt === false || !($project = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    echo json_encode(['success' => false, 'error' => 'Project not found or access denied']);
    exit;
}
sqlsrv_free_stmt($stmt);

// If client but no accepted developer, return early with status
if ($role === 'Client' && (!isset($project['app_status']) || $project['app_status'] !== 'Accepted')) {
    echo json_encode([
        'success' => true,
        'project' => $project,
        'app_status' => $project['app_status'] ?? 'No_Hire',
        'tasks' => []
    ]);
    exit;
}

// Fetch tasks
$sqlTasks = "SELECT task_id, title, description, status FROM dbo.Workspace_Tasks WHERE project_id = ? ORDER BY created_at ASC";
$stmtTasks = sqlsrv_query($conn, $sqlTasks, [$projectId]);
$tasks = [];
if ($stmtTasks !== false) {
    while ($row = sqlsrv_fetch_array($stmtTasks, SQLSRV_FETCH_ASSOC)) {
        $tasks[] = $row;
    }
    sqlsrv_free_stmt($stmtTasks);
}

// Note: Task seeding removed to ensure clean workspace for new projects.

echo json_encode([
    'success' => true,
    'project' => $project,
    'tasks' => $tasks
]);
sqlsrv_close($conn);
