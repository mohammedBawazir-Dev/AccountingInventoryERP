<?php
declare(strict_types=1);

final class Auth
{
    public const DOCTOR_EMAIL = 'labeeb78@gmail.com';
    public static function ensureSchema(PDO $db): void
    {
        $db->exec(<<<'SQL'
IF OBJECT_ID(N'dbo.AppUsers', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AppUsers
    (
        UserID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppUsers PRIMARY KEY,
        FullName NVARCHAR(150) NOT NULL,
        Email NVARCHAR(254) NOT NULL,
        PasswordHash NVARCHAR(255) NOT NULL,
        RoleName NVARCHAR(30) NOT NULL CONSTRAINT DF_AppUsers_RoleName DEFAULT N'custom',
        IsActive BIT NOT NULL CONSTRAINT DF_AppUsers_IsActive DEFAULT (1),
        IsSuperAdmin BIT NOT NULL CONSTRAINT DF_AppUsers_IsSuperAdmin DEFAULT (0),
        MustChangePassword BIT NOT NULL CONSTRAINT DF_AppUsers_MustChangePassword DEFAULT (1),
        AuthVersion INT NOT NULL CONSTRAINT DF_AppUsers_AuthVersion DEFAULT (2),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_AppUsers_CreatedAt DEFAULT SYSDATETIME(),
        LastLoginAt DATETIME2(0) NULL
    );
    CREATE UNIQUE INDEX UX_AppUsers_Email ON dbo.AppUsers(Email);
END;

IF COL_LENGTH(N'dbo.AppUsers', N'IsSuperAdmin') IS NULL
    ALTER TABLE dbo.AppUsers ADD IsSuperAdmin BIT NOT NULL CONSTRAINT DF_AppUsers_IsSuperAdmin_Migration DEFAULT (0) WITH VALUES;
IF COL_LENGTH(N'dbo.AppUsers', N'MustChangePassword') IS NULL
    ALTER TABLE dbo.AppUsers ADD MustChangePassword BIT NOT NULL CONSTRAINT DF_AppUsers_MustChangePassword_Migration DEFAULT (1) WITH VALUES;
IF COL_LENGTH(N'dbo.AppUsers', N'AuthVersion') IS NULL
    ALTER TABLE dbo.AppUsers ADD AuthVersion INT NOT NULL CONSTRAINT DF_AppUsers_AuthVersion_Migration DEFAULT (0) WITH VALUES;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.AppUsers') AND name = N'UX_AppUsers_Email')
    CREATE UNIQUE INDEX UX_AppUsers_Email ON dbo.AppUsers(Email);

IF OBJECT_ID(N'dbo.AppUserPermissions', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AppUserPermissions
    (
        UserID INT NOT NULL,
        ModuleKey NVARCHAR(100) NOT NULL,
        CanView BIT NOT NULL CONSTRAINT DF_AppUserPermissions_CanView DEFAULT (0),
        CanCreate BIT NOT NULL CONSTRAINT DF_AppUserPermissions_CanCreate DEFAULT (0),
        CanEdit BIT NOT NULL CONSTRAINT DF_AppUserPermissions_CanEdit DEFAULT (0),
        CanDelete BIT NOT NULL CONSTRAINT DF_AppUserPermissions_CanDelete DEFAULT (0),
        CONSTRAINT PK_AppUserPermissions PRIMARY KEY (UserID, ModuleKey),
        CONSTRAINT FK_AppUserPermissions_AppUsers FOREIGN KEY (UserID)
            REFERENCES dbo.AppUsers(UserID) ON DELETE CASCADE
    );
END;
SQL);
    }

    public static function ensureDoctorAccount(PDO $db): void
    {
        $stmt = $db->prepare('SELECT UserID FROM dbo.AppUsers WHERE LOWER(Email) = LOWER(?)');
        $stmt->execute([self::DOCTOR_EMAIL]);
        $userId = $stmt->fetchColumn();

        if ($userId === false) {
            // PasswordHash remains populated for schema compatibility, but is never used for the doctor's login.
            $unusedHash = password_hash(bin2hex(random_bytes(48)), PASSWORD_DEFAULT);
            if ($unusedHash === false) throw new RuntimeException('تعذر تهيئة حساب المدير.');
            $insert = $db->prepare(
                "INSERT INTO dbo.AppUsers (FullName, Email, PasswordHash, RoleName, IsActive, IsSuperAdmin, MustChangePassword, AuthVersion)
                 VALUES (?, ?, ?, N'admin', 1, 1, 0, 2)"
            );
            $insert->execute(['مدير النظام', self::DOCTOR_EMAIL, $unusedHash]);
            return;
        }

        // The designated doctor account is always the protected super administrator and never requires a password.
        $update = $db->prepare(
            "UPDATE dbo.AppUsers
             SET IsSuperAdmin = 1, IsActive = 1, RoleName = N'admin',
                 MustChangePassword = 0, AuthVersion = 2
             WHERE UserID = ?"
        );
        $update->execute([(int)$userId]);
    }

    public static function login(PDO $db, string $email, string $password = ''): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $isDoctorAccount = $normalizedEmail === strtolower(self::DOCTOR_EMAIL);

        $stmt = $db->prepare(
            'SELECT TOP (1) UserID, FullName, Email, PasswordHash, RoleName, IsActive, IsSuperAdmin, MustChangePassword, AuthVersion
             FROM dbo.AppUsers WHERE LOWER(Email) = LOWER(?)'
        );
        $stmt->execute([trim($email)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || (int)$user['IsActive'] !== 1) {
            return false;
        }

        if ($isDoctorAccount) {
            if ((int)$user['IsSuperAdmin'] !== 1) return false;
            $user['MustChangePassword'] = 0;
        } elseif (!password_verify($password, (string)$user['PasswordHash'])) {
            return false;
        }

        $userId = (int)$user['UserID'];
        $update = $db->prepare('UPDATE dbo.AppUsers SET LastLoginAt = SYSDATETIME() WHERE UserID = ?');
        $update->execute([$userId]);

        unset($user['PasswordHash'], $user['IsActive']);
        $user['UserID'] = $userId;
        $user['IsSuperAdmin'] = (int)$user['IsSuperAdmin'];
        $user['MustChangePassword'] = (int)$user['MustChangePassword'];
        $user['AuthVersion'] = (int)$user['AuthVersion'];

        self::startSession();
        session_regenerate_id(true);
        $_SESSION['auth_user'] = $user;
        $_SESSION['auth_permissions'] = self::loadPermissions($db, $userId);
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        return true;
    }

    public static function refreshSession(PDO $db): bool
    {
        $current = self::currentUser();
        if ($current === null) return false;
        $stmt = $db->prepare('SELECT UserID, FullName, Email, RoleName, IsActive, IsSuperAdmin, MustChangePassword, AuthVersion FROM dbo.AppUsers WHERE UserID = ?');
        $stmt->execute([(int)$current['UserID']]);
        $fresh = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fresh || (int)$fresh['IsActive'] !== 1 || (int)$fresh['AuthVersion'] !== (int)($current['AuthVersion'] ?? -1)) {
            self::logout();
            return false;
        }
        $fresh['UserID'] = (int)$fresh['UserID'];
        $fresh['AuthVersion'] = (int)$fresh['AuthVersion'];
        $fresh['IsSuperAdmin'] = (int)$fresh['IsSuperAdmin'];
        $fresh['MustChangePassword'] = (int)$fresh['MustChangePassword'];
        unset($fresh['IsActive']);
        $_SESSION['auth_user'] = $fresh;
        $_SESSION['auth_permissions'] = self::loadPermissions($db, (int)$fresh['UserID']);
        return true;
    }

    public static function loadPermissions(PDO $db, int $userId): array
    {
        $stmt = $db->prepare(
            'SELECT ModuleKey, CanView, CanCreate, CanEdit, CanDelete
             FROM dbo.AppUserPermissions WHERE UserID = ?'
        );
        $stmt->execute([$userId]);
        $permissions = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $permissions[(string)$row['ModuleKey']] = [
                'view' => (int)$row['CanView'] === 1,
                'create' => (int)$row['CanCreate'] === 1,
                'edit' => (int)$row['CanEdit'] === 1,
                'delete' => (int)$row['CanDelete'] === 1,
            ];
        }
        return $permissions;
    }

    public static function currentUser(): ?array
    {
        self::startSession();
        $user = $_SESSION['auth_user'] ?? null;
        return is_array($user) ? $user : null;
    }

    public static function isLoggedIn(): bool
    {
        return self::currentUser() !== null;
    }

    public static function isSuperAdmin(): bool
    {
        $user = self::currentUser();
        return $user !== null && (int)($user['IsSuperAdmin'] ?? 0) === 1;
    }

    public static function can(string $moduleKey, string $action = 'view'): bool
    {
        if (!self::isLoggedIn()) return false;
        if (self::isSuperAdmin()) return true;
        if (!in_array($action, ['view', 'create', 'edit', 'delete'], true)) return false;
        $permissions = $_SESSION['auth_permissions'] ?? [];
        return (bool)($permissions[$moduleKey][$action] ?? false);
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }

    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function landingPage(): string
    {
        if (self::can('dashboard', 'view')) return 'dashboard';
        foreach (Modules::all() as $key => $_module) {
            if (self::can($key, 'view')) return $key;
        }
        return 'access_denied';
    }
}
