<?php
// Charger la configuration existante
$config = require 'config3.php';

// 1. Définir les paramètres du Webhook
$callbackURL = "https://sdmsports.com/webhook3.php"; // L'URL de votre récepteur
$idModel = $config['BOARD_ID']; // L'ID du tableau à surveiller
$description = "Webhook Atelier SDM Sports";

// 2. Préparer l'URL de création de l'API Trello
$url = "https://api.trello.com/1/webhooks/";

$queryParams = http_build_query([
    'key'         => $config['API_KEY'],
    'token'       => $config['API_TOKEN'],
    'callbackURL' => $callbackURL,
    'idModel'     => $idModel,
    'description' => $description
]);

// 3. Initialiser cURL pour envoyer la requête
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url . '?' . $queryParams,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

// 4. Affichage du résultat
header('Content-Type: application/json');
if ($error) {
    echo json_encode(['status' => 'error', 'message' => $error]);
} else {
    $result = json_decode($response, true);
    if ($httpCode === 200) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Webhook enregistré avec succès !',
            'webhook_id' => $result['id']
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'code' => $httpCode, 
            'message' => $result
        ]);
    }
}
?>