<?php
declare(strict_types=1);

final class Security
{
    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(): void
    {
        $sent = (string)($_POST['_csrf'] ?? '');
        $known = (string)($_SESSION['_csrf'] ?? '');
        if ($sent === '' || $known === '' || !hash_equals($known, $sent)) {
            http_response_code(419);
            throw new RuntimeException('انتهت صلاحية النموذج. أعد تحميل الصفحة وحاول مرة أخرى.');
        }
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlashes(): array
    {
        $items = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($items) ? $items : [];
    }
}
