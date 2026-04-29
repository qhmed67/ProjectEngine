<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Password is required to delete account.']);
    exit;
}

// Check password
$sqlUser = "SELECT password_hash FROM dbo.Users WHERE user_id = ?";
$stmtUser = sqlsrv_query($conn, $sqlUser, [$userId]);
$user = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'error' => 'Incorrect password.']);
    exit;
}

// Check active projects connection
if ($role === 'Client') {
    $q = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM dbo.Projects WHERE client_id = ?", [$userId]);
    $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
    if ($r && $r['c'] > 0) {
        echo json_encode(['success' => false, 'error' => 'You cannot delete your account because you have active projects. Please close them first.']);
        exit;
    }
} else if ($role === 'Developer') {
    $q = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM dbo.Project_Applications WHERE dev_id = ? AND status = 'Accepted'", [$userId]);
    $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
    if ($r && $r['c'] > 0) {
        echo json_encode(['success' => false, 'error' => 'You cannot delete your account because you are currently working on a project.']);
        exit;
    }
}

sqlsrv_begin_transaction($conn);
try {
    // Manually delete dependencies to avoid FK errors if ON DELETE CASCADE is not set everywhere
    if ($role === 'Developer') {
        sqlsrv_query($conn, "DELETE FROM dbo.Developer_Skills WHERE dev_id = ?", [$userId]);
        sqlsrv_query($conn, "DELETE FROM dbo.Project_Applications WHERE dev_id = ?", [$userId]);
        sqlsrv_query($conn, "DELETE FROM dbo.Developers WHERE dev_id = ?", [$userId]);
    } else {
        sqlsrv_query($conn, "DELETE FROM dbo.Clients WHERE client_id = ?", [$userId]);
    }
    
    // Delete messages
    sqlsrv_query($conn, "DELETE FROM dbo.Workspace_Messages WHERE sender_user_id = ?", [$userId]);
    
    // Finally delete user
    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.Users WHERE user_id = ?", [$userId]);
    if ($stmt === false) throw new Exception("Failed to delete user record.");
    
    sqlsrv_commit($conn);
    session_destroy();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

sqlsrv_close($conn);
