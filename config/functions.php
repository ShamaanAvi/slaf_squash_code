<?php
/**
 * Shared helpers and business logic for the SLS ranking system.
 */

function safeSessionStart()
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function isActive($path, $current)
{
    return (strpos($current, $path) !== false) ? 'active' : '';
}

function appUrl($target = '')
{
    $dir = basename(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')));
    $sectionDirs = ['players', 'tournaments', 'scores', 'admin', 'player', 'public'];
    $depth = in_array($dir, $sectionDirs, true) ? 1 : 0;
    return str_repeat('../', $depth) . ltrim($target, '/');
}

function redirectPath($target)
{
    return appUrl($target);
}

function checkSessionTimeout($timeout_duration = 1800)
{
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
        if (isset($_SESSION['LAST_ACTIVITY']) && time() - $_SESSION['LAST_ACTIVITY'] > $timeout_duration) {
            session_unset();
            session_destroy();
            header('Location: ' . redirectPath('index.php?timeout=1'));
            exit;
        }
        $_SESSION['LAST_ACTIVITY'] = time();
    }
}

function generateCsrfToken()
{
    safeSessionStart();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken()
{
    safeSessionStart();
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid request token.');
    }
}

function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . e(generateCsrfToken()) . '">';
}

function logAudit($conn, $action, $targetType = null, $targetId = null, $detail = null)
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return;
    }
    try {
        $stmt = $conn->prepare(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, detail) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issis', $userId, $action, $targetType, $targetId, $detail);
        $stmt->execute();
    } catch (Exception $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

function fetchAll($result)
{
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function getAgeCategories($conn)
{
    $result = $conn->query('SELECT id, name, gender FROM age_categories ORDER BY id ASC');
    return fetchAll($result);
}

function getRankingYearAge($dob, $year = null)
{
    $birthDate = DateTime::createFromFormat('Y-m-d', (string)$dob);
    $errors = DateTime::getLastErrors();
    if (!$birthDate || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new Exception('Please enter a valid date of birth.');
    }

    $targetYear = $year === null ? (int)date('Y') : (int)$year;
    return $targetYear - (int)$birthDate->format('Y');
}

function getAgeCategoryNameForPlayer($dob, $gender, $year = null)
{
    $gender = ucfirst(strtolower(trim((string)$gender)));
    if (!in_array($gender, ['Male', 'Female'], true)) {
        throw new Exception('Please select the player gender.');
    }

    $age = getRankingYearAge($dob, $year);
    if ($age < 0) {
        throw new Exception('Date of birth cannot be in the future.');
    }

    if ($age <= 8) {
        return $gender === 'Male' ? 'Boys U9' : 'Girls U9';
    }
    if ($age <= 10) {
        return $gender === 'Male' ? 'Boys U11' : 'Girls U11';
    }
    if ($age <= 12) {
        return $gender === 'Male' ? 'Boys U13' : 'Girls U13';
    }
    if ($age <= 14) {
        return $gender === 'Male' ? 'Boys U15' : 'Girls U15';
    }
    if ($age <= 16) {
        return $gender === 'Male' ? 'Boys U17' : 'Girls U17';
    }
    if ($age <= 18) {
        return $gender === 'Male' ? 'Boys U19' : 'Girls U19';
    }

    if ($gender === 'Female') {
        return $age >= 35 ? "Women's Over 35" : "Women's Open";
    }

    if ($age >= 65) {
        return "Men's Masters Over 65";
    }
    if ($age >= 60) {
        return "Men's Masters Over 60";
    }
    if ($age >= 55) {
        return "Men's Masters Over 55";
    }
    if ($age >= 50) {
        return "Men's Masters Over 50";
    }
    if ($age >= 45) {
        return "Men's Over 45";
    }
    if ($age >= 40) {
        return "Men's Over 40";
    }
    if ($age >= 35) {
        return "Men's Over 35";
    }

    return "Men's Open";
}

function getAgeCategoryIdByName($conn, $name)
{
    $stmt = $conn->prepare('SELECT id FROM age_categories WHERE name = ? LIMIT 1');
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        throw new Exception('Calculated age category is not configured.');
    }
    return (int)$row['id'];
}

function normalizePlayerGender($gender)
{
    $gender = ucfirst(strtolower(trim((string)$gender)));
    return in_array($gender, ['Male', 'Female'], true) ? $gender : '';
}

function refreshPlayerCalculatedCategory($conn, $playerId, $year = null)
{
    $stmt = $conn->prepare(
        'SELECT id, COALESCE(date_of_birth, dob) AS dob, gender, calculated_category_id
         FROM players WHERE id = ?'
    );
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    if (!$player) {
        return false;
    }

    $dob = $player['dob'] ?? '';
    $gender = normalizePlayerGender($player['gender'] ?? '');
    if ($dob === '' || $gender === '') {
        error_log('Skipped category refresh for player ' . (int)$playerId . ': missing DOB or gender.');
        return false;
    }

    $categoryName = getAgeCategoryNameForPlayer($dob, $gender, $year);
    $categoryId = getAgeCategoryIdByName($conn, $categoryName);
    $previousCategoryId = (int)($player['calculated_category_id'] ?? 0);

    if ($previousCategoryId > 0 && $previousCategoryId !== $categoryId) {
        $delete = $conn->prepare('DELETE FROM player_categories WHERE player_id = ? AND category_id = ?');
        $delete->bind_param('ii', $playerId, $previousCategoryId);
        $delete->execute();
    }

    $insert = $conn->prepare('INSERT IGNORE INTO player_categories (player_id, category_id) VALUES (?, ?)');
    $insert->bind_param('ii', $playerId, $categoryId);
    $insert->execute();

    $update = $conn->prepare('UPDATE players SET calculated_category_id = ? WHERE id = ?');
    $update->bind_param('ii', $categoryId, $playerId);
    $update->execute();

    return true;
}

function refreshAllPlayerCalculatedCategories($conn, $year = null)
{
    $result = $conn->query('SELECT id FROM players ORDER BY id ASC');
    $updated = 0;
    foreach (fetchAll($result) as $row) {
        try {
            if (refreshPlayerCalculatedCategory($conn, (int)$row['id'], $year)) {
                $updated++;
            }
        } catch (Exception $e) {
            error_log('Category refresh failed for player ' . (int)$row['id'] . ': ' . $e->getMessage());
        }
    }
    return $updated;
}

function getPlayers($conn)
{
    $result = $conn->query('SELECT id, full_name FROM players ORDER BY full_name ASC');
    return fetchAll($result);
}

function getSystemSetting($conn, $key, $default = null)
{
    $stmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['setting_value'] : $default;
}

function setSystemSetting($conn, $key, $value)
{
    $stmt = $conn->prepare(
        'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->bind_param('ss', $key, $value);
    return $stmt->execute();
}

function registerPlayer($conn, $data)
{
    $name = trim($data['name'] ?? '');
    $nic = trim($data['nic'] ?? '');
    $dob = $data['dob'] ?? null;
    $gender = ucfirst(strtolower(trim($data['gender'] ?? '')));
    $address = trim($data['address'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');
    $otherCategoryId = (int)($data['other_category_id'] ?? 0);

    if ($name === '' || $nic === '' || $dob === '') {
        throw new Exception('Full name, NIC/Passport, and date of birth are required.');
    }
    if (!in_array($gender, ['Male', 'Female'], true)) {
        throw new Exception('Please select the player gender.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address.');
    }

    $primaryCategoryName = getAgeCategoryNameForPlayer($dob, $gender);
    $primaryCategoryId = getAgeCategoryIdByName($conn, $primaryCategoryName);
    $categoryIds = [$primaryCategoryId];
    if ($otherCategoryId > 0 && $otherCategoryId !== $primaryCategoryId) {
        $stmt = $conn->prepare('SELECT id FROM age_categories WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $otherCategoryId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new Exception('Selected optional category is not valid.');
        }
        $categoryIds[] = $otherCategoryId;
    }

    $passwordHash = password_hash($nic, PASSWORD_BCRYPT);
    $role = 'player';

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('INSERT INTO users (username, password, role, is_first_login) VALUES (?, ?, ?, 1)');
        $stmt->bind_param('sss', $nic, $passwordHash, $role);
        $stmt->execute();
        $userId = $conn->insert_id;

        $stmt = $conn->prepare(
            'INSERT INTO players (user_id, full_name, nic, dob, date_of_birth, gender, calculated_category_id, address, phone, email)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssssisss', $userId, $name, $nic, $dob, $dob, $gender, $primaryCategoryId, $address, $phone, $email);
        $stmt->execute();
        $playerId = $conn->insert_id;

        $stmt = $conn->prepare('UPDATE users SET player_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $playerId, $userId);
        $stmt->execute();

        if ($categoryIds) {
            $catStmt = $conn->prepare('INSERT INTO player_categories (player_id, category_id) VALUES (?, ?)');
            foreach ($categoryIds as $categoryId) {
                $catStmt->bind_param('ii', $playerId, $categoryId);
                $catStmt->execute();
            }
        }

        logAudit($conn, 'player_created', 'players', $playerId, $name);
        $conn->commit();
        return $nic;
    } catch (Exception $e) {
        $conn->rollback();
        if ($conn->errno == 1062) {
            throw new Exception('A player with this NIC/Passport already exists.');
        }
        error_log('Register player failed: ' . $e->getMessage());
        throw new Exception('Could not register player.');
    }
}

function getPlayerProfile($conn, $playerId)
{
    $stmt = $conn->prepare(
        'SELECT p.*, u.username, u.is_first_login
         FROM players p JOIN users u ON p.user_id = u.id
         WHERE p.id = ?'
    );
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function updatePlayerProfile($conn, $playerId, $data, $userId = null, $completeFirstLogin = false)
{
    $name = trim($data['name'] ?? '');
    $dob = $data['dob'] ?? null;
    if ($dob === '') {
        $dob = null;
    }
    $gender = array_key_exists('gender', $data) ? normalizePlayerGender($data['gender']) : null;
    $address = trim($data['address'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $confirm = $data['confirm_password'] ?? '';

    if ($name === '' || $address === '' || $phone === '' || ($completeFirstLogin && $dob === null)) {
        throw new Exception('Please complete all required fields.');
    }
    if (array_key_exists('gender', $data) && $gender === '') {
        throw new Exception('Please select the player gender.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address.');
    }
    if ($password !== '' && $password !== $confirm) {
        throw new Exception('Passwords do not match.');
    }
    if ($completeFirstLogin && $password === '') {
        throw new Exception('Please set a new password.');
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'UPDATE players SET full_name = ?, date_of_birth = COALESCE(?, date_of_birth), dob = COALESCE(?, dob),
             gender = COALESCE(?, gender),
             address = ?, phone = ?, email = ? WHERE id = ?'
        );
        $stmt->bind_param('sssssssi', $name, $dob, $dob, $gender, $address, $phone, $email, $playerId);
        $stmt->execute();
        refreshPlayerCalculatedCategory($conn, $playerId);

        if ($password !== '' && $userId !== null) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $userId);
            $stmt->execute();
        }
        if ($completeFirstLogin && $userId !== null) {
            $zero = 0;
            $stmt = $conn->prepare('UPDATE users SET is_first_login = ? WHERE id = ?');
            $stmt->bind_param('ii', $zero, $userId);
            $stmt->execute();
            $_SESSION['is_first_login'] = 0;
        }

        logAudit($conn, 'profile_updated', 'players', $playerId, null);
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Profile update failed: ' . $e->getMessage());
        throw new Exception('Could not update profile.');
    }
}

function addTournament($conn, $data)
{
    $name = trim($data['name'] ?? '');
    $tier = strtoupper($data['tier'] ?? 'B');
    $drawSize = (int)($data['draw_size'] ?? 0);
    $heldOn = $data['held_on'] ?? '';
    $categoryId = (int)($data['category_id'] ?? 0);

    if ($name === '' || !in_array($tier, ['A', 'B'], true) || $drawSize < 0 || $heldOn === '' || $categoryId <= 0) {
        throw new Exception('Please complete all tournament fields.');
    }

    $stmt = $conn->prepare(
        'INSERT INTO tournaments (name, tournament_name, tier, draw_size, held_on, category_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('sssisi', $name, $name, $tier, $drawSize, $heldOn, $categoryId);
    $ok = $stmt->execute();
    logAudit($conn, 'tournament_created', 'tournaments', $conn->insert_id, $name);
    return $ok;
}

function getAssignedTournamentsWithoutResult($conn, $playerId)
{
    $stmt = $conn->prepare(
        'SELECT t.id, COALESCE(t.name, t.tournament_name) AS display_name, t.draw_size, c.name AS category_name
         FROM tournaments t
         JOIN player_tournaments pt ON t.id = pt.tournament_id
         LEFT JOIN tournament_results tr ON (t.id = tr.tournament_id AND tr.player_id = ?)
         LEFT JOIN age_categories c ON c.id = t.category_id
         WHERE pt.player_id = ? AND tr.id IS NULL AND COALESCE(t.is_archived, 0) = 0
         ORDER BY t.held_on DESC, t.id DESC'
    );
    $stmt->bind_param('ii', $playerId, $playerId);
    $stmt->execute();
    return fetchAll($stmt->get_result());
}

function updateTournament($conn, $id, $data)
{
    $name = trim($data['name'] ?? '');
    $tier = strtoupper($data['tier'] ?? 'B');
    $drawSize = (int)($data['draw_size'] ?? 0);
    $heldOn = $data['held_on'] ?? '';
    $categoryId = (int)($data['category_id'] ?? 0);
    if ($name === '' || !in_array($tier, ['A', 'B'], true) || $drawSize < 0 || $heldOn === '' || $categoryId <= 0) {
        throw new Exception('Please complete all tournament fields.');
    }
    $stmt = $conn->prepare(
        'UPDATE tournaments SET name = ?, tournament_name = ?, tier = ?, draw_size = ?, held_on = ?, category_id = ? WHERE id = ?'
    );
    $stmt->bind_param('sssisii', $name, $name, $tier, $drawSize, $heldOn, $categoryId, $id);
    $ok = $stmt->execute();
    logAudit($conn, 'tournament_updated', 'tournaments', $id, $name);
    return $ok;
}

function archiveTournament($conn, $id)
{
    $one = 1;
    $stmt = $conn->prepare('UPDATE tournaments SET is_archived = ? WHERE id = ?');
    $stmt->bind_param('ii', $one, $id);
    $ok = $stmt->execute();
    logAudit($conn, 'tournament_archived', 'tournaments', $id, null);
    return $ok;
}

function getTournamentById($conn, $id)
{
    $stmt = $conn->prepare('SELECT * FROM tournaments WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getTournamentList($conn, $includeArchived = false)
{
    $sql = 'SELECT t.*, COALESCE(t.name, t.tournament_name) AS display_name, c.name AS category_name
            FROM tournaments t LEFT JOIN age_categories c ON c.id = t.category_id';
    if (!$includeArchived) {
        $sql .= ' WHERE COALESCE(t.is_archived, 0) = 0';
    }
    $sql .= ' ORDER BY t.held_on DESC, t.id DESC';
    return fetchAll($conn->query($sql));
}

function assignPlayerToTournament($conn, $playerId, $tournamentId)
{
    $stmt = $conn->prepare('SELECT category_id FROM tournaments WHERE id = ?');
    $stmt->bind_param('i', $tournamentId);
    $stmt->execute();
    $tournament = $stmt->get_result()->fetch_assoc();
    if (!$tournament) {
        throw new Exception('Tournament not found.');
    }

    $categoryId = (int)$tournament['category_id'];
    $stmt = $conn->prepare('SELECT id FROM player_tournaments WHERE player_id = ? AND tournament_id = ?');
    $stmt->bind_param('ii', $playerId, $tournamentId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('This player is already assigned to this tournament.');
    }

    $stmt = $conn->prepare('SELECT name FROM age_categories WHERE id = ?');
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $categoryName = $category ? $category['name'] : 'Assigned';

    $stmt = $conn->prepare('INSERT INTO player_tournaments (player_id, tournament_id, category) VALUES (?, ?, ?)');
    $stmt->bind_param('iis', $playerId, $tournamentId, $categoryName);
    $stmt->execute();

    $stmt = $conn->prepare('INSERT IGNORE INTO player_categories (player_id, category_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $playerId, $categoryId);
    $stmt->execute();

    logAudit($conn, 'player_assigned', 'tournaments', $tournamentId, 'Player ' . $playerId);
    return $categoryName;
}

function getPoints(string $tier, int $position, int $draw_size): float
{
    if ($draw_size < 4 || $position < 1 || $position > 16) {
        return 0.00;
    }
    $table = [
        'A' => [1 => 405.00, 2 => 270.00, 3 => 202.50, 4 => 150.75, 5 => 121.50, 6 => 108.00, 7 => 96.75, 8 => 81.00, 9 => 54.00, 10 => 54.00, 11 => 54.00, 12 => 54.00, 13 => 54.00, 14 => 54.00, 15 => 54.00, 16 => 54.00],
        'B' => [1 => 270.00, 2 => 180.00, 3 => 135.00, 4 => 100.50, 5 => 81.00, 6 => 72.00, 7 => 64.50, 8 => 54.00, 9 => 36.00, 10 => 36.00, 11 => 36.00, 12 => 36.00, 13 => 36.00, 14 => 36.00, 15 => 36.00, 16 => 36.00],
    ];
    if (getSystemSetting($GLOBALS['conn'], 'transition_mode', '0') === '1' && $position > 8) {
        return 0.00;
    }
    return $table[$tier][$position] ?? 0.00;
}

function saveScore($conn, $playerId, $tournamentId, $finishPosition)
{
    $stmt = $conn->prepare('SELECT tier, draw_size FROM tournaments WHERE id = ? AND COALESCE(is_archived, 0) = 0');
    $stmt->bind_param('i', $tournamentId);
    $stmt->execute();
    $tournament = $stmt->get_result()->fetch_assoc();
    if (!$tournament) {
        throw new Exception('Tournament not found.');
    }
    if ((int)$tournament['draw_size'] < 4) {
        throw new Exception('This tournament has fewer than 4 players and cannot award ranking points.');
    }

    $isPenalty = ($finishPosition === null) ? 1 : 0;
    $points = $isPenalty ? 0.00 : getPoints($tournament['tier'], (int)$finishPosition, (int)$tournament['draw_size']);
    $positionValue = $finishPosition === null ? null : (int)$finishPosition;

    $stmt = $conn->prepare(
        'INSERT INTO tournament_results (player_id, tournament_id, finish_position, points_awarded, is_penalty)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iiidi', $playerId, $tournamentId, $positionValue, $points, $isPenalty);
    $ok = $stmt->execute();
    logAudit($conn, 'result_created', 'tournament_results', $conn->insert_id, 'Player ' . $playerId);
    return $ok;
}

function getScoresList($conn, $playerId = null, $searchTerm = '')
{
    $sql = 'SELECT tr.id, p.full_name, t.name AS tournament_name, ac.name AS category,
                   t.held_on, tr.finish_position, tr.points_awarded, tr.is_penalty
            FROM tournament_results tr
            JOIN players p ON tr.player_id = p.id
            JOIN tournaments t ON tr.tournament_id = t.id
            LEFT JOIN age_categories ac ON ac.id = t.category_id
            WHERE 1=1';
    $params = [];
    $types = '';
    if ($playerId) {
        $sql .= ' AND p.id = ?';
        $params[] = (int)$playerId;
        $types .= 'i';
    } elseif ($searchTerm !== '') {
        $sql .= ' AND p.full_name LIKE ?';
        $params[] = '%' . $searchTerm . '%';
        $types .= 's';
    }
    $sql .= ' ORDER BY t.held_on DESC, tr.id DESC';
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

function getScoreById($conn, $id)
{
    $stmt = $conn->prepare(
        'SELECT tr.*, p.full_name, t.name AS tournament_name, t.tier, t.draw_size
         FROM tournament_results tr
         JOIN players p ON tr.player_id = p.id
         JOIN tournaments t ON tr.tournament_id = t.id
         WHERE tr.id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function updateScore($conn, $id, $finishPosition)
{
    $score = getScoreById($conn, $id);
    if (!$score) {
        throw new Exception('Result not found.');
    }
    $isPenalty = ($finishPosition === null) ? 1 : 0;
    $points = $isPenalty ? 0.00 : getPoints($score['tier'], (int)$finishPosition, (int)$score['draw_size']);
    $positionValue = $finishPosition === null ? null : (int)$finishPosition;
    $stmt = $conn->prepare(
        'UPDATE tournament_results SET finish_position = ?, points_awarded = ?, is_penalty = ? WHERE id = ?'
    );
    $stmt->bind_param('idii', $positionValue, $points, $isPenalty, $id);
    $ok = $stmt->execute();
    logAudit($conn, 'result_updated', 'tournament_results', $id, null);
    return $ok;
}

function deleteScore($conn, $id)
{
    $stmt = $conn->prepare('DELETE FROM tournament_results WHERE id = ?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    logAudit($conn, 'result_deleted', 'tournament_results', $id, null);
    return $ok;
}

function expireOldResults($conn)
{
    $stmt = $conn->prepare(
        'UPDATE tournament_results tr JOIN tournaments t ON tr.tournament_id = t.id
         SET tr.is_active = 0 WHERE t.held_on < DATE_SUB(CURDATE(), INTERVAL 12 MONTH)'
    );
    return $stmt->execute();
}

function calculateRankings($conn, $periodLabel)
{
    refreshAllPlayerCalculatedCategories($conn);
    expireOldResults($conn);
    $divisor = max(1, (float)getSystemSetting($conn, 'ranking_divisor', '4'));
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('DELETE FROM rankings WHERE period_label = ?');
        $stmt->bind_param('s', $periodLabel);
        $stmt->execute();

        $pairs = fetchAll($conn->query(
            'SELECT DISTINCT tr.player_id, t.category_id, p.psa_wsf_ranking
             FROM tournament_results tr
             JOIN tournaments t ON tr.tournament_id = t.id
             JOIN players p ON p.id = tr.player_id
             WHERE tr.is_active = 1 AND t.category_id IS NOT NULL'
        ));

        $grouped = [];
        foreach ($pairs as $pair) {
            $playerId = (int)$pair['player_id'];
            $categoryId = (int)$pair['category_id'];
            $stmt = $conn->prepare(
                'SELECT tr.points_awarded
                 FROM tournament_results tr
                 JOIN tournaments t ON tr.tournament_id = t.id
                 WHERE tr.player_id = ? AND t.category_id = ? AND tr.is_active = 1
                   AND t.held_on >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 ORDER BY t.held_on DESC, tr.id DESC LIMIT 6'
            );
            $stmt->bind_param('ii', $playerId, $categoryId);
            $stmt->execute();
            $results = fetchAll($stmt->get_result());
            $lastSix = array_map('floatval', array_column($results, 'points_awarded'));
            $lastTwo = array_sum(array_slice($lastSix, 0, 2));
            rsort($lastSix, SORT_NUMERIC);
            $average = array_sum(array_slice($lastSix, 0, 4)) / $divisor;
            $grouped[$categoryId][] = [
                'player_id' => $playerId,
                'average' => $average,
                'last_two' => $lastTwo,
                'psa' => $pair['psa_wsf_ranking'] === null ? PHP_INT_MAX : (int)$pair['psa_wsf_ranking'],
            ];
        }

        $insert = $conn->prepare(
            'INSERT INTO rankings (player_id, category_id, ranking_average, rank_position, period_label)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($grouped as $categoryId => $rows) {
            usort($rows, function ($a, $b) {
                if ($a['average'] != $b['average']) {
                    return $a['average'] < $b['average'] ? 1 : -1;
                }
                if ($a['last_two'] != $b['last_two']) {
                    return $a['last_two'] < $b['last_two'] ? 1 : -1;
                }
                return $a['psa'] <=> $b['psa'];
            });
            $rank = 1;
            foreach ($rows as $row) {
                $playerId = (int)$row['player_id'];
                $categoryIdInt = (int)$categoryId;
                $average = (float)$row['average'];
                $insert->bind_param('iidis', $playerId, $categoryIdInt, $average, $rank, $periodLabel);
                $insert->execute();
                $rank++;
            }
        }

        setSystemSetting($conn, 'last_ranking_run', date('Y-m-d H:i:s'));
        logAudit($conn, 'rankings_calculated', 'rankings', null, $periodLabel);
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Ranking calculation failed: ' . $e->getMessage());
        throw new Exception('Ranking calculation failed.');
    }
}

function formatFinishPosition($position, $isPenalty = 0)
{
    if ($isPenalty || $position === null || $position === '') {
        return 'Withdrawal / No-show';
    }
    $position = (int)$position;
    if ($position % 100 >= 11 && $position % 100 <= 13) {
        return $position . 'th';
    }
    $suffixes = [1 => 'st', 2 => 'nd', 3 => 'rd'];
    return $position . ($suffixes[$position % 10] ?? 'th');
}

function getCountingResultIds($conn, $playerId)
{
    $stmt = $conn->prepare(
        'SELECT tr.id, t.category_id, tr.points_awarded
         FROM tournament_results tr
         JOIN tournaments t ON t.id = tr.tournament_id
         WHERE tr.player_id = ? AND tr.is_active = 1
           AND t.held_on >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         ORDER BY t.category_id ASC, t.held_on DESC, tr.id DESC'
    );
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $byCategory = [];
    foreach (fetchAll($stmt->get_result()) as $row) {
        $byCategory[(int)$row['category_id']][] = $row;
    }
    $ids = [];
    foreach ($byCategory as $rows) {
        $lastSix = array_slice($rows, 0, 6);
        usort($lastSix, function ($a, $b) {
            return (float)$a['points_awarded'] < (float)$b['points_awarded'] ? 1 : -1;
        });
        foreach (array_slice($lastSix, 0, 4) as $row) {
            $ids[(int)$row['id']] = true;
        }
    }
    return $ids;
}

function getLatestPeriodLabel($conn)
{
    $row = $conn->query('SELECT period_label FROM rankings ORDER BY calculated_at DESC, id DESC LIMIT 1')->fetch_assoc();
    return $row ? $row['period_label'] : null;
}

function getCurrentRankings($conn, $categoryId = 0)
{
    $period = getLatestPeriodLabel($conn);
    if (!$period) {
        return [];
    }
    $sql = 'SELECT r.*, p.full_name, ac.name AS category_name
            FROM rankings r
            JOIN players p ON p.id = r.player_id
            JOIN age_categories ac ON ac.id = r.category_id
            WHERE r.period_label = ?';
    $params = [$period];
    $types = 's';
    if ($categoryId > 0) {
        $sql .= ' AND r.category_id = ?';
        $params[] = $categoryId;
        $types .= 'i';
    }
    $sql .= ' ORDER BY ac.id ASC, r.rank_position ASC';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return fetchAll($stmt->get_result());
}

function getPlayerDashboardData($conn, $playerId)
{
    $stmt = $conn->prepare(
        'SELECT r.rank_position, r.ranking_average, ac.name AS category_name
         FROM rankings r JOIN age_categories ac ON ac.id = r.category_id
         WHERE r.player_id = ? AND r.period_label = (SELECT period_label FROM rankings ORDER BY calculated_at DESC LIMIT 1)
         ORDER BY ac.id'
    );
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $rankings = fetchAll($stmt->get_result());

    $stmt = $conn->prepare(
        'SELECT tr.id, t.name AS tournament_name, t.held_on, tr.points_awarded, tr.is_penalty
         FROM tournament_results tr JOIN tournaments t ON t.id = tr.tournament_id
         WHERE tr.player_id = ? ORDER BY t.held_on DESC LIMIT 4'
    );
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    return [
        'rankings' => $rankings,
        'results' => fetchAll($stmt->get_result()),
        'counting_ids' => getCountingResultIds($conn, $playerId),
    ];
}
?>
