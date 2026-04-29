<?php
/**
 * Submit an application to a project (Developer only).
 * POST /api/apply_project.php
 *
 * Checks the project is still open, prevents duplicate
 * applications, and inserts into Project_Applications.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed."]);
    exit;
}

// ── Auth Guard: Must be a Developer ────────────────────────
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Developer') {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Only developers can apply to projects."]);
    exit;
}

$devId     = (int)$_SESSION['dev_id'];
$projectId = (int)($_POST['project_id'] ?? 0);

if ($projectId <= 0) {
    http_response_code(422);
    echo json_encode(["success" => false, "error" => "Valid project_id is required."]);
    exit;
}

// ── Verify project exists and is open ──────────────────────
$sqlCheck  = "SELECT project_id, status FROM dbo.Projects WHERE project_id = ?";
$stmtCheck = sqlsrv_query($conn, $sqlCheck, [$projectId]);
$project   = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtCheck);

if (!$project) {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Project not found."]);
    exit;
}

if ($project['status'] !== 'Pending') {
    http_response_code(409);
    echo json_encode(["success" => false, "error" => "This project is no longer accepting applications."]);
    exit;
}

// ── Check for duplicate application ────────────────────────
$sqlDup  = "SELECT application_id FROM dbo.Project_Applications WHERE project_id = ? AND dev_id = ?";
$stmtDup = sqlsrv_query($conn, $sqlDup, [$projectId, $devId]);
$existing = sqlsrv_fetch_array($stmtDup, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtDup);

if ($existing) {
    http_response_code(409);
    echo json_encode(["success" => false, "error" => "You have already applied to this project."]);
    exit;
}

// ── INSERT application ─────────────────────────────────────
$sql    = "INSERT INTO dbo.Project_Applications (project_id, dev_id, status) VALUES (?, ?, 'Applied');";
$stmt   = sqlsrv_query($conn, $sql, [$projectId, $devId]);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to submit application."]);
    exit;
}
sqlsrv_free_stmt($stmt);

// Retrieve the application_id
$stmtId = sqlsrv_query($conn, "SELECT SCOPE_IDENTITY() AS application_id");
$row    = sqlsrv_fetch_array($stmtId, SQLSRV_FETCH_ASSOC);
$appId  = (int)$row['application_id'];
sqlsrv_free_stmt($stmtId);

echo json_encode([
    "success"        => true,
    "message"        => "Application submitted successfully.",
    "application_id" => $appId,
    "status"         => "Applied"
]);

sqlsrv_close($conn);
