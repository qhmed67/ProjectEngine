<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

$devId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($devId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid developer ID']);
    exit;
}

// Get developer details
$sql = "SELECT d.dev_id, d.full_name, d.level, d.hourly_rate, d.portfolio_url,
               d.job_title, d.github_url, d.linkedin_url, d.is_booked, d.bio, u.email
        FROM dbo.Developers d
        INNER JOIN dbo.Users u ON d.dev_id = u.user_id
        WHERE d.dev_id = ?";
$stmt = sqlsrv_query($conn, $sql, [$devId]);
if ($stmt === false || !($dev = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    echo json_encode(['success' => false, 'error' => 'Developer not found']);
    exit;
}
sqlsrv_free_stmt($stmt);

// Get skills
$sqlSkills = "SELECT s.skill_name FROM dbo.Skills s 
              INNER JOIN dbo.Developer_Skills ds ON s.skill_id = ds.skill_id 
              WHERE ds.dev_id = ?";
$stmtSkills = sqlsrv_query($conn, $sqlSkills, [$devId]);
$skills = [];
if ($stmtSkills !== false) {
    while ($row = sqlsrv_fetch_array($stmtSkills, SQLSRV_FETCH_ASSOC)) {
        $skills[] = $row['skill_name'];
    }
    sqlsrv_free_stmt($stmtSkills);
}
$dev['skills'] = $skills;

// Check if current user is a client and has any active/pending projects
$projects = [];
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'Client') {
    $clientId = (int)$_SESSION['user_id'];
    $sqlProj = "SELECT project_id, title FROM dbo.Projects WHERE client_id = ? AND status != 'Completed'";
    $stmtProj = sqlsrv_query($conn, $sqlProj, [$clientId]);
    if ($stmtProj !== false) {
        while ($row = sqlsrv_fetch_array($stmtProj, SQLSRV_FETCH_ASSOC)) {
            $projects[] = $row;
        }
        sqlsrv_free_stmt($stmtProj);
    }
}

echo json_encode(['success' => true, 'developer' => $dev, 'client_projects' => $projects]);
sqlsrv_close($conn);
