<?php
// api/get-conf.php - Recupera dati configurazione

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

use App\Repository\ConfigOperatoreRepository;

try {
    $container = require __DIR__ . '/../bootstrap.php';
    $db = $container['db'];
    $confRepo = new ConfigOperatoreRepository($db);

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID mancante']);
        exit;
    }

    $conf = $confRepo->findById($id);
    if (!$conf) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Configurazione non trovata']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $conf
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
