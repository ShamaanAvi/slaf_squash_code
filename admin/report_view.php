<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

$tournamentId = (int)($_GET['tournament_id'] ?? 0);
$tournament = getTournamentDetails($conn, $tournamentId);
if (!$tournament) {
    http_response_code(404);
    exit('Tournament not found.');
}
$players = getTournamentAssignedPlayers($conn, $tournamentId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Report - <?php echo e($tournament['display_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .report-page { max-width: 1100px; margin: 24px auto; background: #fff; padding: 32px; }
        .report-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .report-table-wrap .table { min-width: max-content; }
        @media (max-width: 575.98px) {
            .report-page { margin: 0; padding: 16px; }
            .report-page > .d-flex { flex-wrap: wrap; gap: 12px; }
            .report-page .no-print { width: 100%; }
        }
        @media print {
            body { background: #fff; }
            .report-page { margin: 0; max-width: none; padding: 0; }
            .no-print { display: none !important; }
            a[href]::after { content: ""; }
        }
    </style>
</head>
<body>
<main class="report-page shadow-sm">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">Tournament Report</h1>
            <div class="text-muted"><?php echo e($tournament['display_name']); ?></div>
        </div>
        <button class="btn btn-primary no-print" onclick="window.print()">Print / Save PDF</button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><strong>Category</strong><br><?php echo e($tournament['category_name']); ?></div>
        <div class="col-md-3"><strong>Tier</strong><br>Tier <?php echo e($tournament['tier']); ?></div>
        <div class="col-md-3"><strong>Dates</strong><br><?php echo e($tournament['held_on']); ?> to <?php echo e($tournament['end_on'] ?: $tournament['held_on']); ?></div>
        <div class="col-md-3"><strong>Assigned Players</strong><br><?php echo count($players); ?></div>
    </div>

    <div class="report-table-wrap">
    <table class="table table-bordered table-sm align-middle">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Player</th>
                <th>Gender</th>
                <th>DOB</th>
                <th>Category</th>
                <th>Document</th>
                <th>Passport</th>
                <th>Result</th>
                <th class="text-end">Points</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($players as $index => $player): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo e($player['full_name']); ?></td>
                    <td><?php echo e($player['gender']); ?></td>
                    <td><?php echo e($player['dob']); ?></td>
                    <td><?php echo e($player['calculated_category_name']); ?></td>
                    <td><?php echo e($player['identity_type'] . (!empty($player['nic']) ? ': ' . $player['nic'] : '')); ?></td>
                    <td><?php echo e($player['passport_status_label']); ?></td>
                    <td>
                        <?php if ($player['finish_position'] !== null || (int)$player['is_penalty'] === 1): ?>
                            <?php echo e(formatFinishPosition($player['finish_position'], $player['is_penalty'])); ?>
                        <?php else: ?>
                            No result
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?php echo e(number_format((float)($player['points_awarded'] ?? 0), 2)); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$players): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No players assigned.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</main>
</body>
</html>
