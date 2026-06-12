<?php
/**
 * Database Configuration and Connection
 * * This file establishes the connection to the MySQL database using the MySQLi extension.
 * It is required in all files that perform database operations.
 */

// Database Credentials
$host = "localhost";
$user = "root";
$password = "";
$db = "slaf_squash_test";

// Enable MySQLi exception reporting for better error tracking
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Attempt to establish a connection
    $conn = mysqli_connect($host, $user, $password, $db);
    
    // Set charset to utf8mb4 to support special characters and emojis
    mysqli_set_charset($conn, "utf8mb4");

} catch (mysqli_sql_exception $e) {

    //Exception Handling:
    error_log($e->getMessage());
    
    // Stop execution and show a user-friendly message
    die("<h3>Database Connection Error</h3> 
         <p>We are currently experiencing technical difficulties. Please try again later.</p>");
}

/**
 * Note: We leave the connection open here. 
 * It will be closed automatically when the script ends, 
 * or mysqli_close($conn) can be used in footers.
 */
?>