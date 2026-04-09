<?php
// 1. FORCER L'AFFICHAGE DES ERREURS (Pour voir ce qui plante)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$config = require 'config.php';

// 2. CONFIGURATION DES IDS
$TRELLO_KEY      = $config['API_KEY'] ?? null;
$TRELLO_TOKEN    = $config['API_TOKEN'] ?? null;
$cardId          = '69c67edd488190ac5d552530'; 
$checkItemId     = '69c67ee578b0318af047df65'; 
$nouvelEtat      = 'complete';

// 3. CONSTRUCTION DE L'APPEL
$url = "https://api.trello.com/1/cards/{$cardId}/checkItem/{$checkItemId}";
$queryParams = http_build_query([
    'key'   => $TRELLO_KEY,
    'token' => $TRELLO_TOKEN
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url . '?' . $queryParams,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_POSTFIELDS => http_build_query(['state' => $nouvelEtat]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    // Option de secours si vous êtes en local (WAMP/MAMP) :
    CURLOPT_SSL_VERIFYPEER => false, 
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

// 6. RÉSULTAT
if ($error) {
    echo "❌ Erreur cURL : " . $error . "\n";
} else {
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "✅ SUCCÈS ! L'item est maintenant : " . ($data['state'] ?? 'inconnu') . "\n";
    } elseif ($httpCode === 404) {
        echo "❌ ERREUR 404 : Trello ne trouve pas la ressource.\n";
        echo "Cela signifie que l'ID de la CARTE ou l'ID de l'ITEM est faux.\n";
    } else {
        echo "❌ ERREUR HTTP {$httpCode} :\n";
        echo $response . "\n";
    }
}
echo "</pre>";