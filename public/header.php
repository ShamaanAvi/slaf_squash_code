<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sri Lanka Air Force Squash</title>
    
    <meta name="view-transition" content="same-origin" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        @view-transition { navigation: auto; }

        /* Animation restricted to content only so navbar stays put */
        .main-content-wrapper {
            animation: slideUpFade 0.4s ease-out;
        }

        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Navbar link underline setup */
        .nav-link {
            position: relative;
            padding-bottom: 8px !important;
            transition: color 0.3s ease;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            bottom: 0;
            left: 50%;
            background-color: #0d6efd;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(-50%);
        }

        /* This name is the "key" that makes the blue line slide across tabs */
        .nav-link.active::after {
            view-transition-name: active-nav-indicator;
            width: 80%;
        }

        .nav-link.active {
            color: #fff !important;
            font-weight: bold;
        }

        /* Input stability */
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15) !important;
        }
    </style>
</head>
<body class="bg-light">

<?php 
$navbarPath = __DIR__ . '/navbar.php';
if (file_exists($navbarPath)) { include $navbarPath; }
?>

<div class="main-content-wrapper">
