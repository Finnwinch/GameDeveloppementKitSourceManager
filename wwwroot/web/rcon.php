<?php
header('Content-Type: application/json');

$server = 'garrysmod-cli';
$port = 27015;
$password = 'TonMotDePasse';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$command = trim($input['command'] ?? '');
if ($command === '') {
    echo json_encode(['success' => false, 'message' => 'Commande vide']);
    exit;
}

class Rcon {
    private $socket;
    private $requestId;

    public function connect($host, $port, $password, $timeout = 2) {
        $this->socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$this->socket) {
            throw new Exception("Connexion échouée: $errstr ($errno)");
        }
        stream_set_timeout($this->socket, $timeout);

        $this->requestId = rand(1, 100000);
        $this->sendPacket(3, $password);
        $response = $this->readPacket();

        if ($response['id'] === -1) {
            throw new Exception("Mot de passe RCON invalide.");
        }
    }

    public function sendCommand($cmd) {
        $this->sendPacket(2, $cmd);
        $response = $this->readPacket();
        return $response['body'] ?? '';
    }

    private function sendPacket($type, $body) {
        $data = pack('VV', $this->requestId, $type) . $body . "\x00\x00";
        $length = pack('V', strlen($data));
        fwrite($this->socket, $length . $data);
    }

    private function readPacket() {
        $sizeData = fread($this->socket, 4);
        if (strlen($sizeData) < 4) return ['id' => -1, 'body' => ''];
        $size = unpack('V', $sizeData)[1];

        $packet = fread($this->socket, $size);
        $header = unpack('Vid/Vtype', substr($packet, 0, 8));
        $body = substr($packet, 8, -2);

        return ['id' => $header['id'], 'type' => $header['type'], 'body' => $body];
    }

    public function disconnect() {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
}

try {
    $rcon = new Rcon();
    $rcon->connect($server, $port, $password);
    $output = $rcon->sendCommand($command);
    $rcon->disconnect();

    echo json_encode(['success' => true, 'output' => $output]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
