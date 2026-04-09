<?php
// checkitem.php

// 1. GESTION DES ERREURS & CONFIGURATION
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$config = require 'config3.php';

// Gestion de la session
ini_set('session.gc_maxlifetime', $config['SESSION_DURATION']);
ini_set('session.cookie_lifetime', $config['SESSION_DURATION']);
session_start();

// Vérification de l'authentification (Optionnel pour un script de test, mais recommandé)
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    die("Erreur : Accès non autorisé.");
}

// 2. RÉCUPÉRATION DES PARAMÈTRES
$TRELLO_KEY   = $config['API_KEY'];
$TRELLO_TOKEN = $config['API_TOKEN'];
$BASE_URL     = $config['BASE_URL'];

$cardId          = '69c67edd488190ac5d552530'; 
$checkItemId     = '69c67ee578b0318af047df65'; 
$nouvelEtat      = 'complete';

// 3. CONSTRUCTION DE L'APPEL API
// Utilisation de la BASE_URL centralisée
$url = "{$BASE_URL}cards/{$cardId}/checkItem/{$checkItemId}";

$queryParams = http_build_query([
    'key'   => $TRELLO_KEY,
    'token' => $TRELLO_TOKEN
]);

// 4. INITIALISATION DE CURL
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url . '?' . $queryParams,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PUT',
    // On passe l'état (complete/incomplete) dans le corps de la requête
    CURLOPT_POSTFIELDS => http_build_query(['state' => $nouvelEtat]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_SSL_VERIFYPEER => false, // Pour les environnements locaux sans certif SSL
]);

// 5. EXÉCUTION
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

// 6. AFFICHAGE DES RÉSULTATS (Format JSON pour débugging facile)
header('Content-Type: application/json');

if ($httpCode === 200) {
    echo json_encode([
        'status'  => 'success',
        'message' => 'Élément de checklist mis à jour avec succès.',
        'data'    => json_decode($response, true)
    ]);
} else {
    echo json_encode([
        'status'    => 'error',
        'httpCode'  => $httpCode,
        'curlError' => $error,
        'response'  => json_decode($response, true) ?: $response
    ]);
}