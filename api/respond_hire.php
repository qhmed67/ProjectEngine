<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Developer') {
    echo json_encode(['success' => false, 'error' => 'Only developers can respond to requests.']);
    exit;
}

$devId = (int)$_SESSION['user_id'];
$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

if ($projectId <= 0 || !in_array($status, ['Accepted', 'Rejected'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
    exit;
}

$sql = "UPDATE dbo.Project_Applications SET status = ? WHERE project_id = ? AND dev_id = ?";
$stmt = sqlsrv_query($conn, $sql, [$status, $projectId, $devId]);

if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to update request status.']);
    exit;
}
sqlsrv_free_stmt($stmt);

// If accepted, we might want to update the project status to Active and mark developer as booked
if ($status === 'Accepted') {
    // Activate project
    $updateProj = "UPDATE dbo.Projects SET status = 'Active' WHERE project_id = ?";
    sqlsrv_query($conn, $updateProj, [$projectId]);
    
    // Mark developer as booked
    $updateDev = "UPDATE dbo.Developers SET is_booked = 1 WHERE dev_id = ?";
    sqlsrv_query($conn, $updateDev, [$devId]);
    
    // Reject all other pending applications for this developer so those clients can hire someone else
    $rejectOthers = "UPDATE dbo.Project_Applications SET status = 'Rejected' WHERE dev_id = ? AND project_id != ? AND status IN ('Applied', 'Pending')";
    sqlsrv_query($conn, $rejectOthers, [$devId, $projectId]);
}

echo json_encode(['success' => true]);
sqlsrv_close($conn);
