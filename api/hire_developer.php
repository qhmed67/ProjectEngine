<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Client') {
    echo json_encode(['success' => false, 'error' => 'Only clients can hire developers.']);
    exit;
}

$devId = isset($_POST['dev_id']) ? (int)$_POST['dev_id'] : 0;
$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

if ($devId <= 0 || $projectId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters. Please select a project.']);
    exit;
}

// 1. Check if developer is already booked
$sqlCheckDev = "SELECT is_booked FROM dbo.Developers WHERE dev_id = ?";
$stmtDev = sqlsrv_query($conn, $sqlCheckDev, [$devId]);
$dev = sqlsrv_fetch_array($stmtDev, SQLSRV_FETCH_ASSOC);
if ($dev && $dev['is_booked']) {
    echo json_encode(['success' => false, 'error' => 'This developer is already booked on another project.']);
    exit;
}

// 2. Check if this project already has a pending/accepted application
$sqlCheckProj = "SELECT status FROM dbo.Project_Applications WHERE project_id = ? AND status IN ('Applied', 'Pending', 'Accepted')";
$stmtProj = sqlsrv_query($conn, $sqlCheckProj, [$projectId]);
if (sqlsrv_has_rows($stmtProj)) {
    echo json_encode(['success' => false, 'error' => 'You already have a pending or accepted hire request for this project. Please wait for their response or cancel it.']);
    exit;
}

// Insert into Project_Applications with status 'Applied'
$sql = "INSERT INTO dbo.Project_Applications (project_id, dev_id, status) VALUES (?, ?, 'Applied')";
$stmt = sqlsrv_query($conn, $sql, [$projectId, $devId]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'Developer has already been invited or applied to this project.']);
    exit;
}
sqlsrv_free_stmt($stmt);
echo json_encode(['success' => true, 'message' => 'Hire request sent successfully!']);
sqlsrv_close($conn);
