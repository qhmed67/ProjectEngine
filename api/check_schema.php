<?php
/**
 * Diagnostic script — checks if the database schema matches what the code expects.
 * Open in browser: http://localhost/ProjectEngine%20-%20Copy%20-%20Copy/api/check_schema.php
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

$results = [];

// Check 1: Can we connect?
$results['connection'] = ($conn !== false) ? 'OK' : 'FAILED';

// Check 2: Does Users table exist?
$stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.Users");
$results['users_table'] = ($stmt !== false) ? 'OK' : 'MISSING';
if ($stmt) sqlsrv_free_stmt($stmt);

// Check 3: Does Clients table exist?
$stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.Clients");
$results['clients_table'] = ($stmt !== false) ? 'OK' : 'MISSING';
if ($stmt) sqlsrv_free_stmt($stmt);

// Check 4: Does Developers table exist with bio column?
$stmt = sqlsrv_query($conn, "SELECT TOP 1 bio FROM dbo.Developers");
if ($stmt !== false) {
    $results['developers_bio_column'] = 'OK';
    sqlsrv_free_stmt($stmt);
} else {
    $results['developers_bio_column'] = 'MISSING — Run: ALTER TABLE dbo.Developers ADD bio NVARCHAR(MAX) NULL;';
    // Auto-fix: add the column
    $fix = sqlsrv_query($conn, "ALTER TABLE dbo.Developers ADD bio NVARCHAR(MAX) NULL;");
    if ($fix !== false) {
        $results['auto_fix_bio'] = 'FIXED — bio column added successfully';
        sqlsrv_free_stmt($fix);
    } else {
        $results['auto_fix_bio'] = 'Could not auto-fix';
    }
}

// Check 5: Does Skills table exist?
$stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.Skills");
$results['skills_table'] = ($stmt !== false) ? 'OK' : 'MISSING';
if ($stmt) sqlsrv_free_stmt($stmt);

// Check 6: Count existing users (to check for leftover data)
$stmt = sqlsrv_query($conn, "SELECT COUNT(*) AS cnt FROM dbo.Users");
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$results['total_users'] = $row['cnt'];
sqlsrv_free_stmt($stmt);

// Check 7: Session status
session_start();
$results['session_user_id'] = $_SESSION['user_id'] ?? 'NOT SET';
$results['session_role'] = $_SESSION['role'] ?? 'NOT SET';

echo json_encode($results, JSON_PRETTY_PRINT);
sqlsrv_close($conn);
