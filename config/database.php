<?php
declare(strict_types=1);

/*
 | Local defaults preserve the existing XAMPP setup.
 | For hosting, configure the ERP_DB_* environment variables in the provider panel.
 | A remote SQL Server requires SQL authentication or another supported remote credential method;
 | Windows Integrated Authentication from the local computer does not travel with the website.
 */
return [
    'server' => getenv('ERP_DB_SERVER') ?: '.',
    'database' => getenv('ERP_DB_NAME') ?: 'AccountingInventoryERP_DB',
    'username' => (($value = getenv('ERP_DB_USERNAME')) !== false && $value !== '') ? $value : null,
    'password' => (($value = getenv('ERP_DB_PASSWORD')) !== false && $value !== '') ? $value : null,
    'encrypt' => getenv('ERP_DB_ENCRYPT') ?: 'yes',
    'trust_server_certificate' => getenv('ERP_DB_TRUST_CERT') ?: 'yes',
];
