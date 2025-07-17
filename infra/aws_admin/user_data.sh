#!/bin/bash
set -e

# Enhanced logging
exec > >(tee /var/log/user-data.log) 2>&1
echo "Starting Axialy Admin setup at $(date)"

# Error handling
error_exit() {
    echo "ERROR: $1" >&2
    exit 1
}

# Update and install packages
echo "Installing packages..."
dnf update -y || error_exit "Failed to update packages"
dnf install -y httpd php php-cli php-fpm php-mysqlnd php-zip php-xml php-mbstring php-json php-curl php-gd php-opcache mysql unzip wget curl || error_exit "Failed to install packages"

# Enable mod_headers
echo "LoadModule headers_module modules/mod_headers.so" >> /etc/httpd/conf/httpd.conf

# Create web directory
mkdir -p /var/www/html/includes
rm -rf /var/www/html/* 2>/dev/null || true

# Create environment file
cat > /var/www/html/.env << 'ENV_EOF'
DB_HOST=${db_host}
DB_PORT=${db_port}
DB_USER=${db_user}
DB_PASSWORD=${db_password}
DB_NAME=axialy_admin
UI_DB_HOST=${db_host}
UI_DB_PORT=${db_port}
UI_DB_USER=${db_user}
UI_DB_PASSWORD=${db_password}
UI_DB_NAME=axialy_ui
ADMIN_DEFAULT_USER=${admin_default_user}
ADMIN_DEFAULT_EMAIL=${admin_default_email}
ADMIN_DEFAULT_PASSWORD=${admin_default_password}
SMTP_HOST=${smtp_host}
SMTP_PORT=${smtp_port}
SMTP_USER=${smtp_user}
SMTP_PASSWORD=${smtp_password}
SMTP_SECURE=${smtp_secure}
ENV_EOF

# Create AdminDBConfig.php (compact version)
cat > /var/www/html/includes/AdminDBConfig.php << 'PHP_EOF'
<?php
namespace Axialy\AdminConfig;
use PDO, RuntimeException;
final class AdminDBConfig {
    private const REQUIRED_VARS = ['DB_HOST','DB_USER','DB_PASSWORD'];
    private static ?self $instance = null;
    private string $host, $user, $password, $port, $nameAdmin, $nameUI;
    private array $pdoPool = [];
    public static function getInstance(): self { return self::$instance ??= new self(); }
    private function __construct() {
        $this->loadEnv();
        foreach (self::REQUIRED_VARS as $k) {
            if (!getenv($k)) throw new RuntimeException("Missing $k");
        }
        $this->host = getenv('DB_HOST'); $this->user = getenv('DB_USER'); $this->password = getenv('DB_PASSWORD');
        $this->port = getenv('DB_PORT') ?: '3306'; $this->nameAdmin = 'axialy_admin'; $this->nameUI = 'axialy_ui';
    }
    public function getPdo(): PDO { return $this->getPdoFor($this->nameAdmin); }
    public function getPdoFor(string $dbName): PDO {
        if (!isset($this->pdoPool[$dbName])) {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $this->user, $this->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            if ($dbName === $this->nameAdmin) $this->ensureSchema($pdo);
            $this->pdoPool[$dbName] = $pdo;
        }
        return $this->pdoPool[$dbName];
    }
    private function ensureSchema(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, is_active TINYINT(1) DEFAULT 1, is_sys_admin TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_user_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_user_id INT UNSIGNED NOT NULL, session_token CHAR(64) NOT NULL UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME NOT NULL, KEY(admin_user_id), FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE) ENGINE=InnoDB");
    }
    private function loadEnv(): void {
        $env = __DIR__ . '/../.env';
        if (file_exists($env)) {
            foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if ($line[0] !== '#' && strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    putenv(trim($key) . '=' . trim($value));
                }
            }
        }
    }
}
PHP_EOF

# Create admin_auth.php
cat > /var/www/html/includes/admin_auth.php << 'AUTH_EOF'
<?php
require_once __DIR__ . '/AdminDBConfig.php';
use Axialy\AdminConfig\AdminDBConfig;
function requireAdminAuth() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_user_id']) || empty($_SESSION['admin_session_token'])) {
        header('Location: /admin_login.php'); exit;
    }
    $db = AdminDBConfig::getInstance()->getPdo();
    $stmt = $db->prepare("SELECT u.is_active FROM admin_user_sessions s JOIN admin_users u ON s.admin_user_id = u.id WHERE s.admin_user_id = ? AND s.session_token = ? AND s.expires_at > NOW()");
    $stmt->execute([$_SESSION['admin_user_id'], $_SESSION['admin_session_token']]);
    if (!$stmt->fetch() || !$stmt->fetchColumn()) {
        session_destroy(); header('Location: /admin_login.php'); exit;
    }
}
AUTH_EOF

# Create admin_login.php
cat > /var/www/html/admin_login.php << 'LOGIN_EOF'
<?php
session_start();
require_once __DIR__ . '/includes/AdminDBConfig.php';
use Axialy\AdminConfig\AdminDBConfig;
$error = '';
if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($username && $password) {
        try {
            $db = AdminDBConfig::getInstance()->getPdo();
            $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                $db->prepare("DELETE FROM admin_user_sessions WHERE admin_user_id = ?")->execute([$user['id']]);
                $token = bin2hex(random_bytes(32));
                $db->prepare("INSERT INTO admin_user_sessions (admin_user_id, session_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 4 HOUR))")->execute([$user['id'], $token]);
                $_SESSION['admin_user_id'] = $user['id']; $_SESSION['admin_session_token'] = $token;
                header('Location: index.php'); exit;
            }
        } catch (Exception $e) { error_log($e->getMessage()); }
        $error = 'Invalid credentials';
    } else $error = 'Please enter username and password';
}
?><!DOCTYPE html><html><head><title>Axialy Admin Login</title><style>body{font-family:sans-serif;max-width:400px;margin:50px auto;padding:20px}.error{color:red;margin-bottom:1em}input{width:100%;padding:8px;margin:5px 0;box-sizing:border-box}button{width:100%;padding:10px;background:#007BFF;color:white;border:none;cursor:pointer}</style></head><body><h2>Axialy Admin Login</h2><?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post"><input type="text" name="username" placeholder="Username" required autofocus><input type="password" name="password" placeholder="Password" required><button type="submit">Login</button></form></body></html>
LOGIN_EOF

# Create index.php
cat > /var/www/html/index.php << 'INDEX_EOF'
<?php
session_start();
require_once __DIR__ . '/includes/AdminDBConfig.php';
use Axialy\AdminConfig\AdminDBConfig;

// Check if initial user exists
$db = AdminDBConfig::getInstance()->getPdo();
$stmt = $db->prepare("SELECT COUNT(*) FROM admin_users WHERE username = 'caseylide'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    if ($_POST && ($_POST['init_code'] ?? '') === 'Casellio') {
        $hash = password_hash('Casellio', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO admin_users (username, password, email) VALUES ('caseylide', ?, 'caseylide@gmail.com')")->execute([$hash]);
        header('Location: admin_login.php'); exit;
    }
    echo '<!DOCTYPE html><html><head><title>Setup</title></head><body><h2>Initial Setup</h2><form method="post"><input type="password" name="init_code" placeholder="Admin code" required><button type="submit">Initialize</button></form></body></html>';
    exit;
}

require_once __DIR__ . '/includes/admin_auth.php';
requireAdminAuth();
?><!DOCTYPE html><html><head><title>Axialy Admin</title><style>body{font-family:sans-serif;max-width:800px;margin:30px auto;padding:20px}</style></head><body><h1>Axialy Admin Dashboard</h1><p>Welcome to the administration panel.</p><p><a href="admin_login.php?logout=1">Logout</a></p></body></html>
INDEX_EOF

# Create health.php
cat > /var/www/html/health.php << 'HEALTH_EOF'
<?php
header('Content-Type: application/json');
$health = ['status' => 'ok', 'timestamp' => date('c')];
try {
    require_once __DIR__ . '/includes/AdminDBConfig.php';
    use Axialy\AdminConfig\AdminDBConfig;
    AdminDBConfig::getInstance()->getPdo();
    $health['database'] = 'ok';
} catch (Exception $e) {
    $health['status'] = 'error';
    $health['database'] = 'error';
}
echo json_encode($health);
HEALTH_EOF

# Configure PHP
cat > /etc/php.ini << 'PHP_INI'
[PHP]
memory_limit = 256M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
session.cookie_httponly = 1
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php-errors.log
date.timezone = UTC
PHP_INI

# Configure Apache
cat > /etc/httpd/conf.d/axialy-admin.conf << 'APACHE_EOF'
<VirtualHost *:80>
    DocumentRoot /var/www/html
    DirectoryIndex index.php admin_login.php
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"
    </FilesMatch>
    ErrorLog /var/log/httpd/axialy-admin-error.log
    CustomLog /var/log/httpd/axialy-admin-access.log combined
</VirtualHost>
APACHE_EOF

# Set permissions
chown -R apache:apache /var/www/html
chmod -R 755 /var/www/html
chmod 600 /var/www/html/.env

# Start services
systemctl start httpd php-fpm
systemctl enable httpd php-fpm

# Create verification script
cat > /usr/local/bin/verify-axialy-admin << 'VERIFY_EOF'
#!/bin/bash
echo "=== Axialy Admin Status ==="
echo "Apache: $(systemctl is-active httpd)"
echo "PHP-FPM: $(systemctl is-active php-fpm)"
echo "HTTP Test: $(curl -s -o /dev/null -w '%{http_code}' http://localhost/admin_login.php || echo 'FAIL')"
echo "Health: $(curl -s http://localhost/health.php | grep -o '"status":"[^"]*"' || echo 'FAIL')"
VERIFY_EOF
chmod +x /usr/local/bin/verify-axialy-admin

# Test database connection
echo "Testing database connection..."
php -r "
require_once '/var/www/html/includes/AdminDBConfig.php';
use Axialy\AdminConfig\AdminDBConfig;
try {
    AdminDBConfig::getInstance()->getPdo();
    echo 'Database: OK' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Database: ERROR - ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

# Final setup
sleep 5
PUBLIC_IP=$(curl -s http://169.254.169.254/latest/meta-data/public-ipv4 || echo 'localhost')
echo "=== Setup Complete ==="
echo "Access: http://$PUBLIC_IP/admin_login.php"
echo "Status: $(/usr/local/bin/verify-axialy-admin)"
echo "Setup completed at $(date)"
