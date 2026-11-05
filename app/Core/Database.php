<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        if (!extension_loaded('pdo_sqlsrv')) {
            throw new RuntimeException('امتداد pdo_sqlsrv غير مفعل في PHP. فعّله من php.ini ثم أعد تشغيل Apache.');
        }

        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $server = (string)($config['server'] ?? '.');
        $database = (string)($config['database'] ?? 'AccountingInventoryERP_DB');
        $encrypt = (string)($config['encrypt'] ?? 'yes');
        $trust = (string)($config['trust_server_certificate'] ?? 'yes');

        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;Encrypt=%s;TrustServerCertificate=%s',
            $server,
            $database,
            $encrypt,
            $trust
        );

        self::$connection = new PDO(
            $dsn,
            $config['username'] ?? null,
            $config['password'] ?? null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::$connection;
    }
}
