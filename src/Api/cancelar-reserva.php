<?php
require_once '../Template/cors.php';
require_once '../ConectionBD/CConection.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost:4200');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $conn = (new ConectionDB())->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || !isset($input['id_reserva'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de reserva requerido']);
        exit;
    }

    $id_reserva = intval($input['id_reserva']);

    // Cancelar la reserva
    $stmt = $conn->prepare("UPDATE reserva SET cancelado = 1, idUpdate = NOW() WHERE id_reserva = ?");
    $stmt->bind_param("i", $id_reserva);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Reserva cancelada exitosamente']);
    } else {
        throw new Exception('Error al cancelar la reserva');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
