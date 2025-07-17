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

  # SSH access
  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "SSH access"
  }

  # HTTP access
  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTP access"
  }

  # HTTPS access
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

# User data script for server setup
locals {
  user_data = base64encode(join("\n", [
    "#!/bin/bash",
    "set -e",
    "",
    "# Log everything",
    "exec > >(tee /var/log/user-data.log) 2>&1",
    "",
    "echo 'Starting Axialy Admin server setup at $(date)'",
    "",
    "# Update system",
    "echo 'Updating system packages...'",
    "dnf update -y",
    "",
    "# Install required packages",
    "echo 'Installing required packages...'",
    "dnf install -y httpd php php-cli php-fpm php-mysqlnd php-zip php-xml php-mbstring php-json php-curl php-gd php-opcache mysql unzip wget curl git",
    "",
    "# Configure PHP for production",
    "echo 'Configuring PHP...'",
    "cat > /etc/php.ini << 'PHP_CONFIG'",
    "[PHP]",
    "memory_limit = 256M",
    "upload_max_filesize = 50M",
    "post_max_size = 50M",
    "max_execution_time = 300",
    "max_input_time = 300",
    "session.gc_maxlifetime = 14400",
    "session.cookie_secure = 1",
    "session.cookie_httponly = 1",
    "session.use_only_cookies = 1",
    "expose_php = Off",
    "display_errors = Off",
    "log_errors = On",
    "error_log = /var/log/php-errors.log",
    "date.timezone = UTC",
    "PHP_CONFIG",
    "",
    "# Start and enable services",
    "echo 'Starting services...'",
    "systemctl start httpd",
    "systemctl enable httpd",
    "systemctl start php-fpm",
    "systemctl enable php-fpm",
    "",
    "# Configure Apache virtual host",
    "echo 'Configuring Apache...'",
    "cat > /etc/httpd/conf.d/axialy-admin.conf << 'APACHE_CONFIG'",
    "<VirtualHost *:80>",
    "    DocumentRoot /var/www/html/axialy-admin",
    "    ServerName admin.axialy.com",
    "    ",
    "    <Directory /var/www/html/axialy-admin>",
    "        Options -Indexes +FollowSymLinks",
    "        AllowOverride All",
    "        Require all granted",
    "        ",
    "        # Security headers",
    "        Header always set X-Content-Type-Options nosniff",
    "        Header always set X-Frame-Options DENY",
    "        Header always set X-XSS-Protection \"1; mode=block\"",
    "        Header always set Strict-Transport-Security \"max-age=31536000; includeSubDomains\"",
    "        Header always set Referrer-Policy \"strict-origin-when-cross-origin\"",
    "    </Directory>",
    "    ",
    "    # Hide sensitive files",
    "    <FilesMatch \"\\.(env|log|ini)$\">",
    "        Require all denied",
    "    </FilesMatch>",
    "    ",
    "    # PHP handling",
    "    <FilesMatch \\.php$>",
    "        SetHandler \"proxy:unix:/run/php-fpm/www.sock|fcgi://localhost\"",
    "    </FilesMatch>",
    "    ",
    "    # Logging",
    "    ErrorLog /var/log/httpd/axialy-admin-error.log",
    "    CustomLog /var/log/httpd/axialy-admin-access.log combined",
    "</VirtualHost>",
    "APACHE_CONFIG",
    "",
    "# Create application directory",
    "echo 'Setting up application directory...'",
    "mkdir -p /var/www/html/axialy-admin",
    "",
    "# Download application files from GitHub",
    "echo 'Downloading application files...'",
    "cd /tmp",
    "curl -L -o axialy-admin.zip 'https://github.com/axialy-ai/openai-poc/archive/main.zip'",
    "unzip -q axialy-admin.zip",
    "cp -r openai-poc-main/axialy-admin-product/* /var/www/html/axialy-admin/",
    "",
    "# Create .env file with configuration",
    "echo 'Creating environment configuration...'",
    "cat > /var/www/html/axialy-admin/.env << ENV_CONFIG",
    "# Database Configuration (AWS RDS)",
    "DB_HOST=${var.db_host}",
    "DB_PORT=${var.db_port}",
    "DB_USER=${var.db_user}",
    "DB_PASSWORD=${var.db_password}",
    "DB_NAME=axialy_admin",
    "",
    "# UI Database Configuration",
    "UI_DB_HOST=${var.db_host}",
    "UI_DB_PORT=${var.db_port}",
    "UI_DB_USER=${var.db_user}",
    "UI_DB_PASSWORD=${var.db_password}",
    "UI_DB_NAME=axialy_ui",
    "",
    "# Application Configuration",
    "APP_ENV=production",
    "APP_DEBUG=false",
    "APP_BASE_URL=http://admin.axialy.com",
    "",
    "# Session Configuration",
    "SESSION_LIFETIME=14400",
    "SESSION_SECURE=false",
    "SESSION_HTTPONLY=true",
    "",
    "# Admin Configuration",
    "ADMIN_DEFAULT_USER=${var.admin_default_user}",
    "ADMIN_DEFAULT_EMAIL=${var.admin_default_email}",
    "ADMIN_DEFAULT_PASSWORD=${var.admin_default_password}",
    "",
    "# SMTP Configuration",
    "SMTP_HOST=${var.smtp_host}",
    "SMTP_PORT=${var.smtp_port}",
    "SMTP_USER=${var.smtp_user}",
    "SMTP_PASSWORD=${var.smtp_password}",
    "SMTP_SECURE=${var.smtp_secure}",
    "ENV_CONFIG",
    "",
    "# Create .htaccess for additional security",
    "cat > /var/www/html/axialy-admin/.htaccess << 'HTACCESS'",
    "# Security headers",
    "<IfModule mod_headers.c>",
    "    Header always set X-Content-Type-Options nosniff",
    "    Header always set X-Frame-Options DENY",
    "    Header always set X-XSS-Protection \"1; mode=block\"",
    "</IfModule>",
    "",
    "# Prevent access to sensitive files",
    "<FilesMatch \"\\.(env|log|ini|conf)$\">",
    "    Order allow,deny",
    "    Deny from all",
    "</FilesMatch>",
    "",
    "# Directory protection",
    "<FilesMatch \"^\\.\">",
    "    Order allow,deny",
    "    Deny from all",
    "</FilesMatch>",
    "",
    "# PHP error handling",
    "php_flag display_errors Off",
    "php_flag log_errors On",
    "php_value error_log /var/log/httpd/php-errors.log",
    "HTACCESS",
    "",
    "# Create health check endpoint",
    "cat > /var/www/html/axialy-admin/health.php << 'HEALTH_PHP'",
    "<?php",
    "header('Content-Type: application/json');",
    "",
    "$health = [",
    "    'status' => 'ok',",
    "    'timestamp' => date('c'),",
    "    'services' => []",
    "];",
    "",
    "// Check database connection",
    "try {",
    "    require_once __DIR__ . '/includes/AdminDBConfig.php';",
    "    use Axialy\\AdminConfig\\AdminDBConfig;",
    "    AdminDBConfig::getInstance()->getPdo();",
    "    $health['services']['database'] = 'ok';",
    "} catch (Exception $e) {",
    "    $health['services']['database'] = 'error';",
    "    $health['status'] = 'error';",
    "    $health['database_error'] = $e->getMessage();",
    "}",
    "",
    "// Check PHP",
    "$health['services']['php'] = 'ok';",
    "$health['php_version'] = PHP_VERSION;",
    "",
    "// Check Apache",
    "$health['services']['apache'] = 'ok';",
    "",
    "echo json_encode($health, JSON_PRETTY_PRINT);",
    "HEALTH_PHP",
    "",
    "# Set proper permissions",
    "echo 'Setting file permissions...'",
    "chown -R apache:apache /var/www/html/axialy-admin",
    "chmod -R 755 /var/www/html/axialy-admin",
    "chmod 600 /var/www/html/axialy-admin/.env",
    "chmod 644 /var/www/html/axialy-admin/health.php",
    "",
    "# Create log directories",
    "mkdir -p /var/log/axialy-admin",
    "chown apache:apache /var/log/axialy-admin",
    "",
    "# Test database connection",
    "echo 'Testing database connection...'",
    "php -r \"",
    "\\$host = '${var.db_host}';",
    "\\$port = '${var.db_port}';",
    "\\$user = '${var.db_user}';",
    "\\$pass = '${var.db_password}';",
    "\\$dsn = \\\"mysql:host=\\$host;port=\\$port;dbname=axialy_admin;charset=utf8mb4\\\";",
    "try {",
    "    \\$pdo = new PDO(\\$dsn, \\$user, \\$pass, [",
    "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,",
    "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,",
    "    ]);",
    "    echo 'Database connection successful!' . PHP_EOL;",
    "} catch (Exception \\$e) {",
    "    echo 'Database connection failed: ' . \\$e->getMessage() . PHP_EOL;",
    "    exit(1);",
    "}",
    "\"",
    "",
    "# Restart services",
    "echo 'Restarting services...'",
    "systemctl restart httpd",
    "systemctl restart php-fpm",
    "",
    "# Verify services are running",
    "systemctl is-active httpd || (echo 'Apache failed to start' && exit 1)",
    "systemctl is-active php-fpm || (echo 'PHP-FPM failed to start' && exit 1)",
    "",
    "# Test HTTP response",
    "echo 'Testing HTTP response...'",
    "sleep 5",
    "PUBLIC_IP=$(curl -s http://169.254.169.254/latest/meta-data/public-ipv4)",
    "HTTP_STATUS=$(curl -s -o /dev/null -w '%%{http_code}' http://localhost/admin_login.php || echo '000')",
    "if [ \"$HTTP_STATUS\" = \"200\" ]; then",
    "    echo '✓ Application is responding correctly (HTTP '$HTTP_STATUS')'",
    "else",
    "    echo '⚠ Application response status: HTTP '$HTTP_STATUS",
    "fi",
    "",
    "echo '✓ Axialy Admin server setup completed successfully at $(date)'",
    "echo '✓ Application should be accessible at http://'$PUBLIC_IP'/admin_login.php'"
  ]))
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
    volume_size = 20
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
