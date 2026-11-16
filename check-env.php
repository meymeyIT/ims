<?php
/**
 * IMS - Phase 1 environment check.
 * Usage:  php check-env.php [db_user] [db_password] [db_host] [db_port]
 * Example: php check-env.php root ""            (XAMPP/Laragon default)
 */

$ok = fn ($b) => $b ? 'OK     ' : 'MISSING';
$line = str_repeat('-', 60) . PHP_EOL;

echo $line, "PHP", PHP_EOL, $line;
echo 'Version      : ' . PHP_VERSION . (version_compare(PHP_VERSION, '8.2.0', '>=') ? '  (OK, Laravel 12 needs >= 8.2)' : '  (TOO OLD - need >= 8.2)') . PHP_EOL;
echo 'php.ini      : ' . (php_ini_loaded_file() ?: 'none loaded!') . PHP_EOL;
echo 'OS           : ' . PHP_OS_FAMILY . PHP_EOL;

echo $line, "PHP extensions (Laravel + DomPDF + Laravel Excel)", PHP_EOL, $line;
$exts = [
    'pdo_mysql' => 'MySQL driver',
    'mbstring'  => 'Laravel core',
    'openssl'   => 'Laravel core',
    'tokenizer' => 'Laravel core',
    'xml'       => 'Laravel core / Excel',
    'ctype'     => 'Laravel core',
    'fileinfo'  => 'file uploads (product images)',
    'curl'      => 'HTTP client',
    'zip'       => 'Composer + Laravel Excel',
    'gd'        => 'DomPDF images + Laravel Excel',
    'intl'      => 'currency/number formatting (USD/KHR)',
    'bcmath'    => 'precise decimal math (recommended)',
    'dom'       => 'DomPDF',
    'iconv'     => 'Laravel Excel',
];
$missing = [];
foreach ($exts as $e => $why) {
    $has = extension_loaded($e);
    if (!$has) $missing[] = $e;
    printf("%s %-10s %s\n", $ok($has), $e, $why);
}

echo $line, "Command-line tools", PHP_EOL, $line;
$run = function (string $cmd): string {
    $out = @shell_exec($cmd . ' 2>&1');
    $out = trim((string) $out);
    // keep the line that actually contains a version number
    foreach (preg_split('/\R/', $out) as $l) {
        if (preg_match('/\d+\.\d+/', $l)) return trim($l);
    }
    return $out === '' ? 'NOT FOUND' : $out;
};
foreach (['composer' => 'composer -V', 'node' => 'node -v', 'npm' => 'npm -v', 'git' => 'git --version', 'mysql client' => 'mysql --version'] as $name => $cmd) {
    printf("%-13s: %s\n", $name, $run($cmd));
}

echo $line, "Database server", PHP_EOL, $line;
$user = $argv[1] ?? 'root';
$pass = $argv[2] ?? '';
$host = $argv[3] ?? '127.0.0.1';
$port = $argv[4] ?? '3306';
if (!extension_loaded('pdo_mysql')) {
    echo "Skipped: pdo_mysql is not enabled.\n";
} else {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        echo "Connected as '$user' to $host:$port\n";
        echo "Server       : $ver\n";
        if (stripos($ver, 'mariadb') !== false) {
            echo "NOTE         : This is MariaDB, not MySQL 8 (XAMPP ships MariaDB).\n";
        } elseif (version_compare($ver, '8.0.0', '>=')) {
            echo "Status       : OK (MySQL 8)\n";
        } else {
            echo "WARNING      : MySQL older than 8.0\n";
        }
        $dbs = $pdo->query("SHOW DATABASES LIKE 'ims%'")->fetchAll(PDO::FETCH_COLUMN);
        echo 'IMS databases: ' . ($dbs ? implode(', ', $dbs) : 'none yet') . PHP_EOL;
    } catch (Throwable $e) {
        echo "Could not connect: " . $e->getMessage() . PHP_EOL;
        echo "(Is MySQL running? Try: php check-env.php <user> <password>)\n";
    }
}

echo $line;
echo $missing ? 'Enable these PHP extensions in php.ini: ' . implode(', ', $missing) . PHP_EOL : "All PHP extensions present.\n";
echo $line;