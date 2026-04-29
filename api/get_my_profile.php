<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

$sqlUser = "SELECT email, role FROM dbo.Users WHERE user_id = ?";
$stmtUser = sqlsrv_query($conn, $sqlUser, [$userId]);
$user = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$profile = ['email' => $user['email'], 'role' => $user['role']];

if ($user['role'] === 'Client') {
    $sqlClient = "SELECT company_name, contact_number FROM dbo.Clients WHERE client_id = ?";
    $stmtClient = sqlsrv_query($conn, $sqlClient, [$userId]);
    $client = sqlsrv_fetch_array($stmtClient, SQLSRV_FETCH_ASSOC);
    if ($client) {
        $profile = array_merge($profile, $client);
    }
} else if ($user['role'] === 'Developer') {
    $sqlDev = "SELECT full_name, level, hourly_rate, portfolio_url, job_title, github_url, linkedin_url, bio FROM dbo.Developers WHERE dev_id = ?";
    $stmtDev = sqlsrv_query($conn, $sqlDev, [$userId]);
    $dev = sqlsrv_fetch_array($stmtDev, SQLSRV_FETCH_ASSOC);
    if ($dev) {
        $profile = array_merge($profile, $dev);
        
        // Fetch skills
        $sqlSkills = "SELECT s.skill_name FROM dbo.Skills s INNER JOIN dbo.Developer_Skills ds ON s.skill_id = ds.skill_id WHERE ds.dev_id = ?";
        $stmtSkills = sqlsrv_query($conn, $sqlSkills, [$userId]);
        $skills = [];
        if ($stmtSkills !== false) {
            while ($row = sqlsrv_fetch_array($stmtSkills, SQLSRV_FETCH_ASSOC)) {
                $skills[] = $row['skill_name'];
            }
        }
        $profile['skills'] = implode(', ', $skills);
    }
}

echo json_encode(['success' => true, 'profile' => $profile]);
sqlsrv_close($conn);
