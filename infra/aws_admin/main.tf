data "aws_availability_zones" "available" {
  state = "available"
}

data "aws_vpc" "default" {
  default = true
}

data "aws_subnets" "default" {
  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.default.id]
  }
}

# Get the latest Amazon Linux 2023 AMI
data "aws_ami" "amazon_linux" {
  most_recent = true
  owners      = ["amazon"]

  filter {
    name   = "name"
    values = ["al2023-ami-*-x86_64"]
  }

  filter {
    name   = "state"
    values = ["available"]
  }
}

# Create security group for Axialy Admin
resource "aws_security_group" "axialy_admin" {
  name        = "${var.instance_identifier}-sg"
  description = "Security group for Axialy Admin application"
  vpc_id      = data.aws_vpc.default.id

  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "SSH access"
  }

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTP access"
  }

  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTPS access"
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "Axialy Admin Security Group"
  }
}

# Simple user data script that downloads and runs a setup script
locals {
  user_data = base64encode(<<-EOF
    #!/bin/bash
    set -e
    exec > >(tee /var/log/user-data.log) 2>&1
    echo "Starting Axialy Admin setup at $(date)"
    
    # Update and install packages
    dnf update -y
    dnf install -y httpd php php-cli php-fpm php-mysqlnd php-zip php-xml php-mbstring php-json php-curl php-gd php-opcache mysql unzip wget curl
    
    # Enable mod_headers
    echo "LoadModule headers_module modules/mod_headers.so" >> /etc/httpd/conf/httpd.conf
    
    # Create web directory
    mkdir -p /var/www/html/includes
    rm -rf /var/www/html/* 2>/dev/null || true
    
    # Create environment file
    cat > /var/www/html/.env << 'ENV_EOF'
DB_HOST=${var.db_host}
DB_PORT=${var.db_port}
DB_USER=${var.db_user}
DB_PASSWORD=${var.db_password}
DB_NAME=axialy_admin
UI_DB_HOST=${var.db_host}
UI_DB_PORT=${var.db_port}
UI_DB_USER=${var.db_user}
UI_DB_PASSWORD=${var.db_password}
UI_DB_NAME=axialy_ui
ADMIN_DEFAULT_USER=${var.admin_default_user}
ADMIN_DEFAULT_EMAIL=${var.admin_default_email}
ADMIN_DEFAULT_PASSWORD=${var.admin_default_password}
SMTP_HOST=${var.smtp_host}
SMTP_PORT=${var.smtp_port}
SMTP_USER=${var.smtp_user}
SMTP_PASSWORD=${var.smtp_password}
SMTP_SECURE=${var.smtp_secure}
ENV_EOF
    
    # Download application files from GitHub
    curl -L -o /tmp/admin.zip "https://github.com/axialy-ai/openai-poc/archive/main.zip" 2>/dev/null || {
      echo "Failed to download from GitHub, creating basic files..."
      
      # Create basic AdminDBConfig.php
      cat > /var/www/html/includes/AdminDBConfig.php << 'PHP_EOF'
<?php
namespace Axialy\AdminConfig;
use PDO, RuntimeException;
final class AdminDBConfig {
    private static ?self $instance = null;
    private string $host, $user, $password, $port;
    private array $pdoPool = [];
    public static function getInstance(): self { return self::$instance ??= new self(); }
    private function __construct() {
        $this->loadEnv();
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->user = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';
        $this->port = getenv('DB_PORT') ?: '3306';
    }
    public function getPdo(): PDO { return $this->getPdoFor('axialy_admin'); }
    public function getPdoFor(string $dbName): PDO {
        if (!isset($this->pdoPool[$dbName])) {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $this->user, $this->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            if ($dbName === 'axialy_admin') $this->ensureSchema($pdo);
            $this->pdoPool[$dbName] = $pdo;
        }
        return $this->pdoPool[$dbName];
    }
    private function ensureSchema(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, is_active TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_user_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_user_id INT UNSIGNED NOT NULL, session_token CHAR(64) NOT NULL UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME NOT NULL, FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE) ENGINE=InnoDB");
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
                $token = bin2hex(random_bytes(32));
                $db->prepare("INSERT INTO admin_user_sessions (admin_user_id, session_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 4 HOUR))")->execute([$user['id'], $token]);
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_session_token'] = $token;
                header('Location: index.php');
                exit;
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
        header('Location: admin_login.php');
        exit;
    }
    echo '<!DOCTYPE html><html><head><title>Setup</title></head><body><h2>Initial Setup</h2><p>Enter the admin code to initialize the system.</p><form method="post"><input type="password" name="init_code" placeholder="Admin code" required><button type="submit">Initialize</button></form></body></html>';
    exit;
}

// Check authentication
if (empty($_SESSION['admin_user_id']) || empty($_SESSION['admin_session_token'])) {
    header('Location: admin_login.php');
    exit;
}
?><!DOCTYPE html><html><head><title>Axialy Admin</title><style>body{font-family:sans-serif;max-width:800px;margin:30px auto;padding:20px}</style></head><body><h1>Axialy Admin Dashboard</h1><p>Welcome to the administration panel.</p><p><a href="admin_login.php">Logout</a></p></body></html>
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
    }
    
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
echo "Apache: $(systemctl is-active httpd)"
echo "PHP-FPM: $(systemctl is-active php-fpm)"
echo "Login test: $(curl -s -o /dev/null -w '%{http_code}' http://localhost/admin_login.php)"
VERIFY_EOF
    chmod +x /usr/local/bin/verify-axialy-admin
    
    # Final test
    sleep 10
    PUBLIC_IP=$(curl -s http://169.254.169.254/latest/meta-data/public-ipv4 2>/dev/null || echo 'localhost')
    echo "Setup completed! Access at: http://$PUBLIC_IP/admin_login.php"
    /usr/local/bin/verify-axialy-admin
    echo "Setup finished at $(date)"
  EOF
  )
}

# EC2 instance for Axialy Admin
resource "aws_instance" "axialy_admin" {
  ami           = data.aws_ami.amazon_linux.id
  instance_type = var.instance_type
  key_name      = var.key_pair_name

  vpc_security_group_ids = [aws_security_group.axialy_admin.id]
  subnet_id              = data.aws_subnets.default.ids[0]

  associate_public_ip_address = true

  user_data = local.user_data

  root_block_device {
    volume_type = "gp3"
    volume_size = 30
    encrypted   = true
  }

  tags = {
    Name        = var.instance_identifier
    Project     = "axialy-ai"
    Environment = "production"
    Component   = "admin"
  }

  lifecycle {
    create_before_destroy = true
  }
}

# Associate existing Elastic IP
data "aws_eip" "axialy_admin" {
  id = var.elastic_ip_allocation_id
}

resource "aws_eip_association" "axialy_admin" {
  instance_id   = aws_instance.axialy_admin.id
  allocation_id = data.aws_eip.axialy_admin.id

  depends_on = [aws_instance.axialy_admin]
}

# CloudWatch Log Group for application logs
resource "aws_cloudwatch_log_group" "axialy_admin" {
  name              = "/aws/ec2/${var.instance_identifier}"
  retention_in_days = 14

  tags = {
    Name = "Axialy Admin Logs"
  }
}
