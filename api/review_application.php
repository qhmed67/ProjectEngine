<?php
/**
 * Accept or reject a developer application (Client only).
 * POST /api/review_application.php
 *
 * On acceptance: activates the project, books the developer,
 * and auto-rejects remaining applicants.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed."]);
    exit;
}

// ── Auth Guard ─────────────────────────────────────────────
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Client') {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Only clients can review applications."]);
    exit;
}

$clientId      = (int)$_SESSION['client_id'];
$applicationId = (int)($_POST['application_id'] ?? 0);
$newStatus     = trim($_POST['status'] ?? '');

if ($applicationId <= 0) {
    http_response_code(422);
    echo json_encode(["success" => false, "error" => "Valid application_id is required."]);
    exit;
}

if (!in_array($newStatus, ['Accepted', 'Rejected'])) {
    http_response_code(422);
    echo json_encode(["success" => false, "error" => "Status must be 'Accepted' or 'Rejected'."]);
    exit;
}

// ── Verify ownership: the application must belong to this client's project ──
$sqlVerify = "SELECT pa.application_id, pa.dev_id, pa.project_id, pa.status
              FROM dbo.Project_Applications pa
              INNER JOIN dbo.Projects p ON p.project_id = pa.project_id
              WHERE pa.application_id = ? AND p.client_id = ?";
$stmtV     = sqlsrv_query($conn, $sqlVerify, [$applicationId, $clientId]);
$app       = sqlsrv_fetch_array($stmtV, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtV);

if (!$app) {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Application not found or access denied."]);
    exit;
}

if ($app['status'] !== 'Applied') {
    http_response_code(409);
    echo json_encode(["success" => false, "error" => "This application has already been reviewed."]);
    exit;
}

// ── BEGIN TRANSACTION ──────────────────────────────────────
sqlsrv_begin_transaction($conn);

try {
    // ── Update application status ──────────────────────────
    $sqlUpdate = "UPDATE dbo.Project_Applications SET status = ? WHERE application_id = ?";
    $stmtUpd   = sqlsrv_query($conn, $sqlUpdate, [$newStatus, $applicationId]);
    if ($stmtUpd === false) throw new Exception("Failed to update application status.");
    sqlsrv_free_stmt($stmtUpd);

    // ── If accepted: activate project + book developer ─────
    if ($newStatus === 'Accepted') {
        // Activate the project
        $sqlActivate = "UPDATE dbo.Projects SET status = 'Active' WHERE project_id = ?";
        $stmtAct     = sqlsrv_query($conn, $sqlActivate, [$app['project_id']]);
        if ($stmtAct === false) throw new Exception("Failed to activate project.");
        sqlsrv_free_stmt($stmtAct);

        // Book the developer
        $sqlBook = "UPDATE dbo.Developers SET is_booked = 1 WHERE dev_id = ?";
        $stmtBk  = sqlsrv_query($conn, $sqlBook, [$app['dev_id']]);
        if ($stmtBk === false) throw new Exception("Failed to book developer.");
        sqlsrv_free_stmt($stmtBk);

        // Reject all other applications for this project
        $sqlRejectOthers = "UPDATE dbo.Project_Applications
                            SET status = 'Rejected'
                            WHERE project_id = ? AND application_id != ? AND status = 'Applied'";
        $stmtRej = sqlsrv_query($conn, $sqlRejectOthers, [$app['project_id'], $applicationId]);
        if ($stmtRej === false) throw new Exception("Failed to reject other applications.");
        sqlsrv_free_stmt($stmtRej);
    }

    sqlsrv_commit($conn);

    echo json_encode([
        "success"  => true,
        "message"  => "Application " . strtolower($newStatus) . " successfully.",
        "status"   => $newStatus
    ]);

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

sqlsrv_close($conn);
