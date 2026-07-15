<?php
require_once "../config/db.php";
require_once "../config/auth.php";

adminOnly();

$id = (int)($_GET['id'] ?? 0);
header("Location: list.php" . ($id > 0 ? "?players=" . $id : ""));
exit;
