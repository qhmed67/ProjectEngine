<?php
/**
 * Create a new project (Client only).
 * POST /api/post_project.php
 *
 * Validates inputs, checks session for Client role,
 * inserts into Projects table within a transaction.
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

// ── Auth Guard: Must be a logged-in Client ─────────────────
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Client') {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "error"   => "Unauthorized. You must be logged in as a Client to post projects."
    ]);
    exit;
}

$clientId = (int)$_SESSION['client_id'];

// ── Collect & Sanitize Input ───────────────────────────────
$title          = trim($_POST['title']          ?? '');
$description    = trim($_POST['description']    ?? '');
$budgetTier     = trim($_POST['budget_tier']    ?? '');
$requiredLevel  = trim($_POST['required_level'] ?? '');

// ── Validation ─────────────────────────────────────────────
$errors = [];

if (empty($title))         $errors[] = "Project title is required.";
if (strlen($title) > 300)  $errors[] = "Title must be under 300 characters.";
if (empty($description))   $errors[] = "Project description is required.";
if (empty($budgetTier))    $errors[] = "Budget tier is required.";

$validLevels = ['Trainee', 'Junior', 'Mid', 'Senior'];
if (!in_array($requiredLevel, $validLevels)) {
    $errors[] = "Required level must be one of: " . implode(', ', $validLevels);
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(["success" => false, "errors" => $errors]);
    exit;
}

// ── BEGIN TRANSACTION ──────────────────────────────────────
sqlsrv_begin_transaction($conn);

try {
    // ── Prepared INSERT Statement ──────────────────────────
    $sql = "INSERT INTO dbo.Projects
                (client_id, title, description, budget_tier, required_level, status)
            OUTPUT INSERTED.project_id
            VALUES
                (?, ?, ?, ?, ?, 'Pending');";

    $params = [
        [$clientId,      SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_INT],
        [$title,         SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_NVARCHAR(300)],
        [$description,   SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_NVARCHAR('max')],
        [$budgetTier,    SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_NVARCHAR(50)],
        [$requiredLevel, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_NVARCHAR(20)],
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $sqlErrors = sqlsrv_errors();
        throw new Exception("INSERT failed: " . ($sqlErrors[0]['message'] ?? 'Unknown error'));
    }

    // ── Retrieve the generated project_id ──────────────────
    $row      = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $projectId = (int)$row['project_id'];
    sqlsrv_free_stmt($stmt);

    if ($projectId <= 0) {
        throw new Exception("Failed to retrieve new project ID.");
    }

    // ── COMMIT TRANSACTION ─────────────────────────────────
    sqlsrv_commit($conn);

    // ── Success Response ───────────────────────────────────
    http_response_code(201);
    echo json_encode([
        "success"    => true,
        "message"    => "Project created successfully.",
        "project_id" => $projectId,
        "status"     => "Pending",
        "redirect"   => "dashboard.html"
    ]);

} catch (Exception $e) {
    // ── ROLLBACK on any failure ────────────────────────────
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error"   => $e->getMessage()
    ]);
}

sqlsrv_close($conn);
