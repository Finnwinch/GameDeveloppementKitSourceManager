<?php
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

$status = trim(shell_exec("docker inspect -f '{{.State.Running}}' gkds-game"));

if ($status === 'true') {
    $log = shell_exec("docker logs --tail 50 gkds-game 2>&1");
    if (!$log) $log = "[Aucun log disponible]";

    $lines = explode("\n", $log);
    $lines = array_filter($lines, function($line) {
        return stripos($line, 'rcon from') === false;
    });
    $log = implode("\n", $lines);
} else {
    $log = "Serveur fermé";
}

echo json_encode(['logs' => $log]);
