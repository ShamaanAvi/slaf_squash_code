<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sri Lanka Air Force Squash | Home</title>

    <meta name="view-transition" content="same-origin" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        @view-transition { navigation: auto; }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Home content animation */
        .home-container {
            animation: slideUpFade 0.6s ease-out;
        }

        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Keep indicator name synced so transitions work when leaving home */
        .nav-link.active::after {
            view-transition-name: active-nav-indicator;
        }
    </style>
</head>
<body>

<?php 
$navbarPath = 'public/navbar.php'; 
if (file_exists($navbarPath)) {
    include $navbarPath;
} else if (file_exists('navbar.php')) {
    include 'navbar.php';
}
?>

<div class="container py-5 home-container">