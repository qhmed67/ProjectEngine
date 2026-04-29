<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

$sql = "SELECT dev_id, full_name, level, hourly_rate FROM dbo.Developers ORDER BY dev_id DESC";
$stmt = sqlsrv_query($conn, $sql);

$developers = [];
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Fetch skills for each dev
        $devId = $row['dev_id'];
        $sqlSkills = "SELECT s.skill_name FROM dbo.Skills s INNER JOIN dbo.Developer_Skills ds ON s.skill_id = ds.skill_id WHERE ds.dev_id = ?";
        $stmtSkills = sqlsrv_query($conn, $sqlSkills, [$devId]);
        $skills = [];
        if ($stmtSkills !== false) {
            while ($sRow = sqlsrv_fetch_array($stmtSkills, SQLSRV_FETCH_ASSOC)) {
                $skills[] = $sRow['skill_name'];
            }
            sqlsrv_free_stmt($stmtSkills);
        }
        $row['skills'] = $skills;
        $developers[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

echo json_encode(['success' => true, 'developers' => $developers]);
sqlsrv_close($conn);
