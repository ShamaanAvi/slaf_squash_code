<?php

//Score Deletion Handler

require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id'])) {
    validateCsrfToken();
    $id = (int)$_POST['id'];

    try {
        if (deleteScore($conn, $id)) {
            // Redirect back to the list with a success flag
            header("Location: list.php?deleted=1");
            exit;
        }
    } catch (Exception $e) {
        // Redirect back with an error flag
        header("Location: list.php?error=1");
        exit;
    }
} else {
    // If no ID is provided, just go back to the list
    header("Location: list.php");
    exit;
}
