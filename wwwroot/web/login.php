<?php
session_start();
require_once 'model.php';

$model = new GDKSModel('/var/www/gmod');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $model->db->prepare("SELECT password, permissions, disabled FROM accounts WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && password_verify($password, $row['password'])) {
        if (!empty($row['disabled'])) {
            $error = "Ce compte est désactivé.";
        } else {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['permissions'] = json_decode($row['permissions'], true) ?: [];

            header('Location: index.php');
            exit;
        }
    } else {
        $error = "Identifiants invalides.";
    }
}


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Connexion - GDKS</title>
    <style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #121212;
    color: #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
    user-select: none;
    transition: background-color 0.3s ease, color 0.3s ease;
}

body.light-theme {
    background-color: #f5f7fa;
    color: #1a2732;
}


.login-container {
    background: #1e1e1e;
    padding: 40px 48px;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.7);
    width: 360px;
    text-align: center;
    user-select: text;
    transition: background-color 0.3s ease, color 0.3s ease;
}

body.light-theme .login-container {
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    color: #1a2732;
}


.login-container h1 {
    font-weight: 800;
    font-size: 2.4rem;
    margin-bottom: 24px;
    color: #90caf9;
    user-select: text;
}

body.light-theme .login-container h1 {
    color: #0d47a1;
}

.error {
    background-color: #b00020;
    color: white;
    padding: 12px 16px;
    margin-bottom: 24px;
    border-radius: 8px;
    font-weight: 700;
    user-select: text;
}

.login-container form {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.login-container input[type="text"],
.login-container input[type="password"] {
    padding: 14px 16px;
    font-size: 1rem;
    border-radius: 8px;
    border: 1.5px solid #333;
    background-color: #121212;
    color: #e0e0e0;
    transition: border-color 0.3s ease, background-color 0.3s ease;
    user-select: text;
}

.login-container input[type="text"]:focus,
.login-container input[type="password"]:focus {
    outline: none;
    border-color: #90caf9;
    background-color: #1e1e1e;
}

body.light-theme .login-container input[type="text"],
body.light-theme .login-container input[type="password"] {
    background-color: #fff;
    color: #1a2732;
    border: 1.5px solid #ccc;
}

body.light-theme .login-container input[type="text"]:focus,
body.light-theme .login-container input[type="password"]:focus {
    border-color: #0d47a1;
    background-color: #f0f4fb;
}

.login-container button {
    background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
    border: none;
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    padding: 14px 0;
    border-radius: 10px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(13, 71, 161, 0.5);
    user-select: none;
    transition: background 0.3s ease, box-shadow 0.3s ease;
}

.login-container button:hover {
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
    box-shadow: 0 6px 16px rgba(21, 101, 192, 0.7);
}

.login-container button:active {
    background: linear-gradient(135deg, #124a9e 0%, #0a3b81 100%);
    box-shadow: 0 2px 8px rgba(10, 59, 129, 0.8);
    transform: translateY(1px);
}

.login-container button:focus-visible {
    outline: 3px solid #90caf9;
    outline-offset: 2px;
}

.login-container input::placeholder {
    color: #999;
}

body.light-theme .login-container input::placeholder {
    color: #666;
}

    </style>
</head>
<body>
    <main class="login-container">
        <h1>Connexion</h1>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Nom d'utilisateur" required />
            <input type="password" name="password" placeholder="Mot de passe" required />
            <button type="submit">Se connecter</button>
        </form>
    </main>
</body>
</html>
