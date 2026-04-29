<?php
/**
 * Authenticate user and start session.
 * POST /api/login.php
 *
 * Looks up email, verifies bcrypt hash, loads
 * the role-specific profile, and populates $_SESSION.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed."]);
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';

if (empty($email) || empty($password)) {
    http_response_code(422);
    echo json_encode(["success" => false, "error" => "Email and password are required."]);
    exit;
}

// ── Look up user by email ──────────────────────────────────
$sql  = "SELECT user_id, email, password_hash, role FROM dbo.Users WHERE email = ?";
$stmt = sqlsrv_query($conn, $sql, [$email]);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Query failed."]);
    exit;
}

$user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Invalid email or password."]);
    exit;
}

// ── Load role-specific profile ─────────────────────────────
$userId = (int)$user['user_id'];
$role   = $user['role'];
$profileName = '';

if ($role === 'Client') {
    $sqlProfile  = "SELECT company_name FROM dbo.Clients WHERE client_id = ?";
    $stmtProfile = sqlsrv_query($conn, $sqlProfile, [$userId]);
    $profile     = sqlsrv_fetch_array($stmtProfile, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtProfile);
    $profileName = $profile['company_name'] ?? 'Client';
    $_SESSION['client_id'] = $userId;

} elseif ($role === 'Developer') {
    $sqlProfile  = "SELECT full_name, level, is_booked FROM dbo.Developers WHERE dev_id = ?";
    $stmtProfile = sqlsrv_query($conn, $sqlProfile, [$userId]);
    $profile     = sqlsrv_fetch_array($stmtProfile, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtProfile);
    $profileName = $profile['full_name'] ?? 'Developer';
    $_SESSION['dev_id'] = $userId;
    $_SESSION['level']  = $profile['level'] ?? '';
}

// ── Populate Session ───────────────────────────────────────
$_SESSION['user_id']   = $userId;
$_SESSION['email']     = $user['email'];
$_SESSION['role']      = $role;
$_SESSION['full_name'] = $profileName;

echo json_encode([
    "success"   => true,
    "message"   => "Login successful.",
    "user_id"   => $userId,
    "role"      => $role,
    "name"      => $profileName,
    "redirect"  => "dashboard.html"
]);

sqlsrv_close($conn);
