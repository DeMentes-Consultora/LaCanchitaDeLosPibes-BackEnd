<?php
// cors
require_once __DIR__ . '/../Template/cors.php';
require_once __DIR__ . '/../ConectionBD/CConection.php';

try {
    $db = new ConectionDB();
    $conn = $db->getConnection();

    // Consulta para obtener roles
    $query = "SELECT id, nombre FROM rol ORDER BY id ASC";
    $result = $conn->query($query);

    if ($result) {
        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre']
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $roles
        ]);
    } else {
        throw new Exception("Error en la consulta: " . $conn->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
