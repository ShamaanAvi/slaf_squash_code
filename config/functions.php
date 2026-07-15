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

function ensureUserSessionsTable($conn)
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS user_sessions (
            session_id VARCHAR(128) PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            last_activity INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_sessions_user_activity (user_id, last_activity),
            CONSTRAINT fk_user_sessions_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function destroyCurrentSession()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function redirectExpiredSession($reason = 'timeout')
{
    destroyCurrentSession();
    header('Location: ' . redirectPath('index.php?' . $reason . '=1'));
    exit;
}

function registerUserSession($conn, $userId, $sessionId, $maxSessions = 3, $timeoutDuration = 600)
{
    ensureUserSessionsTable($conn);
    $now = time();
    $cutoff = $now - $timeoutDuration;

    $stmt = $conn->prepare('DELETE FROM user_sessions WHERE last_activity < ?');
    $stmt->bind_param('i', $cutoff);
    $stmt->execute();

    $stmt = $conn->prepare(
        'INSERT INTO user_sessions (session_id, user_id, last_activity)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), last_activity = VALUES(last_activity)'
    );
    $stmt->bind_param('sii', $sessionId, $userId, $now);
    $stmt->execute();

    $stmt = $conn->prepare('SELECT session_id FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC, created_at DESC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $sessions = fetchAll($stmt->get_result());
    $expiredSessionIds = array_slice(array_column($sessions, 'session_id'), $maxSessions);

    if ($expiredSessionIds) {
        $placeholders = implode(',', array_fill(0, count($expiredSessionIds), '?'));
        $types = str_repeat('s', count($expiredSessionIds));
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id IN ($placeholders)");
        $stmt->bind_param($types, ...$expiredSessionIds);
        $stmt->execute();
    }
}

function enforceAuthenticatedSession($conn, $timeoutDuration = 600, $maxSessions = 3)
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user_id'])) {
        return;
    }

    $now = time();
    $sessionId = session_id();
    $userId = (int)$_SESSION['user_id'];

    if (isset($_SESSION['LAST_ACTIVITY']) && $now - (int)$_SESSION['LAST_ACTIVITY'] > $timeoutDuration) {
        ensureUserSessionsTable($conn);
        $stmt = $conn->prepare('DELETE FROM user_sessions WHERE session_id = ?');
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        redirectExpiredSession('timeout');
    }

    ensureUserSessionsTable($conn);
    $cutoff = $now - $timeoutDuration;
    $stmt = $conn->prepare('DELETE FROM user_sessions WHERE last_activity < ?');
    $stmt->bind_param('i', $cutoff);
    $stmt->execute();

    $stmt = $conn->prepare('SELECT user_id, last_activity FROM user_sessions WHERE session_id = ? LIMIT 1');
    $stmt->bind_param('s', $sessionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row && empty($_SESSION['MANAGED_SESSION'])) {
        $_SESSION['MANAGED_SESSION'] = 1;
        $_SESSION['LAST_ACTIVITY'] = $now;
        registerUserSession($conn, $userId, $sessionId, $maxSessions, $timeoutDuration);
        return;
    }

    if (!$row || (int)$row['user_id'] !== $userId) {
        redirectExpiredSession('session_limit');
    }
    if ($now - (int)$row['last_activity'] > $timeoutDuration) {
        $stmt = $conn->prepare('DELETE FROM user_sessions WHERE session_id = ?');
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        redirectExpiredSession('timeout');
    }

    $_SESSION['LAST_ACTIVITY'] = $now;
    $stmt = $conn->prepare('UPDATE user_sessions SET last_activity = ? WHERE session_id = ?');
    $stmt->bind_param('is', $now, $sessionId);
    $stmt->execute();
    registerUserSession($conn, $userId, $sessionId, $maxSessions, $timeoutDuration);
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
    $categoryOrder = [
        'Boys U9', 'Girls U9',
        'Boys U11', 'Girls U11',
        'Boys U 11 Novice', 'Girls U 11 Novice',
        'Boys U13', 'Girls U13',
        'Boys U15', 'Girls U15',
        'Boys U 15 Novice', 'Girls U 15 Novice',
        'Boys U17', 'Girls U17',
        'Boys U19', 'Girls U19',
        "Men's Open", "Women's Open",
        "Women's Over 35",
        "Men's Over 35", "Men's Over 40", "Men's Over 45",
        "Men's Masters Over 50", "Men's Masters Over 55", "Men's Masters Over 60", "Men's Masters Over 65",
    ];
    $quotedOrder = array_map(function ($category) use ($conn) {
        return "'" . $conn->real_escape_string($category) . "'";
    }, $categoryOrder);
    $result = $conn->query(
        'SELECT id, name, gender FROM age_categories ORDER BY FIELD(name, ' . implode(',', $quotedOrder) . ') = 0, FIELD(name, ' . implode(',', $quotedOrder) . '), id ASC'
    );
    return fetchAll($result);
}

function getCategoryOrderMap()
{
    return [
        'Male' => [
            'Boys U9', 'Boys U11', 'Boys U 11 Novice', 'Boys U13', 'Boys U15', 'Boys U 15 Novice', 'Boys U17', 'Boys U19',
            "Men's Open", "Men's Over 35", "Men's Over 40", "Men's Over 45",
            "Men's Masters Over 50", "Men's Masters Over 55", "Men's Masters Over 60", "Men's Masters Over 65",
        ],
        'Female' => [
            'Girls U9', 'Girls U11', 'Girls U 11 Novice', 'Girls U13', 'Girls U15', 'Girls U 15 Novice', 'Girls U17', 'Girls U19',
            "Women's Open", "Women's Over 35",
        ],
    ];
}

function getCategoryRank($categoryName, $gender)
{
    $map = getCategoryOrderMap();
    $list = $map[$gender] ?? [];
    $rank = array_search($categoryName, $list, true);
    return $rank === false ? null : $rank;
}

function isPlayerEligibleForTournamentCategory($playerCategoryName, $playerGender, $tournamentCategoryName, $tournamentGender)
{
    if ($playerGender !== $tournamentGender) {
        return false;
    }
    $playerRank = getCategoryRank($playerCategoryName, $playerGender);
    $tournamentRank = getCategoryRank($tournamentCategoryName, $tournamentGender);
    if ($playerRank === null || $tournamentRank === null) {
        return false;
    }
    return $tournamentRank >= $playerRank;
}

function tableColumnExists($conn, $table, $column)
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS count
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['count'] ?? 0) > 0;
}

function generatePlayerUsername($conn, $name)
{
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '.', trim($name)));
    $base = trim($base, '.');
    if ($base === '') {
        $base = 'player';
    }

    for ($i = 0; $i < 20; $i++) {
        $suffix = $i === 0 ? random_int(1000, 9999) : random_int(10000, 99999);
        $username = substr($base, 0, 50) . '.' . $suffix;

        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            return $username;
        }
    }

    throw new Exception('Could not generate a unique player username.');
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

function ensurePlayerCalculatedCategoriesCurrent($conn)
{
    $currentYear = (string)date('Y');
    if (getSystemSetting($conn, 'player_categories_refreshed_year', '') === $currentYear) {
        return false;
    }

    refreshAllPlayerCalculatedCategories($conn, (int)$currentYear);
    setSystemSetting($conn, 'player_categories_refreshed_year', $currentYear);
    return true;
}

function getPlayers($conn)
{
    ensurePlayerCalculatedCategoriesCurrent($conn);

    $hasIdentityType = tableColumnExists($conn, 'players', 'identity_type');
    $hasPassportExpiry = tableColumnExists($conn, 'players', 'passport_expiry_date');
    $identitySelect = $hasIdentityType ? 'identity_type' : "'NIC' AS identity_type";
    $expirySelect = $hasPassportExpiry ? 'passport_expiry_date' : 'NULL AS passport_expiry_date';
    $result = $conn->query("SELECT id, full_name, {$identitySelect}, nic, {$expirySelect} FROM players ORDER BY full_name ASC");
    $players = fetchAll($result);

    foreach ($players as &$player) {
        $player['passport_status'] = getPassportExpiryStatus($player['identity_type'] ?? 'NIC', $player['passport_expiry_date'] ?? null);
        $player['passport_status_label'] = getPassportExpiryLabel($player['passport_status'], $player['passport_expiry_date'] ?? null);
    }
    unset($player);

    return $players;
}

function getPlayerList($conn, $search = '')
{
    ensurePlayerCalculatedCategoriesCurrent($conn);

    $sql = 'SELECT p.*, u.username, ac.name AS calculated_category_name
            FROM players p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN age_categories ac ON ac.id = p.calculated_category_id
            WHERE 1=1';
    $params = [];
    $types = '';
    $search = trim($search);
    if ($search !== '') {
        $sql .= ' AND (p.full_name LIKE ? OR p.nic LIKE ? OR p.phone LIKE ? OR p.email LIKE ? OR u.username LIKE ?)';
        $term = '%' . $search . '%';
        $params = [$term, $term, $term, $term, $term];
        $types = 'sssss';
    }
    $sql .= ' ORDER BY p.full_name ASC';
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $players = fetchAll($stmt->get_result());
    foreach ($players as &$player) {
        $player['passport_status'] = getPassportExpiryStatus($player['identity_type'] ?? 'NIC', $player['passport_expiry_date'] ?? null);
        $player['passport_status_label'] = getPassportExpiryLabel($player['passport_status'], $player['passport_expiry_date'] ?? null);
    }
    unset($player);
    return $players;
}

function getPlayerAdminById($conn, $playerId)
{
    refreshPlayerCalculatedCategory($conn, $playerId);

    $stmt = $conn->prepare(
        'SELECT p.*, u.username, u.is_active, ac.name AS calculated_category_name
         FROM players p
         JOIN users u ON u.id = p.user_id
         LEFT JOIN age_categories ac ON ac.id = p.calculated_category_id
         WHERE p.id = ?'
    );
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getPassportExpiryStatus($identityType, $expiryDate, $warningDays = 90)
{
    if ($identityType !== 'Passport') {
        return 'not_passport';
    }
    if (!$expiryDate) {
        return 'unknown';
    }

    $expiry = DateTime::createFromFormat('Y-m-d', (string)$expiryDate);
    $errors = DateTime::getLastErrors();
    if (!$expiry || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return 'unknown';
    }

    $today = new DateTime('today');
    if ($expiry < $today) {
        return 'expired';
    }

    $warningLimit = (clone $today)->modify('+' . (int)$warningDays . ' days');
    return $expiry <= $warningLimit ? 'expiring' : 'valid';
}

function getPassportExpiryLabel($status, $expiryDate)
{
    if ($status === 'not_passport') {
        return 'NIC document';
    }
    if ($status === 'unknown') {
        return 'Passport expiry date not recorded';
    }

    $date = $expiryDate ? date('d M Y', strtotime($expiryDate)) : '';
    if ($status === 'expired') {
        return 'Passport expired on ' . $date;
    }
    if ($status === 'expiring') {
        return 'Passport expires soon on ' . $date;
    }
    return 'Passport valid until ' . $date;
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
    $identityType = trim($data['identity_type'] ?? '');
    $nic = trim($data['nic'] ?? '');
    $passportExpiryDate = trim($data['passport_expiry_date'] ?? '');
    $dob = $data['dob'] ?? null;
    $gender = ucfirst(strtolower(trim($data['gender'] ?? '')));
    $address = trim($data['address'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');

    if (!in_array($identityType, ['NIC', 'Passport'], true)) {
        throw new Exception('Please select NIC or Passport.');
    }
    if ($identityType !== 'Passport') {
        $passportExpiryDate = null;
    } elseif ($passportExpiryDate !== '') {
        $expiry = DateTime::createFromFormat('Y-m-d', $passportExpiryDate);
        $errors = DateTime::getLastErrors();
        if (!$expiry || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new Exception('Please enter a valid passport expiry date.');
        }
    } else {
        $passportExpiryDate = null;
    }
    if ($name === '' || $dob === '') {
        throw new Exception('Full name and date of birth are required.');
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

    $username = $nic !== '' ? $nic : generatePlayerUsername($conn, $name);
    $identityNumber = $nic !== '' ? $nic : null;
    $passwordHash = password_hash($username, PASSWORD_BCRYPT);
    $role = 'player';

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('INSERT INTO users (username, password, role, is_first_login) VALUES (?, ?, ?, 1)');
        $stmt->bind_param('sss', $username, $passwordHash, $role);
        $stmt->execute();
        $userId = $conn->insert_id;

        $hasIdentityType = tableColumnExists($conn, 'players', 'identity_type');
        $hasPassportExpiry = tableColumnExists($conn, 'players', 'passport_expiry_date');
        if ($hasIdentityType && $hasPassportExpiry) {
            $stmt = $conn->prepare(
                'INSERT INTO players (user_id, full_name, identity_type, nic, passport_expiry_date, dob, date_of_birth, gender, calculated_category_id, address, phone, email)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('isssssssisss', $userId, $name, $identityType, $identityNumber, $passportExpiryDate, $dob, $dob, $gender, $primaryCategoryId, $address, $phone, $email);
        } elseif ($hasIdentityType) {
            $stmt = $conn->prepare(
                'INSERT INTO players (user_id, full_name, identity_type, nic, dob, date_of_birth, gender, calculated_category_id, address, phone, email)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('issssssisss', $userId, $name, $identityType, $identityNumber, $dob, $dob, $gender, $primaryCategoryId, $address, $phone, $email);
        } elseif ($hasPassportExpiry) {
            $stmt = $conn->prepare(
                'INSERT INTO players (user_id, full_name, nic, passport_expiry_date, dob, date_of_birth, gender, calculated_category_id, address, phone, email)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('issssssisss', $userId, $name, $identityNumber, $passportExpiryDate, $dob, $dob, $gender, $primaryCategoryId, $address, $phone, $email);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO players (user_id, full_name, nic, dob, date_of_birth, gender, calculated_category_id, address, phone, email)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('isssssisss', $userId, $name, $identityNumber, $dob, $dob, $gender, $primaryCategoryId, $address, $phone, $email);
        }
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
        return $username;
    } catch (Exception $e) {
        $conn->rollback();
        if ((int)$e->getCode() === 1062) {
            throw new Exception('A player with this NIC/Passport already exists.');
        }
        error_log('Register player failed [' . $e->getCode() . ']: ' . $e->getMessage());
        throw new Exception('Could not register player.');
    }
}

function getPlayerProfile($conn, $playerId)
{
    refreshPlayerCalculatedCategory($conn, $playerId);

    $stmt = $conn->prepare(
        'SELECT p.*, u.username, u.is_first_login
         FROM players p JOIN users u ON p.user_id = u.id
         WHERE p.id = ?'
    );
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getUserAccount($conn, $userId)
{
    $stmt = $conn->prepare('SELECT id, username, role FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function updateUserPassword($conn, $userId, $data)
{
    $password = $data['password'] ?? '';
    $confirm = $data['confirm_password'] ?? '';

    if ($password === '') {
        throw new Exception('Please enter a new password.');
    }
    if ($password !== $confirm) {
        throw new Exception('Passwords do not match.');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->bind_param('si', $hash, $userId);
    $stmt->execute();
    logAudit($conn, 'profile_updated', 'users', $userId, 'Password updated');
    return true;
}

function updateAdminPlayer($conn, $playerId, $data)
{
    $name = trim($data['name'] ?? '');
    $identityType = trim($data['identity_type'] ?? '');
    $identityNumber = trim($data['nic'] ?? '');
    $passportExpiryDate = trim($data['passport_expiry_date'] ?? '');
    $dob = trim($data['dob'] ?? '');
    $gender = normalizePlayerGender($data['gender'] ?? '');
    $address = trim($data['address'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');
    $psa = trim($data['psa_wsf_ranking'] ?? '');

    if ($name === '' || $dob === '' || $gender === '') {
        throw new Exception('Full name, date of birth, and gender are required.');
    }
    if (!in_array($identityType, ['NIC', 'Passport'], true)) {
        throw new Exception('Please select NIC or Passport.');
    }
    if ($identityType !== 'Passport') {
        $passportExpiryDate = null;
    } elseif ($passportExpiryDate === '') {
        $passportExpiryDate = null;
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address.');
    }
    $psaValue = $psa === '' ? null : (int)$psa;

    $categoryName = getAgeCategoryNameForPlayer($dob, $gender);
    $categoryId = getAgeCategoryIdByName($conn, $categoryName);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'UPDATE players
             SET full_name = ?, identity_type = ?, nic = ?, passport_expiry_date = ?, dob = ?, date_of_birth = ?,
                 gender = ?, calculated_category_id = ?, address = ?, phone = ?, email = ?, psa_wsf_ranking = ?
             WHERE id = ?'
        );
        $stmt->bind_param(
            'sssssssisssii',
            $name,
            $identityType,
            $identityNumber,
            $passportExpiryDate,
            $dob,
            $dob,
            $gender,
            $categoryId,
            $address,
            $phone,
            $email,
            $psaValue,
            $playerId
        );
        $stmt->execute();

        $delete = $conn->prepare('DELETE FROM player_categories WHERE player_id = ?');
        $delete->bind_param('i', $playerId);
        $delete->execute();
        $insert = $conn->prepare('INSERT INTO player_categories (player_id, category_id) VALUES (?, ?)');
        $insert->bind_param('ii', $playerId, $categoryId);
        $insert->execute();

        logAudit($conn, 'player_updated', 'players', $playerId, $name);
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        if ((int)$e->getCode() === 1062) {
            throw new Exception('Another player already uses this document number.');
        }
        error_log('Admin player update failed: ' . $e->getMessage());
        throw new Exception('Could not update player.');
    }
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
    $heldOn = $data['held_on'] ?? '';
    $endOn = trim($data['end_on'] ?? '');
    $categoryIds = $data['category_ids'] ?? ($data['category_id'] ?? []);
    if (!is_array($categoryIds)) {
        $categoryIds = [$categoryIds];
    }
    $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));

    if ($name === '' || !in_array($tier, ['A', 'B'], true) || $heldOn === '' || $endOn === '' || !$categoryIds) {
        throw new Exception('Please complete all tournament fields.');
    }
    if ($endOn !== '' && $endOn < $heldOn) {
        throw new Exception('Ending date cannot be before the starting date.');
    }

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $types = str_repeat('i', count($categoryIds));
    $stmt = $conn->prepare("SELECT id FROM age_categories WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$categoryIds);
    $stmt->execute();
    if ($stmt->get_result()->num_rows !== count($categoryIds)) {
        throw new Exception('One or more selected age categories are invalid.');
    }

    $hasEndOn = tableColumnExists($conn, 'tournaments', 'end_on');
    $conn->begin_transaction();
    try {
        if ($hasEndOn) {
            $stmt = $conn->prepare(
                'INSERT INTO tournaments (name, tournament_name, tier, held_on, end_on, category_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO tournaments (name, tournament_name, tier, held_on, category_id)
                 VALUES (?, ?, ?, ?, ?)'
            );
        }

        $createdIds = [];
        foreach ($categoryIds as $categoryId) {
            if ($hasEndOn) {
                $stmt->bind_param('sssssi', $name, $name, $tier, $heldOn, $endOn, $categoryId);
            } else {
                $stmt->bind_param('ssssi', $name, $name, $tier, $heldOn, $categoryId);
            }
            $stmt->execute();
            $createdIds[] = $conn->insert_id;
        }

        logAudit($conn, 'tournament_created', 'tournaments', $createdIds[0] ?? null, $name . ' (' . count($createdIds) . ' categories)');
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Tournament creation failed: ' . $e->getMessage());
        throw new Exception('Could not create tournament.');
    }
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

function getAssignedPlayersWithoutResult($conn, $tournamentId)
{
    ensurePlayerCalculatedCategoriesCurrent($conn);

    $stmt = $conn->prepare(
        'SELECT p.id, p.full_name, ac.name AS calculated_category_name
         FROM player_tournaments pt
         JOIN players p ON p.id = pt.player_id
         LEFT JOIN age_categories ac ON ac.id = p.calculated_category_id
         LEFT JOIN tournament_results tr ON (tr.tournament_id = pt.tournament_id AND tr.player_id = pt.player_id)
         WHERE pt.tournament_id = ? AND tr.id IS NULL
         ORDER BY p.full_name ASC'
    );
    $stmt->bind_param('i', $tournamentId);
    $stmt->execute();
    return fetchAll($stmt->get_result());
}

function updateTournament($conn, $id, $data)
{
    $name = trim($data['name'] ?? '');
    $tier = strtoupper($data['tier'] ?? 'B');
    $drawSize = (int)($data['draw_size'] ?? 0);
    $heldOn = $data['held_on'] ?? '';
    $endOn = trim($data['end_on'] ?? '');
    $categoryId = (int)($data['category_id'] ?? 0);
    if ($name === '' || !in_array($tier, ['A', 'B'], true) || $drawSize < 0 || $heldOn === '' || $endOn === '' || $categoryId <= 0) {
        throw new Exception('Please complete all tournament fields.');
    }
    if ($endOn !== '' && $endOn < $heldOn) {
        throw new Exception('Ending date cannot be before the starting date.');
    }
    if (tableColumnExists($conn, 'tournaments', 'end_on')) {
        $stmt = $conn->prepare(
            'UPDATE tournaments SET name = ?, tournament_name = ?, tier = ?, draw_size = ?, held_on = ?, end_on = ?, category_id = ? WHERE id = ?'
        );
        $stmt->bind_param('sssissii', $name, $name, $tier, $drawSize, $heldOn, $endOn, $categoryId, $id);
    } else {
        $stmt = $conn->prepare(
            'UPDATE tournaments SET name = ?, tournament_name = ?, tier = ?, draw_size = ?, held_on = ?, category_id = ? WHERE id = ?'
        );
        $stmt->bind_param('sssisii', $name, $name, $tier, $drawSize, $heldOn, $categoryId, $id);
    }
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

function getTournamentDetails($conn, $id)
{
    $stmt = $conn->prepare(
        'SELECT t.*, COALESCE(t.name, t.tournament_name) AS display_name, ac.name AS category_name, ac.gender AS category_gender
         FROM tournaments t
         LEFT JOIN age_categories ac ON ac.id = t.category_id
         WHERE t.id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getTournamentAssignedPlayers($conn, $tournamentId)
{
    ensurePlayerCalculatedCategoriesCurrent($conn);

    $stmt = $conn->prepare(
        'SELECT pt.assigned_at, p.id AS player_id, p.full_name, p.identity_type, p.nic, p.passport_expiry_date,
                COALESCE(p.date_of_birth, p.dob) AS dob, p.gender, p.phone, p.email,
                ac.name AS calculated_category_name,
                tr.finish_position, tr.points_awarded, tr.is_penalty
         FROM player_tournaments pt
         JOIN players p ON p.id = pt.player_id
         LEFT JOIN age_categories ac ON ac.id = p.calculated_category_id
         LEFT JOIN tournament_results tr ON tr.tournament_id = pt.tournament_id AND tr.player_id = pt.player_id
         WHERE pt.tournament_id = ?
         ORDER BY p.full_name ASC'
    );
    $stmt->bind_param('i', $tournamentId);
    $stmt->execute();
    $rows = fetchAll($stmt->get_result());
    foreach ($rows as &$row) {
        $row['passport_status'] = getPassportExpiryStatus($row['identity_type'] ?? 'NIC', $row['passport_expiry_date'] ?? null);
        $row['passport_status_label'] = getPassportExpiryLabel($row['passport_status'], $row['passport_expiry_date'] ?? null);
    }
    unset($row);
    return $rows;
}

function getEligiblePlayersForTournament($conn, $tournamentId)
{
    ensurePlayerCalculatedCategoriesCurrent($conn);

    $tournament = getTournamentDetails($conn, $tournamentId);
    if (!$tournament) {
        return [];
    }

    $players = getPlayerList($conn);
    $eligible = [];
    foreach ($players as $player) {
        if (empty($player['calculated_category_name']) || empty($player['gender'])) {
            continue;
        }
        if (
            isPlayerEligibleForTournamentCategory(
                $player['calculated_category_name'],
                $player['gender'],
                $tournament['category_name'],
                $tournament['category_gender']
            )
        ) {
            $eligible[] = $player;
        }
    }
    return $eligible;
}

function assignPlayerToTournament($conn, $playerId, $tournamentId)
{
    refreshPlayerCalculatedCategory($conn, $playerId);

    $hasIdentityType = tableColumnExists($conn, 'players', 'identity_type');
    $hasPassportExpiry = tableColumnExists($conn, 'players', 'passport_expiry_date');
    $identitySelect = $hasIdentityType ? 'p.identity_type' : "'NIC' AS identity_type";
    $expirySelect = $hasPassportExpiry ? 'passport_expiry_date' : 'NULL AS passport_expiry_date';
    $stmt = $conn->prepare(
        "SELECT {$identitySelect}, {$expirySelect}, p.gender, ac.name AS calculated_category_name
         FROM players p
         LEFT JOIN age_categories ac ON ac.id = p.calculated_category_id
         WHERE p.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    if (!$player) {
        throw new Exception('Player not found.');
    }

    $dateSelect = tableColumnExists($conn, 'tournaments', 'end_on') ? 't.category_id, t.held_on, t.end_on' : 't.category_id, t.held_on, NULL AS end_on';
    $stmt = $conn->prepare(
        "SELECT {$dateSelect}, ac.name AS category_name, ac.gender AS category_gender
         FROM tournaments t
         LEFT JOIN age_categories ac ON ac.id = t.category_id
         WHERE t.id = ?"
    );
    $stmt->bind_param('i', $tournamentId);
    $stmt->execute();
    $tournament = $stmt->get_result()->fetch_assoc();
    if (!$tournament) {
        throw new Exception('Tournament not found.');
    }

    $passportStatus = getPassportExpiryStatus($player['identity_type'] ?? 'NIC', $player['passport_expiry_date'] ?? null);
    if ($passportStatus === 'expired') {
        throw new Exception(getPassportExpiryLabel($passportStatus, $player['passport_expiry_date']));
    }
    $tournamentCheckDate = $tournament['end_on'] ?: $tournament['held_on'];
    if (($player['identity_type'] ?? '') === 'Passport' && !empty($player['passport_expiry_date']) && !empty($tournamentCheckDate)) {
        $expiry = DateTime::createFromFormat('Y-m-d', $player['passport_expiry_date']);
        $tournamentDate = DateTime::createFromFormat('Y-m-d', $tournamentCheckDate);
        if ($expiry && $tournamentDate && $expiry < $tournamentDate) {
            throw new Exception('Passport expires before the selected tournament ends.');
        }
    }

    $categoryId = (int)$tournament['category_id'];
    if (
        !isPlayerEligibleForTournamentCategory(
            $player['calculated_category_name'] ?? '',
            $player['gender'] ?? '',
            $tournament['category_name'] ?? '',
            $tournament['category_gender'] ?? ''
        )
    ) {
        throw new Exception('This player is not eligible for the selected tournament category.');
    }

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

function getScoresList($conn, $playerId = null, $filters = [])
{
    $sql = 'SELECT tr.id, p.full_name, t.name AS tournament_name, ac.name AS category,
                   t.held_on, t.tier, tr.finish_position, tr.points_awarded, tr.is_penalty
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
    }

    $searchTerm = trim($filters['search'] ?? '');
    if (!$playerId && $searchTerm !== '') {
        $sql .= ' AND p.full_name LIKE ?';
        $params[] = '%' . $searchTerm . '%';
        $types .= 's';
    }
    if (!$playerId && !empty($filters['tournament_id'])) {
        $sql .= ' AND t.id = ?';
        $params[] = (int)$filters['tournament_id'];
        $types .= 'i';
    }
    if (!$playerId && !empty($filters['category_id'])) {
        $sql .= ' AND t.category_id = ?';
        $params[] = (int)$filters['category_id'];
        $types .= 'i';
    }
    if (!$playerId && in_array(($filters['tier'] ?? ''), ['A', 'B'], true)) {
        $sql .= ' AND t.tier = ?';
        $params[] = $filters['tier'];
        $types .= 's';
    }
    if (!$playerId && !empty($filters['date_from'])) {
        $sql .= ' AND t.held_on >= ?';
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    if (!$playerId && !empty($filters['date_to'])) {
        $sql .= ' AND t.held_on <= ?';
        $params[] = $filters['date_to'];
        $types .= 's';
    }
    if (!$playerId && isset($filters['finish_position']) && $filters['finish_position'] !== '') {
        $sql .= ' AND tr.finish_position = ?';
        $params[] = (int)$filters['finish_position'];
        $types .= 'i';
    }
    if (!$playerId && isset($filters['penalty']) && $filters['penalty'] !== '') {
        $sql .= ' AND tr.is_penalty = ?';
        $params[] = (int)$filters['penalty'];
        $types .= 'i';
    }
    if (!$playerId && isset($filters['points_min']) && $filters['points_min'] !== '') {
        $sql .= ' AND tr.points_awarded >= ?';
        $params[] = (float)$filters['points_min'];
        $types .= 'd';
    }
    if (!$playerId && isset($filters['points_max']) && $filters['points_max'] !== '') {
        $sql .= ' AND tr.points_awarded <= ?';
        $params[] = (float)$filters['points_max'];
        $types .= 'd';
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
