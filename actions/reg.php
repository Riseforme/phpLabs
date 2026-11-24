<?php
declare(strict_types=1);

require_once __DIR__ . '/utility.php';

requireMethod('POST');
requireCsrfToken();

$name = trim(requestString($_POST, 'name'));
$email = strtolower(trim(requestString($_POST, 'email')));
$phone = trim(requestString($_POST, 'phone_number'));
$password = requestString($_POST, 'password');
$validationError = validateRegistration($name, $email, $phone, $password);
if ($validationError !== null) {
    returnError($validationError, 400);
}

try {
    $pdo = getPDO();
    $statement = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, balance, phone_number, access_level)
         VALUES (:name, :email, :password, 1000.00, :phone, 1)
         RETURNING id, access_level'
    );
    $statement->execute([
        'name' => $name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'phone' => preg_replace('/\D+/', '', $phone),
    ]);
    $user = $statement->fetch();
    createSession((int) $user['id'], (int) $user['access_level']);

    returnJSON([
        'status' => 'success',
        'message' => 'Регистрация прошла успешно!',
        'data' => [
            'id' => (int) $user['id'],
            'name' => $name,
            'email' => $email,
            'balance' => '1000.00',
            'phone_number' => preg_replace('/\D+/', '', $phone),
            'access_level' => 1,
        ],
        'csrf_token' => $_SESSION['csrf_token'],
    ], 201);
} catch (PDOException $exception) {
    error_log('registration: ' . $exception->getMessage());
    if (($exception->errorInfo[0] ?? '') === '23505') {
        returnError('Этот email уже зарегистрирован.', 409);
    }
    returnError('Не удалось создать аккаунт. Попробуйте позже.', 500);
}
