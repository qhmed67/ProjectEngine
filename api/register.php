<?php
/**
 * Register a new Client or Developer account.
 * POST /api/register.php
 *
 * 1. Validate fields.
 * 2. Hash password (bcrypt).
 * 3. Insert into Users, then Clients or Developers.
 * 4. Parse comma-separated skills for devs.
 * 5. Commit or rollback.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';

// ── Only accept POST ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed."]);
    exit;
}

// ── Collect & Sanitize Input ───────────────────────────────
$fullName       = trim($_POST['full_name']    ?? '');
$email          = trim($_POST['email']        ?? '');
$password       = $_POST['password']          ?? '';
$role           = trim($_POST['role']         ?? '');  // 'Client' or 'Developer'

// Developer-specific fields
$level          = trim($_POST['level']        ?? '');
$skillsRaw      = trim($_POST['skills']       ?? '');
$companyName    = trim($_POST['company_name'] ?? '');

// ── Validation ─────────────────────────────────────────────
$errors = [];

if (empty($fullName))   $errors[] = "Full name is required.";
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
if (!in_array($role, ['Client', 'Developer'])) $errors[] = "Role must be 'Client' or 'Developer'.";

if ($role === 'Developer') {
    if (!in_array($level, ['Trainee', 'Junior', 'Mid', 'Senior'])) {
        $errors[] = "Level must be Trainee, Junior, Mid, or Senior.";
    }
}
if ($role === 'Client' && empty($companyName)) {
    $errors[] = "Company name is required for clients.";
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(["success" => false, "errors" => $errors]);
    exit;
}

// ── Hash Password ──────────────────────────────────────────
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// ── BEGIN TRANSACTION ──────────────────────────────────────
sqlsrv_begin_transaction($conn);

try {
    // ── Step 1: INSERT into Users ──────────────────────────
    $sqlUser = "INSERT INTO dbo.Users (email, password_hash, role)
                OUTPUT INSERTED.user_id
                VALUES (?, ?, ?);";
    $paramsUser = [$email, $passwordHash, $role];
    $stmtUser   = sqlsrv_query($conn, $sqlUser, $paramsUser);

    if ($stmtUser === false) {
        throw new Exception("Failed to create user record.");
    }
    
    // Fetch the generated ID directly from the INSERT statement
    $row = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC);
    $userId = (int)$row['user_id'];
    sqlsrv_free_stmt($stmtUser);

    if ($userId <= 0) {
        throw new Exception("Failed to retrieve new user ID.");
    }

    // ── Step 3: Insert role-specific profile ───────────────
    if ($role === 'Client') {
        $sqlClient = "INSERT INTO dbo.Clients (client_id, company_name, contact_number)
                      VALUES (?, ?, ?);";
        $contactNumber = trim($_POST['contact_number'] ?? null);
        $paramsClient  = [$userId, $companyName, $contactNumber];
        $stmtClient    = sqlsrv_query($conn, $sqlClient, $paramsClient);

        if ($stmtClient === false) {
            throw new Exception("Failed to create client profile.");
        }
        sqlsrv_free_stmt($stmtClient);

    } elseif ($role === 'Developer') {
        $sqlDev = "INSERT INTO dbo.Developers (dev_id, full_name, level, hourly_rate, portfolio_url, job_title, github_url, linkedin_url, bio, is_booked)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0);";
        $hourlyRate   = floatval($_POST['hourly_rate']   ?? 0);
        $portfolioUrl = trim($_POST['portfolio_url']     ?? '');
        $jobTitle     = trim($_POST['job_title']         ?? '');
        $githubUrl    = trim($_POST['github_url']        ?? '');
        $linkedinUrl  = trim($_POST['linkedin_url']      ?? '');
        $bio          = trim($_POST['bio']               ?? '');
        $paramsDev    = [$userId, $fullName, $level, $hourlyRate, $portfolioUrl ?: null, $jobTitle ?: null, $githubUrl ?: null, $linkedinUrl ?: null, $bio ?: null];
        $stmtDev      = sqlsrv_query($conn, $sqlDev, $paramsDev);

        if ($stmtDev === false) {
            throw new Exception("Failed to create developer profile.");
        }
        sqlsrv_free_stmt($stmtDev);

        // ── Step 4: Parse & assign skills ──────────────────
        if (!empty($skillsRaw)) {
            $skillNames = array_map('trim', explode(',', $skillsRaw));
            $skillNames = array_filter($skillNames); // remove empty entries

            foreach ($skillNames as $skillName) {
                // Upsert: find existing skill or insert new one
                $sqlFindSkill = "SELECT skill_id FROM dbo.Skills WHERE skill_name = ?";
                $stmtFind     = sqlsrv_query($conn, $sqlFindSkill, [$skillName]);
                $skillRow     = sqlsrv_fetch_array($stmtFind, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmtFind);

                if ($skillRow) {
                    $skillId = (int)$skillRow['skill_id'];
                } else {
                    // Insert new skill into lookup table
                    $sqlInsertSkill = "INSERT INTO dbo.Skills (skill_name) OUTPUT INSERTED.skill_id VALUES (?);";
                    $stmtInsSkill   = sqlsrv_query($conn, $sqlInsertSkill, [$skillName]);
                    if ($stmtInsSkill === false) {
                        throw new Exception("Failed to insert skill: $skillName");
                    }
                    
                    // Retrieve the new skill_id
                    $skRow    = sqlsrv_fetch_array($stmtInsSkill, SQLSRV_FETCH_ASSOC);
                    $skillId  = (int)$skRow['skill_id'];
                    sqlsrv_free_stmt($stmtInsSkill);
                }

                // Link developer ↔ skill in junction table
                $sqlJunction = "INSERT INTO dbo.Developer_Skills (dev_id, skill_id) VALUES (?, ?);";
                $stmtJunc    = sqlsrv_query($conn, $sqlJunction, [$userId, $skillId]);
                if ($stmtJunc === false) {
                    throw new Exception("Failed to link skill: $skillName");
                }
                sqlsrv_free_stmt($stmtJunc);
            }
        }
    }

    // ── COMMIT ─────────────────────────────────────────────
    sqlsrv_commit($conn);

    // ── Set Session ────────────────────────────────────────
    $_SESSION['user_id']   = $userId;
    $_SESSION['email']     = $email;
    $_SESSION['role']      = $role;
    $_SESSION['full_name'] = $fullName;

    if ($role === 'Client') {
        $_SESSION['client_id'] = $userId;
    } else {
        $_SESSION['dev_id'] = $userId;
    }

    echo json_encode([
        "success"  => true,
        "message"  => "Registration successful.",
        "user_id"  => $userId,
        "role"     => $role,
        "redirect" => "dashboard.html"
    ]);

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error"   => $e->getMessage()
    ]);
}

sqlsrv_close($conn);
