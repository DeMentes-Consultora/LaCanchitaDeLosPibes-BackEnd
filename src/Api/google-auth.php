<?php

require_once __DIR__ . '/../Template/cors.php';
require_once __DIR__ . '/../ConectionBD/CConection.php';

if (file_exists(__DIR__ . '/../services/CloudinaryService.php')) {
    require_once __DIR__ . '/../services/CloudinaryService.php';
}

header('Content-Type: application/json');

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function validarTokenGoogle(string $idToken): ?array
{
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);

    $responseBody = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $responseBody = curl_exec($ch);
        curl_close($ch);
    }

    if ($responseBody === false) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
    }

    if ($responseBody === false) {
        return null;
    }

    $tokenData = json_decode($responseBody, true);
    return is_array($tokenData) ? $tokenData : null;
}

function hasColumn(mysqli $conn, string $table, string $column): bool
{
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function isCloudinaryConfigured(): bool
{
    $cloudName = trim((string) ($_ENV['CLOUDINARY_CLOUD_NAME'] ?? ''));
    $apiKey = trim((string) ($_ENV['CLOUDINARY_API_KEY'] ?? ''));
    $apiSecret = trim((string) ($_ENV['CLOUDINARY_API_SECRET'] ?? ''));

    return $cloudName !== '' && $apiKey !== '' && $apiSecret !== '';
}

function storeSocialProfilePhoto(string $photoURL): array
{
    if ($photoURL === '') {
        return [
            'url' => null,
            'public_id' => null,
        ];
    }

    if (!class_exists('CloudinaryService')) {
        return [
            'url' => $photoURL,
            'public_id' => null,
        ];
    }

    if (!isCloudinaryConfigured()) {
        return [
            'url' => $photoURL,
            'public_id' => null,
        ];
    }

    try {
        $folders = require __DIR__ . '/../config/media-folders.php';
        $cloudinary = new CloudinaryService($folders['base'] ?? 'LaCanchitaDeLosPibes');
        $folderFoto = $folders['perfiles']['foto'] ?? 'LaCanchitaDeLosPibes/perfiles';
        $upload = $cloudinary->upload($photoURL, $folderFoto, 'image', [
            'overwrite' => false,
            'unique_filename' => true,
            'use_filename' => false,
        ]);

        if (!empty($upload['success']) && !empty($upload['url'])) {
            return [
                'url' => (string) $upload['url'],
                'public_id' => !empty($upload['public_id']) ? (string) $upload['public_id'] : null,
            ];
        }
    } catch (Throwable $exception) {
        error_log('Cloudinary social profile fallback: ' . $exception->getMessage());
    }

    return [
        'url' => $photoURL,
        'public_id' => null,
    ];
}

function updatePersonaPhoto(mysqli $conn, int $idPersona, string $photoUrl, ?string $publicId): void
{
    if ($idPersona <= 0 || $photoUrl === '') {
        return;
    }

    $stmt = $conn->prepare('
        UPDATE persona
        SET foto_perfil_url = ?, foto_perfil_public_id = ?
        WHERE id_persona = ?
    ');

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la actualizacion de foto de perfil.');
    }

    $stmt->bind_param('ssi', $photoUrl, $publicId, $idPersona);
    $stmt->execute();
    $stmt->close();
}

function buildUserPayload(array $userData, string $provider, string $fallbackPhotoUrl = ''): array
{
    return [
        'id_usuario' => (int) ($userData['id_usuario'] ?? 0),
        'id_persona' => (int) ($userData['id_persona'] ?? 0),
        'nombre' => $userData['nombre'] ?? '',
        'apellido' => $userData['apellido'] ?? '',
        'email' => $userData['email'] ?? '',
        'telefono' => $userData['telefono'] ?? '',
        'id_rol' => (int) ($userData['id_rol'] ?? 6),
        'rol' => $userData['nombre_rol'] ?? 'Cliente',
        'provider' => $provider,
        'photoURL' => $userData['foto_perfil_url'] ?? $fallbackPhotoUrl,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond([
        'success' => false,
        'message' => 'Método no permitido'
    ], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    respond([
        'success' => false,
        'message' => 'Payload inválido'
    ], 400);
}

$email = trim((string) ($data['email'] ?? ''));
$firebaseUid = trim((string) ($data['firebaseUid'] ?? ''));
$provider = strtolower(trim((string) ($data['provider'] ?? 'google')));
$idToken = trim((string) ($data['id_token'] ?? ''));
$nombre = trim((string) ($data['nombre'] ?? ''));
$apellido = trim((string) ($data['apellido'] ?? ''));
$telefono = trim((string) ($data['telefono'] ?? ''));
$photoURL = trim((string) ($data['photoURL'] ?? ''));

if (!in_array($provider, ['google', 'facebook'], true)) {
    $provider = 'google';
}

$providerLabel = ucfirst($provider);

if ($provider === 'google') {
    if ($idToken === '') {
        respond([
            'success' => false,
            'message' => 'Token de Google requerido'
        ], 400);
    }

    $tokenData = validarTokenGoogle($idToken);
    if (!$tokenData || isset($tokenData['error_description'])) {
        respond([
            'success' => false,
            'message' => 'Token de Google invalido o expirado'
        ], 401);
    }

    $googleClientId = trim((string) ($_ENV['GOOGLE_CLIENT_ID'] ?? ''));
    if ($googleClientId !== '' && (($tokenData['aud'] ?? '') !== $googleClientId)) {
        respond([
            'success' => false,
            'message' => 'Token de Google no valido para esta aplicacion'
        ], 401);
    }

    $email = trim((string) ($tokenData['email'] ?? $email));
    $emailVerified = ($tokenData['email_verified'] ?? '') === 'true';
    if ($email === '' || !$emailVerified) {
        respond([
            'success' => false,
            'message' => 'La cuenta de Google debe tener email verificado'
        ], 400);
    }

    if ($firebaseUid === '') {
        $firebaseUid = trim((string) ($tokenData['sub'] ?? ''));
    }

    if ($nombre === '') {
        $nombre = trim((string) ($tokenData['given_name'] ?? ''));
    }

    if ($apellido === '') {
        $apellido = trim((string) ($tokenData['family_name'] ?? ''));
    }

    if ($photoURL === '') {
        $photoURL = trim((string) ($tokenData['picture'] ?? ''));
    }
}

if ($email === '' || $firebaseUid === '' || $nombre === '') {
    respond([
        'success' => false,
        'message' => "Datos incompletos para registro con {$providerLabel}."
    ], 400);
}

try {
    $conn = (new ConectionDB())->getConnection();
    $hasFirebaseUidColumn = hasColumn($conn, 'usuario', 'firebase_uid');

    $stmt = $conn->prepare('
        SELECT
            u.id_usuario,
            u.id_persona,
            u.email,
            p.nombre,
            p.apellido,
            p.telefono,
            p.foto_perfil_url,
            p.foto_perfil_public_id,
            e.id_rol,
            r.rol AS nombre_rol
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        LEFT JOIN empleado e ON u.id_usuario = e.id_usuario AND e.habilitado = 1 AND e.cancelado = 0
        LEFT JOIN roles r ON e.id_rol = r.id_roles AND r.habilitado = 1 AND r.cancelado = 0
        WHERE u.email = ? AND u.habilitado = 1 AND u.cancelado = 0
        LIMIT 1
    ');

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la búsqueda del usuario.');
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingUser = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($existingUser) {
        if ($hasFirebaseUidColumn) {
            $updateFirebaseStmt = $conn->prepare('
                UPDATE usuario
                SET firebase_uid = COALESCE(firebase_uid, ?)
                WHERE id_usuario = ?
            ');

            if ($updateFirebaseStmt) {
                $idUsuario = (int) $existingUser['id_usuario'];
                $updateFirebaseStmt->bind_param('si', $firebaseUid, $idUsuario);
                $updateFirebaseStmt->execute();
                $updateFirebaseStmt->close();
            }
        }

        if (($existingUser['foto_perfil_url'] ?? '') === '' && $photoURL !== '') {
            $storedPhoto = storeSocialProfilePhoto($photoURL);
            updatePersonaPhoto(
                $conn,
                (int) $existingUser['id_persona'],
                (string) ($storedPhoto['url'] ?? ''),
                $storedPhoto['public_id'] ?? null
            );

            $existingUser['foto_perfil_url'] = $storedPhoto['url'] ?? $photoURL;
            $existingUser['foto_perfil_public_id'] = $storedPhoto['public_id'] ?? null;
        }

        respond([
            'success' => true,
            'message' => "Inicio de sesión exitoso con {$providerLabel}",
            'user' => buildUserPayload($existingUser, $provider, $photoURL)
        ]);
    }

    $storedPhoto = storeSocialProfilePhoto($photoURL);
    $conn->begin_transaction();

    try {
        $fotoPerfilUrl = $storedPhoto['url'] ?? null;
        $fotoPerfilPublicId = $storedPhoto['public_id'] ?? null;
        $edad = '18';
        $dni = '';

        $stmtPersona = $conn->prepare('
            INSERT INTO persona (apellido, nombre, edad, dni, telefono, foto_perfil_url, foto_perfil_public_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        if (!$stmtPersona) {
            throw new RuntimeException('No se pudo preparar la creación de persona.');
        }

        $stmtPersona->bind_param('sssssss', $apellido, $nombre, $edad, $dni, $telefono, $fotoPerfilUrl, $fotoPerfilPublicId);
        $stmtPersona->execute();
        $idPersona = (int) $conn->insert_id;
        $stmtPersona->close();

        $hashedUid = password_hash($firebaseUid, PASSWORD_DEFAULT);

        if ($hasFirebaseUidColumn) {
            $stmtUsuario = $conn->prepare('
                INSERT INTO usuario (email, clave, id_persona, firebase_uid)
                VALUES (?, ?, ?, ?)
            ');

            if (!$stmtUsuario) {
                throw new RuntimeException('No se pudo preparar la creación de usuario.');
            }

            $stmtUsuario->bind_param('ssis', $email, $hashedUid, $idPersona, $firebaseUid);
        } else {
            $stmtUsuario = $conn->prepare('
                INSERT INTO usuario (email, clave, id_persona)
                VALUES (?, ?, ?)
            ');

            if (!$stmtUsuario) {
                throw new RuntimeException('No se pudo preparar la creación de usuario.');
            }

            $stmtUsuario->bind_param('ssi', $email, $hashedUid, $idPersona);
        }

        $stmtUsuario->execute();
        $idUsuario = (int) $conn->insert_id;
        $stmtUsuario->close();

        $clienteRolId = 6;
        $stmtEmpleado = $conn->prepare('
            INSERT INTO empleado (id_rol, id_persona, id_usuario)
            VALUES (?, ?, ?)
        ');

        if (!$stmtEmpleado) {
            throw new RuntimeException('No se pudo preparar la asignación de rol.');
        }

        $stmtEmpleado->bind_param('iii', $clienteRolId, $idPersona, $idUsuario);
        $stmtEmpleado->execute();
        $stmtEmpleado->close();

        $conn->commit();

        respond([
            'success' => true,
            'message' => "Usuario registrado exitosamente con {$providerLabel}",
            'user' => [
                'id_usuario' => $idUsuario,
                'id_persona' => $idPersona,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email,
                'telefono' => $telefono,
                'id_rol' => $clienteRolId,
                'rol' => 'Cliente',
                'provider' => $provider,
                'photoURL' => $fotoPerfilUrl ?? $photoURL,
            ]
        ]);
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
} catch (Throwable $exception) {
    if ((bool) ($_ENV['APP_DEBUG'] ?? false)) {
        error_log('google-auth.php: ' . $exception->getMessage());
    }

    respond([
        'success' => false,
        'message' => 'Error interno del servidor'
    ], 500);
}
