<?php
// cors
require_once __DIR__ . '/../Template/cors.php';
require_once __DIR__ . '/../ConectionBD/CConection.php';

try {
    $db = new ConectionDB();
    $conn = $db->getConnection();

    // Consulta para obtener instituciones
    $query = "SELECT id, nombre, direccion FROM institucion ORDER BY nombre ASC";
    $result = $conn->query($query);

    if ($result) {
        $instituciones = [];
        while ($row = $result->fetch_assoc()) {
            $instituciones[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'direccion' => $row['direccion'] ?? ''
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $instituciones
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
