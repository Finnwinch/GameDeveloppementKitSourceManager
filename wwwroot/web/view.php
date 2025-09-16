<?php

$availableGamemodes = [
    'darkrp' => [
        'name' => 'DarkRP',
        'url' => 'https://github.com/FPtje/DarkRP/archive/refs/heads/master.zip'
    ],
    'murder' => [
        'name' => 'Murder',
        'url' => 'https://github.com/MechanicalMind/murder/archive/refs/heads/master.zip'
    ],
    'prophunt' => [
        'name' => 'Prop Hunt',
        'url' => 'https://github.com/prop-hunt-enhanced/prop-hunt-enhanced/archive/refs/heads/master.zip'
    ]
];

$message = '';
$showComptePopup = false;

if (isset($_GET['install']) && array_key_exists($_GET['install'], $availableGamemodes)) {
    if (hasPermission('install_gamemodes')) {
        $key = $_GET['install'];
        $success = $model->installGamemode($availableGamemodes[$key]['url'], $key);
        $message = $success
            ? $availableGamemodes[$key]['name'] . " installé avec succès !"
            : "Échec de l'installation de " . $availableGamemodes[$key]['name'];
    } else {
        $message = "Permission refusée pour installer un gamemode.";
    }
    header('Location: ?path=garrysmod%2Fgamemodes&msg=' . urlencode($message));
    exit;
}

if (isset($_POST['delete_gamemode']) && array_key_exists($_POST['delete_gamemode'], $availableGamemodes)) {
    if (hasPermission('install_gamemodes')) {
        $folder = $_POST['delete_gamemode'];
        $path = $model->fullPath("garrysmod/gamemodes/{$folder}");
        if ($path && is_dir($path)) {
            $success = $model->deleteDir($path);
            $message = $success
                ? "Gamemode supprimé avec succès: {$folder}"
                : "Échec de la suppression de {$folder}";
        }
    } else {
        $message = "Permission refusée pour supprimer un gamemode.";
    }
    header('Location: ?path=garrysmod%2Fgamemodes&msg=' . urlencode($message));
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (isset($_POST['compte_btn']) && hasPermission('manage_accounts')) {
    $showComptePopup = true;
}

function hasPermission(string $perm): bool {
    if (!isset($_SESSION['permissions'])) return false;
    if (in_array('all', $_SESSION['permissions'])) return true;
    return in_array($perm, $_SESSION['permissions']);
}

if (isset($_POST['toggle_disable_account']) && isset($_POST['username'])) {
    $username = $_POST['username'];
    $currentState = $accounts[$username]['disabled'] ?? false;

    $model->setAccountDisabled($username, !$currentState);

    if (!$currentState && $_SESSION['username'] === $username) {
        unset($_SESSION['username']);
        header('Location: login.php');
        exit;
    }
}

$accounts = [];
if ($showComptePopup) {
    $accounts = $model->getAllAccounts();
}

$permissionsList = [
    'all' => 'Accès total',
    'manage_accounts' => 'Gérer les comptes',
    'install_gamemodes' => 'Installer/Supprimer Gamemodes',
    'start_stop_server' => 'Démarrer/Arrêter le serveur',
    'edit_files' => 'Modifier les fichiers',
    'view_files' => 'Voir fichiers/dossiers',
    'send_rcon_commands' => 'Envoyer commandes RCON',
];

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>GMod Web Controller - Gestion fichiers</title>
    <link rel="stylesheet" href="style.css" />
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.43.0/min/vs/loader.js"></script>
    <script src="script.js"></script>
</head>
<body
    <?php if (!empty($editingFile)): ?>
        data-editing-file="<?= htmlspecialchars($editingFile, ENT_QUOTES) ?>"
        data-file-content="<?= htmlspecialchars(base64_encode($fileContent), ENT_QUOTES) ?>"
        data-file-language="<?= htmlspecialchars(detectLanguage($editingFile), ENT_QUOTES) ?>"
    <?php endif; ?>
>
<nav>
    <ul>
        <?php if (hasPermission('view_files')): ?>
            <?php foreach ($dirs as $d): ?>
                <li class="folder"><a href="?path=<?= urlencode($d['path']) ?>"><?= htmlspecialchars($d['name']) ?></a></li>
            <?php endforeach; ?>
            <?php foreach ($files as $f): ?>
                <?php if (hasPermission('edit_files')): ?>
                    <li class="file"><a href="?path=<?= urlencode($requestedPath) ?>&edit=<?= urlencode($f['path']) ?>"><?= htmlspecialchars($f['name']) ?></a></li>
                <?php else: ?>
                    <li class="file"><?= htmlspecialchars($f['name']) ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <li>Accès fichiers refusé.</li>
        <?php endif; ?>
    </ul>
</nav>

<main>
    <div id="header-container" class="header-toolbar">
        <h1>GDKS Manager</h1>
        <div class="header-actions">
            <button id="toggleThemeBtn" type="button" aria-label="Changer thème">🌙 Mode Sombre</button>
            <button id="toggleIpBtn" type="button">🛡️ Masquer IP</button>
            <button disabled title="Fonction FTP non disponible pour le moment">🔌 SSHFS</button>
            <?php if (hasPermission('manage_accounts')): ?>
            <form id="compte-control" method="POST">
                <button name="compte_btn">👤 Compte</button>
            </form>
            <?php endif; ?>
            <a href="?logout=1" class="logout-btn">🔓 Déconnexion</a>
        </div>
        <?php if (hasPermission('start_stop_server')): ?>
        <form id="server-control" method="POST">
            <button id="btnStart" name="action" value="start">▶️ Démarrer</button>
            <button id="btnStop" name="action" value="stop">⏹️ Arrêter</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="path-display" title="<?= htmlspecialchars($requestedPath) ?>">
        <?= htmlspecialchars($requestedPath) ?>
    </div>

    <?php if (rtrim($requestedPath, '/') === 'garrysmod/gamemodes' && hasPermission('install_gamemodes')): ?>
    <div class="gamemode-grid">
        <?php foreach ($availableGamemodes as $key => $gm): 
            $installed = $model->isGamemodeInstalled($key);
        ?>
            <form method="GET" class="gamemode-form" onsubmit="return confirmDeletion(event, '<?= $key ?>', <?= $installed ? 'true' : 'false' ?>)">
                <input type="hidden" name="path" value="<?= htmlspecialchars($requestedPath) ?>" />
                <?php if ($installed): ?>
                    <button type="button" class="installed" title="Double-clic pour supprimer"
                        ondblclick="confirmDeleteGamemode('<?= $key ?>')">
                        📦 <?= $gm['name'] ?> déjà installé
                    </button>
                <?php else: ?>
                    <button type="submit" name="install" value="<?= htmlspecialchars($key) ?>">
                        📦 Installer <?= $gm['name'] ?>
                    </button>
                <?php endif; ?>
            </form>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($message) && empty($_SERVER['HTTP_X_REQUESTED_WITH'])): ?>
        <div id="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!empty($editingFile) && hasPermission('edit_files')): ?>
        <div id="fileEditorPopup">
            <div id="fileEditorHeader">
                <div>Édition du fichier : <?= htmlspecialchars($editingFile) ?></div>
                <button id="closeEditorBtn" title="Fermer">✖</button>
            </div>
            <div id="fileEditorContainer">
                <div id="editor"></div>
                <button id="saveBtn">💾 Sauvegarder</button>
            </div>
        </div>
    <?php endif; ?>

    <h2>Console</h2>
    <pre id="consoleOutput"></pre>
    <?php if (hasPermission('send_rcon_commands')): ?>
    <div id="cmd-container">
        <input type="text" id="cmdInput" placeholder="Commande RCON (ex: lua_run print('hello world'))" />
        <button id="cmdSend">Envoyer</button>
    </div>
    <?php endif; ?>
</main>

<div id="deleteModal" class="modal" style="display:none;">
  <div class="modal-content">
    <p id="deleteModalText">Confirmer la suppression ?</p>
    <form method="POST">
      <input type="hidden" name="delete_gamemode" id="deleteGamemodeInput" />
      <button type="submit" class="btn-delete">🗑️ Oui, supprimer</button>
      <button type="button" onclick="closeDeleteModal()" class="btn-cancel">Annuler</button>
    </form>
  </div>
</div>

<?php if ($showComptePopup && hasPermission('manage_accounts')): ?>
<?php
$currentUser = $_SESSION['username'] ?? '';
$isCurrentUserAdmin = in_array('all', $_SESSION['permissions'] ?? []);
?>

<div id="compteModal" class="modal-overlay">
  <div class="modal-content compte-popup" style="max-width:700px;">

    <h2>Gestion des Comptes <button onclick="closeCompteModal()" class="close-btn">✖</button></h2>

    <!-- Liste des comptes existants -->
    <table border="1" cellpadding="5" cellspacing="0" style="width:100%; margin-bottom:20px; border-collapse: collapse;">
    <thead>
    <tr style="background:#eee;">
        <th>Utilisateur</th>
        <th>Permissions</th>
        <th>Statut</th>
        <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($accounts as $name => $info): ?>
        <tr>
            <td><?= htmlspecialchars($name) ?></td>
            <td><?= implode(', ', array_map(fn($perm) => $permissionsList[$perm] ?? $perm, $info['permissions'] ?? [])) ?></td>
            <td><?= !empty($info['disabled']) ? '<span style="color:red;">Désactivé</span>' : '<span style="color:green;">Activé</span>' ?></td>
            <td>
                <?php if ($name !== 'admin'): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($name) ?>">
                    <button name="delete_account" onclick="return confirm('Supprimer ce compte ?')">❌ Supprimer</button>
                </form>

                <form method="POST" style="display:flex;flex-direction:rows; margin-left:8px;">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($name) ?>">
                    <button name="toggle_disable_account" type="submit">
                        <?= !empty($info['disabled']) ? '🔓 Activer' : '🚫 Désactiver' ?>
                    </button>
                </form>
                <?php else: ?>
                    <em>Compte protégé</em>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    </table>

    <!-- Formulaire de création/modification -->
    <form method="POST" style="border:1px solid #ccc; padding:20px; border-radius:8px; background:#fafafa;" autocomplete="off">
        <input type="hidden" name="manage_account" value="1">

        <div style="display:flex; gap: 2rem; margin-bottom:1rem; flex-wrap: wrap;">
            <div style="flex:1; min-width:250px;">
                <label for="username">Nom d'utilisateur :</label><br>
                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"
                    <?= !$isCurrentUserAdmin ? 'readonly' : '' ?>
                    placeholder="Nom d'utilisateur"
                >
            </div>

            <div style="flex:1; min-width:250px; position:relative;">
                <label for="password">Mot de passe :</label><br>
                <input
                    type="password"
                    id="password"
                    name="password"
                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"
                    placeholder="Nouveau mot de passe"
                >
                <button type="button" id="keepPasswordBtn" title="Garder le mot de passe actuel"
                    style="
                        position: absolute;
                        right: 5px;
                        top: 33px;
                        padding: 6px 10px;
                        background-color: #4caf50;
                        border: none;
                        color: white;
                        border-radius: 5px;
                        cursor: pointer;
                        font-size: 0.9rem;
                    ">💾 Garder le même</button>
            </div>
        </div>

        <fieldset
            style="border:1px solid #ccc; padding: 10px 15px; border-radius: 6px; max-height: 180px; overflow-y: auto;"
            <?= !$isCurrentUserAdmin ? 'disabled' : '' ?>
        >
            <legend style="font-weight:bold; margin-bottom:8px;">Permissions :</legend>

            <?php foreach ($permissionsList as $key => $label): ?>
                <?php 
                    $checked = false;
                    if (isset($accounts[$currentUser]['permissions'])) {
                        $checked = in_array($key, $accounts[$currentUser]['permissions']);
                    }
                ?>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-bottom:6px;">
                    <input
                        type="checkbox"
                        name="permissions[]"
                        value="<?= htmlspecialchars($key) ?>"
                        <?= $checked ? 'checked' : '' ?>
                        <?= ($key === 'all' && $currentUser !== 'admin') ? 'disabled' : '' ?>
                    >
                    <?= htmlspecialchars($label) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <br>
        <button type="submit" style="padding:10px 20px; background:#007bff; border:none; color:white; border-radius:5px; cursor:pointer;">
            💾 Enregistrer
        </button>
    </form>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const keepBtn = document.getElementById('keepPasswordBtn');
    const passwordInput = document.getElementById('password');

    keepBtn?.addEventListener('click', () => {
        passwordInput.value = '';
        passwordInput.focus();
    });
});
</script>

<?php endif; ?>

<div id="loadingOverlay" style="display:none;">
    <div class="loader">⏳ Je charge la baril de frite...</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.closeCompteModal = function () {
        document.getElementById('compteModal').style.display = 'none';
    };

    window.closeDeleteModal = function () {
        document.getElementById('deleteModal').style.display = 'none';
    };

    window.confirmDeleteGamemode = function (key) {
        const modal = document.getElementById('deleteModal');
        const input = document.getElementById('deleteGamemodeInput');
        input.value = key;
        modal.style.display = 'block';
    };
});
</script>

</body>
</html>
