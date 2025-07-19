cat > /tmp/test_mailer.php << 'EOF'
<?php
require_once '/var/www/html/axialy-ui/vendor/autoload.php';

// Load environment variables from .env file
$envFile = '/var/www/html/axialy-ui/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

echo 'After loading .env file:' . PHP_EOL;
echo 'SMTP_HOST: ' . getenv('SMTP_HOST') . PHP_EOL;
echo 'SMTP_PORT: ' . getenv('SMTP_PORT') . PHP_EOL;
echo 'SMTP_USER: ' . getenv('SMTP_USER') . PHP_EOL;
echo 'SMTP_SECURE: ' . getenv('SMTP_SECURE') . PHP_EOL;

use PHPMailer\PHPMailer\PHPMailer;
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST');
    $mail->Port = (int)(getenv('SMTP_PORT'));
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USER');
    $mail->Password = getenv('SMTP_PASSWORD');
    
    echo 'PHPMailer configured successfully!' . PHP_EOL;
    echo 'Ready to send emails via: ' . getenv('SMTP_HOST') . ':' . getenv('SMTP_PORT') . PHP_EOL;
} catch (Exception $e) {
    echo 'PHPMailer error: ' . $e->getMessage() . PHP_EOL;
}
EOF

php /tmp/test_mailer.php
