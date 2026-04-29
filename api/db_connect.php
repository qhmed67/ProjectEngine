<?php
/**
 * ============================================================
 *  ProjectEngine – Database Connection Configuration
 *  Server : .\SQLEXPRESS01 (SQL Server Auth)
 *  Database: ProjectEngineDB
 * ============================================================
 */

// Suppress PHP HTML errors — API must always return clean JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

$serverName   = ".\\SQLEXPRESS01";
$databaseName = "ProjectEngineDB";

// IMPORTANT: Treat warnings as warnings only (not errors).
// Without this, ODBC Driver 17 informational messages (01000)
// and the IM006 SQLSetConnectAttr notice make sqlsrv_connect()
// return false even when the connection actually succeeded.
sqlsrv_configure("WarningsReturnAsErrors", 0);

// Connection options: SQL Server Auth.
// Use integer 0/1 for Encrypt/Trust — ODBC Driver 17 rejects
// PHP booleans and throws IM006 (Driver's SQLSetConnectAttr failed).
$connectionOptions = [
    "Database"               => $databaseName,
    "UID"                    => "ProjectEngineUser",
    "PWD"                    => "Engine@2026!",
    "Encrypt"                => 0,   // 0 = off  (not boolean false)
    "TrustServerCertificate" => 1    // 1 = yes  (not boolean true)
];

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    $errors = sqlsrv_errors();
    $errorMsg = "Unknown error";
    if ($errors && isset($errors[0]['message'])) {
        $errorMsg = $errors[0]['message'];
    }
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error"   => "Database connection failed.",
        "detail"  => $errorMsg
    ]);
    exit;
}
