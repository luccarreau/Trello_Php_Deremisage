<?php
$config = require 'config3.php';

ini_set('session.gc_maxlifetime', $config['SESSION_DURATION']);
ini_set('session.cookie_lifetime', $config['SESSION_DURATION']);
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    if (isset($config['USERS'][$user]) && $config['USERS'][$user] === $pass) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $user;
        header('Location: admin3.php');
        exit;
    } else {
        $error = "Identifiants invalides";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <style>
        body { 
            font-family: 'Segoe UI', sans-serif; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0;
            background: #f4f7f6; 
        }
        .login-box { 
            background: white; 
            padding: 2.5rem 2rem; 
            border-radius: 12px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            width: 90%; 
            max-width: 380px;
            box-sizing: border-box;
        }
        h2 { 
            text-align: center; 
            color: #333; 
            margin-bottom: 2rem;
            font-size: 1.5rem;
        }
        input { 
            display: block; 
            width: 100%; 
            margin-bottom: 15px; 
            padding: 16px; /* Plus grand pour le tactile */
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1.5rem; /* Empêche le zoom automatique iOS */
            box-sizing: border-box;
        }
        input:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        button { 
            width: 100%; 
            padding: 16px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 1.2rem;
            font-weight: bold;
            transition: background 0.2s;
            margin-top: 10px;
        }
        button:hover { 
            background: #0056b3; 
        }
        .error-msg {
            color: #d93025;
            background: #fce8e6;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 1rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Accès restreint</h2>
        
        <?php if (isset($error)): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="user" placeholder="Utilisateur" required autocomplete="username">
            <input type="password" name="pass" placeholder="Mot de passe" required autocomplete="current-password">
            <button type="submit">Se connecter</button>
        </form>
    </div>
</body>
</html>