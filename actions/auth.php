<?php
declare(strict_types=1);

require_once __DIR__ . '/utility.php';

requireMethod('POST');
requireCsrfToken();

$email = strtolower(trim(requestString($_POST, 'email')));
$password = requestString($_POST, 'password');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 72) {
    returnError('Проверьте email и пароль.', 400);
}

try {
    $pdo = getPDO();
    $statement = $pdo->prepare(
        'SELECT id, name, email, password_hash, balance, access_level
         FROM users WHERE email = :email'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        returnError('Неверный email или пароль.', 401);
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = $pdo->prepare('UPDATE users SET password_hash = :password WHERE id = :id');
        $rehash->execute([
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $user['id'],
        ]);
    }

    createSession((int) $user['id'], (int) $user['access_level']);
    unset($user['password_hash']);
    $user['id'] = (int) $user['id'];
    $user['access_level'] = (int) $user['access_level'];
    returnJSON([
        'status' => 'success',
        'message' => 'Вы вошли в аккаунт.',
        'data' => $user,
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
} catch (PDOException $exception) {
    error_log('authentication: ' . $exception->getMessage());
    returnError('Не удалось выполнить вход. Попробуйте позже.', 500);
}
