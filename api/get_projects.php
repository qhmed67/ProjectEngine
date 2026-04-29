<?php
/**
 * Fetch projects for dashboard.
 * GET /api/get_projects.php
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Authentication required."]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

$projects = [];

if ($role === 'Client') {
    // Clients see projects they posted
    $sql = "SELECT project_id, title, required_level, status 
            FROM dbo.Projects 
            WHERE client_id = ? 
            ORDER BY project_id DESC";
    $stmt = sqlsrv_query($conn, $sql, [$userId]);
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $projects[] = [
                "id"             => $row['project_id'],
                "title"          => $row['title'],
                "track"          => $row['required_level'] . " Developer Track",
                "status"         => $row['status'], // 'Pending', 'Active', 'Completed'
                "is_client"      => true
            ];
        }
        sqlsrv_free_stmt($stmt);
    }
} else {
    // Developers see projects they applied to
    $sql = "SELECT p.project_id, p.title, p.required_level, p.status, pa.status AS app_status
            FROM dbo.Projects p
            INNER JOIN dbo.Project_Applications pa ON p.project_id = pa.project_id
            WHERE pa.dev_id = ?
            ORDER BY pa.applied_at DESC";
    $stmt = sqlsrv_query($conn, $sql, [$userId]);
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $projects[] = [
                "id"             => $row['project_id'],
                "title"          => $row['title'],
                "track"          => $row['required_level'] . " Developer Track",
                "status"         => $row['status'],
                "app_status"     => $row['app_status'], // 'Pending', 'Accepted', 'Rejected'
                "is_client"      => false
            ];
        }
        sqlsrv_free_stmt($stmt);
    }
}

echo json_encode(["success" => true, "projects" => $projects]);
sqlsrv_close($conn);
