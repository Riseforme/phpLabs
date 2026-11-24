CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    balance NUMERIC(12, 2) NOT NULL DEFAULT 1000.00 CHECK (balance >= 0),
    phone_number VARCHAR(15) NOT NULL,
    access_level SMALLINT NOT NULL DEFAULT 1 CHECK (access_level IN (1, 2)),
    reg_date TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS users_email_lower_unique_idx
    ON users (lower(email));
CREATE INDEX IF NOT EXISTS users_phone_digits_idx
    ON users (regexp_replace(phone_number, '[^0-9]', '', 'g'));

CREATE TABLE IF NOT EXISTS public_content (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    body TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS game_rounds (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    bet NUMERIC(12, 2) NOT NULL CHECK (bet > 0),
    chance NUMERIC(5, 2) NOT NULL CHECK (chance > 0 AND chance < 100),
    direction VARCHAR(4) NOT NULL CHECK (direction IN ('less', 'more')),
    roll_value INTEGER NOT NULL CHECK (roll_value BETWEEN 1 AND 999999),
    won BOOLEAN NOT NULL,
    payout NUMERIC(12, 2) NOT NULL CHECK (payout >= 0),
    salt VARCHAR(64) NOT NULL,
    commitment CHAR(128) NOT NULL UNIQUE,
    balance_after NUMERIC(12, 2) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS game_rounds_user_created_idx
    ON game_rounds (user_id, created_at DESC);

INSERT INTO public_content (title, body, sort_order)
SELECT seed.title, seed.body, seed.sort_order
FROM (VALUES
    (
        'Проверяемая честность',
        'Перед каждой ставкой сервер публикует SHA-512 обязательство. После раунда можно пересчитать хеш раскрытых числа и соли.',
        1
    ),
    (
        'Играйте ответственно',
        'Ставка списывается до определения результата. Размер ставки не может превышать доступный баланс; пополнение в приложении не предусмотрено.',
        2
    ),
    (
        'Простые правила',
        'Выберите шанс и сторону числа. Чем выше шанс, тем ниже коэффициент выплаты; при выигрыше выплачивается ставка с коэффициентом.',
        3
    )
) AS seed(title, body, sort_order)
WHERE NOT EXISTS (SELECT 1 FROM public_content);
