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

sqlsrv_begin_transaction($conn);

try {
    if ($role === 'Client') {
        $companyName = trim($_POST['company_name'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');
        
        if (empty($companyName)) throw new Exception("Company name is required.");
        
        $sql = "UPDATE dbo.Clients SET company_name = ?, contact_number = ? WHERE client_id = ?";
        $stmt = sqlsrv_query($conn, $sql, [$companyName, $contactNumber, $userId]);
        if ($stmt === false) throw new Exception("Failed to update client profile.");
        sqlsrv_free_stmt($stmt);
        
    } else if ($role === 'Developer') {
        $fullName = trim($_POST['full_name'] ?? '');
        $level = trim($_POST['level'] ?? '');
        $jobTitle = trim($_POST['job_title'] ?? '');
        $hourlyRate = floatval($_POST['hourly_rate'] ?? 0);
        $portfolioUrl = trim($_POST['portfolio_url'] ?? '');
        $githubUrl = trim($_POST['github_url'] ?? '');
        $linkedinUrl = trim($_POST['linkedin_url'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        if (empty($fullName)) throw new Exception("Full name is required.");
        if (!in_array($level, ['Trainee', 'Junior', 'Mid', 'Senior'])) throw new Exception("Invalid level.");
        
        $sql = "UPDATE dbo.Developers SET full_name=?, level=?, job_title=?, hourly_rate=?, portfolio_url=?, github_url=?, linkedin_url=?, bio=? WHERE dev_id=?";
        $params = [$fullName, $level, $jobTitle, $hourlyRate, $portfolioUrl, $githubUrl, $linkedinUrl, $bio, $userId];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) throw new Exception("Failed to update developer profile.");
        sqlsrv_free_stmt($stmt);
        
        // Update skills
        $skillsRaw = trim($_POST['skills'] ?? '');
        sqlsrv_query($conn, "DELETE FROM dbo.Developer_Skills WHERE dev_id = ?", [$userId]);
        if (!empty($skillsRaw)) {
            $skillNames = array_map('trim', explode(',', $skillsRaw));
            foreach ($skillNames as $sk) {
                if (empty($sk)) continue;
                // Check if skill exists
                $q = sqlsrv_query($conn, "SELECT skill_id FROM dbo.Skills WHERE skill_name = ?", [$sk]);
                $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
                $skillId = 0;
                if ($r) {
                    $skillId = $r['skill_id'];
                } else {
                    $iq = sqlsrv_query($conn, "INSERT INTO dbo.Skills (skill_name) OUTPUT INSERTED.skill_id VALUES (?)", [$sk]);
                    $ir = sqlsrv_fetch_array($iq, SQLSRV_FETCH_ASSOC);
                    if ($ir) $skillId = $ir['skill_id'];
                }
                if ($skillId > 0) {
                    sqlsrv_query($conn, "INSERT INTO dbo.Developer_Skills (dev_id, skill_id) VALUES (?, ?)", [$userId, $skillId]);
                }
            }
        }
    } else {
        throw new Exception("Unknown role.");
    }
    
    sqlsrv_commit($conn);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

sqlsrv_close($conn);
