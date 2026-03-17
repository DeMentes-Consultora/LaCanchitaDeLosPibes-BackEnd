<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/vendor/autoload.php';

// Carga variables de entorno desde .env si existe.
if (class_exists(\Dotenv\Dotenv::class) && file_exists(__DIR__ . '/.env')) {
	$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
	$dotenv->safeLoad();
}

/**
 * Obtiene una variable de entorno con fallback.
 */
function envValue(string $key, ?string $default = null): ?string
{
	$value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
	if ($value === false || $value === null || $value === '') {
		return $default;
	}
	return (string) $value;
}

/**
 * Convierte texto de entorno a bool.
 */
function envBool(string $key, bool $default = false): bool
{
	$value = envValue($key);
	if ($value === null) {
		return $default;
	}

	$normalized = strtolower(trim($value));
	return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

$to = $argv[1] ?? envValue('MAIL_TEST_TO');

if (!$to) {
	fwrite(STDERR, "Uso: php test_email_registro.php destinatario@email.com\n");
	fwrite(STDERR, "Tip: tambien podes usar MAIL_TEST_TO en .env\n");
	exit(1);
}

$host = envValue('MAIL_HOST');
$port = (int) (envValue('MAIL_PORT', '587') ?? '587');
$username = envValue('MAIL_USERNAME');
$password = envValue('MAIL_PASSWORD');
$smtpAuth = envBool('MAIL_SMTPAuth', true);
$timeout = (int) (envValue('MAIL_TIMEOUT', '20') ?? '20');
$debug = (int) (envValue('MAIL_DEBUG', '0') ?? '0');
$fromAddress = envValue('MAIL_FROM_ADDRESS', $username);
$fromName = envValue('MAIL_FROM_NAME', envValue('APP_NAME', 'La Canchita de los Pibes'));
$secure = envValue('MAIL_ENCRYPTION');

if (!$secure) {
	$secure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
}

$required = [
	'MAIL_HOST' => $host,
	'MAIL_USERNAME' => $username,
	'MAIL_PASSWORD' => $password,
	'MAIL_FROM_ADDRESS' => $fromAddress,
];

foreach ($required as $name => $value) {
	if (!$value) {
		fwrite(STDERR, "Falta configurar {$name} en .env\n");
		exit(1);
	}
}

try {
	$mail = new PHPMailer(true);
	$mail->isSMTP();
	$mail->Host = (string) $host;
	$mail->Port = $port;
	$mail->SMTPAuth = $smtpAuth;
	$mail->Timeout = $timeout;
	$mail->SMTPDebug = $debug;
	$mail->Debugoutput = 'error_log';
	$mail->Username = (string) $username;
	$mail->Password = (string) $password;
	$mail->SMTPSecure = $secure;
	$mail->CharSet = 'UTF-8';

	$mail->setFrom((string) $fromAddress, (string) $fromName);
	$mail->addAddress((string) $to);

	$mail->isHTML(true);
	$mail->Subject = 'Prueba PHPMailer - ' . date('Y-m-d H:i:s');
	$mail->Body = '<h2>PHPMailer funcionando</h2><p>Este es un correo de prueba del backend.</p>';
	$mail->AltBody = 'PHPMailer funcionando. Este es un correo de prueba del backend.';

	$mail->send();
	echo "OK: correo enviado a {$to}\n";
	exit(0);
} catch (Exception $e) {
	fwrite(STDERR, "ERROR al enviar correo: {$mail->ErrorInfo}\n");
	fwrite(STDERR, "Detalle tecnico: {$e->getMessage()}\n");
	exit(1);
}

