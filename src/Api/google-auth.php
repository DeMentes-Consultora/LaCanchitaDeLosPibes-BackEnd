<?php
// cors
require_once __DIR__ . '/../Template/cors.php';
require_once __DIR__ . '/../ConectionBD/CConection.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $data) {
    $email = trim($data['email'] ?? '');
    $firebaseUid = trim($data['firebaseUid'] ?? '');
    $provider = strtolower(trim($data['provider'] ?? 'google'));
    $nombre = trim($data['nombre'] ?? '');
    $apellido = trim($data['apellido'] ?? '');
    $telefono = trim($data['telefono'] ?? '');
    $photoURL = trim($data['photoURL'] ?? '');

    if (!in_array($provider, ['google', 'facebook'], true)) {
        $provider = 'google';
    }

    $providerLabel = ucfirst($provider);
    
    if (empty($email) || empty($firebaseUid) || empty($nombre)) {
        echo json_encode([
            'success' => false, 
            'message' => "Datos incompletos para registro con {$providerLabel}."
        ]);
        exit;
    }

    try {
        $conn = (new ConectionDB())->getConnection();
        
        // Verificar si el usuario ya existe por email
        $stmt = $conn->prepare("
            SELECT u.*, p.*, e.id_rol, r.rol as nombre_rol
            FROM usuario u
            INNER JOIN persona p ON u.id_persona = p.id_persona
            LEFT JOIN empleado e ON u.id_usuario = e.id_usuario
            LEFT JOIN roles r ON e.id_rol = r.id_roles
            WHERE u.email = ? AND u.habilitado = 1
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Usuario ya existe, actualizar con datos de Google si es necesario
            $userData = $result->fetch_assoc();
            
            // Actualizar firebase_uid si no existe
            $updateStmt = $conn->prepare("
                UPDATE usuario SET firebase_uid = ? WHERE id_usuario = ? AND firebase_uid IS NULL
            ");
            $updateStmt->bind_param('si', $firebaseUid, $userData['id_usuario']);
            $updateStmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => "Inicio de sesión exitoso con {$providerLabel}",
                'user' => [
                    'id_usuario' => (int)$userData['id_usuario'],
                    'nombre' => $userData['nombre'],
                    'apellido' => $userData['apellido'],
                    'email' => $userData['email'],
                    'telefono' => $userData['telefono'],
                    'id_rol' => $userData['id_rol'] ?? 6,
                    'rol' => $userData['nombre_rol'] ?? 'Cliente',
                    'provider' => $provider,
                    'photoURL' => $photoURL
                ]
            ]);
        } else {
            // Nuevo usuario, registrar automáticamente
            $conn->begin_transaction();
            
            try {
                // 1. Insertar en tabla persona
                $stmtPersona = $conn->prepare("
                    INSERT INTO persona (nombre, apellido, edad, dni, telefono) 
                    VALUES (?, ?, 18, '', ?)
                ");
                $stmtPersona->bind_param('sss', $nombre, $apellido, $telefono);
                $stmtPersona->execute();
                $idPersona = $conn->insert_id;
                
                // 2. Insertar en tabla usuario
                $stmtUsuario = $conn->prepare("
                    INSERT INTO usuario (email, clave, id_persona, firebase_uid) 
                    VALUES (?, ?, ?, ?)
                ");
                // Para usuarios de Google, usamos el UID como clave
                $hashedUid = password_hash($firebaseUid, PASSWORD_DEFAULT);
                $stmtUsuario->bind_param('ssis', $email, $hashedUid, $idPersona, $firebaseUid);
                $stmtUsuario->execute();
                $idUsuario = $conn->insert_id;
                
                // 3. Insertar en tabla empleado con rol Cliente (6)
                $stmtEmpleado = $conn->prepare("
                    INSERT INTO empleado (id_rol, id_persona, id_usuario) 
                    VALUES (6, ?, ?)
                ");
                $stmtEmpleado->bind_param('ii', $idPersona, $idUsuario);
                $stmtEmpleado->execute();
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => "Usuario registrado exitosamente con {$providerLabel}",
                    'user' => [
                        'id_usuario' => (int)$idUsuario,
                        'nombre' => $nombre,
                        'apellido' => $apellido,
                        'email' => $email,
                        'telefono' => $telefono,
                        'id_rol' => 6,
                        'rol' => 'Cliente',
                        'provider' => $provider,
                        'photoURL' => $photoURL
                    ]
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error del servidor: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}
?>
