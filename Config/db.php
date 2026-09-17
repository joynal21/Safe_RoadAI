<?php
/**
 * SafeRoad AI database connection.
 *
 * Local/XAMPP fallback:
 *   host=localhost, port=3306, user=root, password="", database=saferoad_ai
 *
 * Render/cloud:
 *   DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME
 *
 * Optional single-URL alternative:
 *   DATABASE_URL=mysql://USER:PASSWORD@HOST:PORT/DBNAME
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function sr_env(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    return ($value === false || trim((string)$value) === '') ? $default : trim((string)$value);
}

function sr_database_config(): array {
    $host = sr_env('DB_HOST');
    $port = sr_env('DB_PORT');
    $user = sr_env('DB_USER');
    $password = sr_env('DB_PASSWORD');
    $database = sr_env('DB_NAME');

    // Optional fallback if a provider gives one MySQL service URI.
    $databaseUrl = sr_env('DATABASE_URL') ?? sr_env('MYSQL_URL');
    if ($host === null && $databaseUrl !== null) {
        $parts = parse_url($databaseUrl);
        if (is_array($parts) && (($parts['scheme'] ?? '') === 'mysql')) {
            $host = isset($parts['host']) ? (string)$parts['host'] : null;
            $port = isset($parts['port']) ? (string)$parts['port'] : $port;
            $user = isset($parts['user']) ? rawurldecode((string)$parts['user']) : $user;
            $password = isset($parts['pass']) ? rawurldecode((string)$parts['pass']) : $password;
            if (isset($parts['path'])) {
                $urlDb = ltrim((string)$parts['path'], '/');
                if ($urlDb !== '') $database = rawurldecode($urlDb);
            }
        }
    }

    $isCloud = $host !== null;

    return [
        'is_cloud' => $isCloud,
        'host' => $host ?? 'localhost',
        'port' => (int)($port ?? '3306'),
        'user' => $user ?? 'root',
        'password' => $password ?? '',
        'database' => $database ?? 'saferoad_ai',
    ];
}

function sr_database_public_error(Throwable $e): string {
    $message = strtolower($e->getMessage());

    if (str_contains($message, 'getaddrinfo') || str_contains($message, 'name or service not known') || str_contains($message, 'php_network_getaddresses')) {
        return 'The database host cannot be reached. In Render, update DB_HOST with the current MySQL Host shown by Aiven and make sure the Aiven MySQL service is Running.';
    }
    if (str_contains($message, 'access denied')) {
        return 'The database rejected the login. Check DB_USER and DB_PASSWORD in Render Environment Variables.';
    }
    if (str_contains($message, 'unknown database')) {
        return 'The selected database does not exist. Check DB_NAME in Render or create that database in Aiven.';
    }
    if (str_contains($message, 'connection refused') || str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
        return 'The database did not accept the connection. Check DB_HOST, DB_PORT, and that the Aiven MySQL service is Running.';
    }

    return 'The database is temporarily unavailable. Check the Render database environment variables and the Aiven MySQL service status.';
}

$config = sr_database_config();
$host = $config['host'];
$port = $config['port'];
$user = $config['user'];
$password = $config['password'];
$database = $config['database'];

try {
    if ($config['is_cloud']) {
        $conn = mysqli_init();
        if (!$conn) {
            throw new RuntimeException('Could not initialize MySQL client.');
        }

        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 8);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
            $conn->options(MYSQLI_OPT_READ_TIMEOUT, 12);
        }

        // Aiven MySQL requires TLS. No certificates or passwords are committed to GitHub.
        $conn->ssl_set(null, null, null, null, null);

        // Suppress duplicate PHP transport warnings; exceptions are handled below.
        @$conn->real_connect(
            $host,
            $user,
            $password,
            $database,
            $port,
            null,
            MYSQLI_CLIENT_SSL
        );
    } else {
        // Local XAMPP convenience: create/select the project DB automatically.
        $conn = new mysqli($host, $user, $password, '', $port);
        $escapedDatabase = str_replace('`', '``', $database);
        $conn->query(
            "CREATE DATABASE IF NOT EXISTS `{$escapedDatabase}` " .
            "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        $conn->select_db($database);
    }

    $conn->set_charset('utf8mb4');

    require_once __DIR__ . '/../includes/schema.php';
    saferoadEnsureCoreTables($conn);

} catch (Throwable $e) {
    error_log('SafeRoad database error: ' . $e->getMessage());
    http_response_code(503);
    $message = sr_database_public_error($e);

    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>SafeRoad AI - Database unavailable</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f6f8fb;color:#172033;padding:40px}.box{max-width:780px;margin:auto;background:white;border:1px solid #dfe5ec;border-radius:14px;padding:28px;box-shadow:0 8px 30px rgba(0,0,0,.06)}h2{margin-top:0}.hint{background:#f2f6ff;padding:14px;border-radius:10px;line-height:1.5}code{background:#eef1f5;padding:2px 5px;border-radius:4px}</style></head><body><div class="box">';
    echo '<h2>SafeRoad AI database is temporarily unavailable</h2>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<div class="hint"><strong>Render:</strong> verify <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_USER</code>, <code>DB_PASSWORD</code>, and <code>DB_NAME</code>. Never put the database password in GitHub.</div>';
    echo '</div></body></html>';
    exit;
}
