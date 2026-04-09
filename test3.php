<?php
header('Content-Type: application/json; charset=utf-8');

// Configuration
$config = require 'config.php';
$trelloConfig = [
    'key'   => $config['API_KEY'],
    'token' => $config['API_TOKEN']
];

// Vérification de la méthode
//if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    //$formParams = getCleanPostData();

    //if ($formParams) {
        $cardId     = '69c67edd488190ac5d552530'; //$formParams['cardId'];
        $cardAction = '69c67ee578b0318af047df65'; //$formParams['action'];
        
        // On passe la config en paramètre pour éviter les problèmes de portée
        $checkitemId = getCheckitemIdByName($cardId, $cardAction, $trelloConfig);

        if ($checkitemId) {
            setCheckItem($cardId, $checkitemId, $trelloConfig);
        } else {
            returnResponse(404, 'error', 'CheckitemId non trouvé');
        }
    //} else {
   //     returnResponse(400, 'error', 'Données manquantes (cardId ou action)');
    //}
//} else {
//    returnResponse(405, 'error', 'Méthode non autorisée');
//}

/**
 * Envoie une réponse JSON et arrête le script
 */
function returnResponse($code, $status, $message)
{
    http_response_code($code);
    echo json_encode([
        'status'  => $status,
        'message' => $message
    ]);
    exit; // Important pour stopper l'exécution
}

/**
 * Nettoyage des données POST
 */
function getCleanPostData() {
    $donnees_finales = [];

    foreach ($_POST as $cle => $valeur) {
        if (is_array($valeur)) {
            $donnees_finales[htmlspecialchars($cle)] = array_map('htmlspecialchars', $valeur);
        } else {
            $donnees_finales[htmlspecialchars($cle)] = htmlspecialchars($valeur);
        }
    }

    if (empty($donnees_finales['cardId']) || empty($donnees_finales['action'])) {
        return null; 
    }

    return [
        'cardId' => $donnees_finales['cardId'],
        'action' => $donnees_finales['action']
    ];
}

/**
 * Récupère l'ID d'un item dans une checklist par son nom
 */
function getCheckitemIdByName($cardId, $actionName, $config) {
    $url = "https://api.trello.com/1/cards/{$cardId}/checklists?key={$config['key']}&token={$config['token']}";
    
    $response = @file_get_contents($url);

    if ($response === false) {
        return null;
    }
    
    $checklists = json_decode($response, true);
    if (!$checklists || !is_array($checklists)) {
        return null;
    }

    foreach ($checklists as $checklist) {
        if (isset($checklist['checkItems'])) {
            foreach ($checklist['checkItems'] as $item) {
                if (trim($item['name']) === trim($actionName)) {
                    return $item['id'];
                }
            }
        }
    }

    return null;
}

/**
 * Coche l'item dans Trello
 */
function setCheckItem($cardId, $checkitemId, $config) {
    $url = "https://api.trello.com/1/cards/{$cardId}/checkItem/{$checkitemId}";
    
    $queryParams = http_build_query([
        'key'   => $config['key'],
        'token' => $config['token'],
        'state' => 'complete'
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '?' . $queryParams,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_SSL_VERIFYPEER => false, // Attention en production
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        returnResponse(500, 'error', "Erreur cURL : $error");
    } else {
        if ($httpCode === 200) {
            returnResponse(200, 'success', 'Item coché avec succès');
        } else {
            returnResponse($httpCode, 'error', "Trello a répondu avec le code $httpCode");
        }
    }
}