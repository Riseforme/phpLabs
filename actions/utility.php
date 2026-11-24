<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Starts a hardened PHP session when one is not already active.
 *
 * @return void
 */
function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Returns a PDO connection configured to report database errors.
 *
 * @return PDO Database connection.
 */
function getPDO(): PDO
{
    global $db_host, $db_port, $db_name, $db_user, $db_pass;

    return new PDO(
        "pgsql:host={$db_host};port={$db_port};dbname={$db_name}",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

/**
 * Sends a JSON response and terminates the request.
 *
 * @param array<string, mixed> $data Response data.
 * @param int $code HTTP status code.
 * @return never
 */
function returnJSON(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/**
 * Sends a consistently shaped JSON error response.
 *
 * @param string $message User-readable error.
 * @param int $code HTTP status code.
 * @return never
 */
function returnError(string $message = 'Произошла ошибка', int $code = 400): never
{
    returnJSON(['status' => 'error', 'message' => $message], $code);
}

/**
 * Requires the requested HTTP method.
 *
 * @param string $method Expected method.
 * @return void
 */
function requireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        returnError('Метод запроса не поддерживается', 405);
    }
}

/**
 * Reads a scalar request field without converting arrays into warning strings.
 *
 * @param array<string, mixed> $source Request values.
 * @param string $key Field name.
 * @return string Field value, or an empty string for unsupported types.
 */
function requestString(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) || is_numeric($value) ? (string) $value : '';
}

/**
 * Validates the CSRF token sent with a state-changing request.
 *
 * @return void
 */
function requireCsrfToken(): void
{
    startAppSession();
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!is_string($provided) || !hash_equals((string) $_SESSION['csrf_token'], $provided)) {
        returnError('Сессия устарела. Обновите страницу и повторите действие.', 419);
    }
}

/**
 * Creates a new authenticated session for a user.
 *
 * @param int $userId User identifier.
 * @param int $accessLevel User role level.
 * @return void
 */
function createSession(int $userId, int $accessLevel): void
{
    startAppSession();
    session_regenerate_id(true);
    unset($_SESSION['pending_game']);
    $_SESSION['user'] = ['id' => $userId, 'access_level' => $accessLevel];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Returns the authenticated user id or sends an authorization error.
 *
 * @return int Authenticated user identifier.
 */
function requireUserId(): int
{
    startAppSession();
    $userId = $_SESSION['user']['id'] ?? null;
    if (!is_int($userId) || $userId < 1) {
        returnError('Для продолжения войдите в аккаунт', 401);
    }
    return $userId;
}

/**
 * Requires an authenticated administrator.
 *
 * @return int Administrator user identifier.
 */
function requireAdminId(): int
{
    $userId = requireUserId();
    try {
        $statement = getPDO()->prepare('SELECT access_level FROM users WHERE id = :id');
        $statement->execute(['id' => $userId]);
        $accessLevel = $statement->fetchColumn();
    } catch (PDOException $exception) {
        error_log('administrator authorization: ' . $exception->getMessage());
        returnError('Не удалось проверить права доступа.', 500);
    }

    if ((int) $accessLevel !== 2) {
        unset($_SESSION['user']);
        returnError('Недостаточно прав для этого действия', 403);
    }
    $_SESSION['user']['access_level'] = 2;
    return $userId;
}

/**
 * Validates new-account fields and returns a user-readable validation error.
 *
 * @param string $name Display name.
 * @param string $email Email address.
 * @param string $phone Phone number.
 * @param string $password Plain-text password.
 * @return string|null Validation error, or null when valid.
 */
function validateRegistration(string $name, string $email, string $phone, string $password): ?string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (textLength($name) < 2 || textLength($name) > 50) {
        return 'Имя должно содержать от 2 до 50 символов.';
    }
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Укажите корректный email.';
    }
    if (!is_string($digits) || strlen($digits) < 10 || strlen($digits) > 15) {
        return 'Укажите корректный номер телефона (от 10 до 15 цифр).';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        return 'Пароль должен содержать от 8 до 72 символов.';
    }
    return null;
}

/**
 * Counts UTF-8 characters, falling back to bytes for malformed input.
 *
 * @param string $value Text to measure.
 * @return int Character count.
 */
function textLength(string $value): int
{
    $matched = preg_match_all('/./us', $value, $unused);
    return $matched === false ? strlen($value) : $matched;
}

/**
 * Converts a decimal monetary value into integer cents without float arithmetic.
 *
 * @param string $value User-provided decimal value.
 * @return int|null Amount in cents, or null for invalid input.
 */
function parseMoneyCents(string $value): ?int
{
    if (!preg_match('/^(?:0|[1-9]\d{0,9})(?:[.,]\d{1,2})?$/', trim($value))) {
        return null;
    }
    $normalized = str_replace(',', '.', trim($value));
    [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
    return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
}

/**
 * Formats integer cents as a fixed two-decimal PostgreSQL numeric value.
 *
 * @param int $cents Amount in cents.
 * @return string Decimal amount.
 */
function formatMoneyCents(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}
