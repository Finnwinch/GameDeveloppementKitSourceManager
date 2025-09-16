<?php
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['command'])) {
    echo json_encode(['success' => false, 'message' => 'Pas de commande reçue']);
    exit;
}

$command = trim($data['command']);
if ($command === '') {
    echo json_encode(['success' => false, 'message' => 'Commande vide']);
    exit;
}

$dockerContainerName = 'gkds-game';

$escapedCommand = escapeshellarg($command);

$fullCommand = "docker exec -i {$dockerContainerName} /bin/sh -c \"echo {$escapedCommand} > /proc/1/fd/0\" 2>&1";

exec($fullCommand, $output, $returnVar);

if ($returnVar === 0) {
    echo json_encode(['success' => true, 'output' => implode("\n", $output)]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l’exécution', 'details' => implode("\n", $output)]);
}
