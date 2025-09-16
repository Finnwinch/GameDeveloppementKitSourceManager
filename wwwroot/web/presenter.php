<?php
require_once 'model.php';

$model = new GDKSModel('/var/www/gmod');

$requestedPath = $_GET['path'] ?? '';
$requestedPath = str_replace(['..', "\0"], '', $requestedPath);

$itemsData = $model->listItems($requestedPath);
$dirs = $itemsData['dirs'];
$files = $itemsData['files'];

$editingFile = $_GET['edit'] ?? null;
$fileContent = '';
if ($editingFile) {
    $editingFile = str_replace(['..', "\0"], '', $editingFile);
    $fileContent = $model->readFile($editingFile);
    if ($fileContent === null) {
        $fileContent = "Impossible de lire ce fichier.";
    }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_file'], $_POST['file_path'], $_POST['file_content'])) {
        $filePath = $_POST['file_path'];
        $fileContentToSave = $_POST['file_content'];
        if ($model->saveFile($filePath, $fileContentToSave)) {
            $message = "Fichier sauvegardé avec succès : " . htmlspecialchars($filePath);
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                echo json_encode(['success' => true, 'message' => $message]);
                exit;
            }
        } else {
            $message = "Erreur de sauvegarde, fichier invalide.";
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                echo json_encode(['success' => false, 'message' => $message]);
                exit;
            }
        }
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'start') {
            $model->startServer();
        } elseif ($_POST['action'] === 'stop') {
            $model->stopServer();
        }
    }
}

if (isset($_GET['install']) && $_GET['install'] === 'darkrp') {
    $success = $model->installGamemode(
        'https://github.com/FPtje/DarkRP/archive/refs/heads/master.zip',
        'darkrp'
    );
    $message = $success ? "DarkRP installé avec succès!" : "Échec de l'installation de DarkRP.";
    header('Location: ?path=garrysmod%2Fgamemodes');
    exit;
}

function breadcrumb(string $path): string {
    $parts = explode(DIRECTORY_SEPARATOR, $path);
    $accum = '';
    $crumbs = ['<a href="?">root</a>'];
    foreach ($parts as $part) {
        if ($part === '') continue;
        $accum .= ($accum === '' ? '' : DIRECTORY_SEPARATOR) . $part;
        $crumbs[] = '<a href="?path=' . urlencode($accum) . '">' . htmlspecialchars($part) . '</a>';
    }
    return implode(' / ', $crumbs);
}

function detectLanguage(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'php' => 'php',
        'js' => 'javascript',
        'json' => 'json',
        'html' => 'html',
        'css' => 'css',
        'lua' => 'lua',
        'txt' => 'plaintext',
        'md' => 'markdown',
        'sh' => 'shell',
        'py' => 'python',
        'xml' => 'xml',
    ];
    return $map[$ext] ?? 'plaintext';
}

// Gestion des comptes utilisateurs
if (isset($_POST['manage_account'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'] ?? null;
    $permissions = $_POST['permissions'] ?? [];

    if ($username !== '') {
        $model->addOrUpdateAccount($username, $password !== '' ? $password : null, $permissions);
        $message = "Compte utilisateur mis à jour.";
    }
}

if (isset($_POST['delete_account'])) {
    $username = trim($_POST['username']);
    if ($username !== '') {
        $model->deleteAccount($username);
        $message = "Compte supprimé.";
    }
}
$accounts = $model->getAllAccounts();
$permissionsList = [
    'start_server' => "Démarrer le serveur",
    'stop_server' => "Arrêter le serveur",
    'view_files' => "Voir les fichiers",
    'edit_files' => "Modifier les fichiers",
    'view_console' => "Voir la console",
    'send_rcon' => "Envoyer RCON",
    'manage_accounts' => "Gérer les comptes"
];

?>
