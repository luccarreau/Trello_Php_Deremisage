<?php
$config = require 'config3.php';

ini_set('session.gc_maxlifetime', $config['SESSION_DURATION']);
ini_set('session.cookie_lifetime', $config['SESSION_DURATION']);
session_start();

// Si l'utilisateur n'est pas authentifié, on le redirige vers login.php
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: loginemp.php');
    exit;
}


header('Content-Type: application/json; charset=utf-8');

// Configuration
$config = require 'config3.php';
$trelloConfig = [
    'key'   => $config['API_KEY'],
    'token' => $config['API_TOKEN'],
    'boardId' => $config['BOARD_ID'],
    'listparkinglot'  => $config['LIST_PARKINGLOT'],
    'listsemaine'     => $config['LIST_SEMAINE'],
    'listmecanique'   => $config['LIST_MECANIQUE'],
    'listenattente'   => $config['LIST_ENATTENTE'],
    'listlavage'      => $config['LIST_LAVAGE'],
    'listinspection'  => $config['LIST_INSPECTION'],
    'listpretaliver'  => $config['LIST_PRETALIVER'],
    'listlivre'       => $config['LIST_LIVRE'],
];

// Vérification de la méthode
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    //Controller ADMIN
    if (isset($_POST['adminAction'])) {
        
        switch ($_POST['adminAction']) {
            case 'initChecklist':
                $total = addCheckListAFaire($trelloConfig);
                returnResponse(200, 'success', "Opération terminée : {$total} cartes traitées.");
                break;

            case 'sortCards':
                // On trie la liste "En Attente" définie dans votre config
                $total = 0;
                $total += sortListByDueDate($trelloConfig['listsemaine'], $trelloConfig);
                $total += sortListByDueDate($trelloConfig['listmecanique'], $trelloConfig);
                $total += sortListByDueDate($trelloConfig['listenattente'], $trelloConfig);
                $total += sortListByDueDate($trelloConfig['listlavage'], $trelloConfig);
                $total += sortListByDueDate($trelloConfig['listinspection'], $trelloConfig);
                returnResponse(200, 'success', "{$total} cartes ont été triées par date d'échéance.");
                break;
                
            case 'getPrintData':
                $data = getPrintData($trelloConfig);
                echo json_encode(['status' => 'success', 'data' => $data]);
                exit;

            default:
                returnResponse(400, 'error', "Action administrative inconnue.");
                break;
        }
        exit; // On arrête l'exécution ici pour les actions admin
    }
    
    //Controller OPERATION
    if (isset($_POST['operationAction'])) {
        
        $formParams = getCleanPostData();
        if ($formParams) {
            $cardId     = $formParams['cardId'];
            $cardAction = $formParams['action'];
            
            switch ($_POST['operationAction']) {
                case 'applyAction':
                    applyAction($cardId, $cardAction, $trelloConfig);
                    break;
                    
                default:
                    returnResponse(400, 'error', "Action d'operation inconnue.");
                    break;
            }
        } else {
            returnResponse(400, 'error', 'Données manquantes (cardId ou action)');
        }

        exit; // On arrête l'exécution ici pour les actions admin
    }
    
} else {
    returnResponse(405, 'error', 'Méthode non autorisée');
}

function applyAction($cardId, $cardAction, $config)
{
    switch ($cardAction) {
        case 'deshrink':
            $result = checkAndMoveCard($cardId, $cardAction, $config['listmecanique'], $config);
            break;
            
        case 'mecanique':
            $result = checkAndMoveCard($cardId, $cardAction, $config['listlavage'], $config);
            break;
        
        case 'mecanique_standby':
            $result = moveCardToList($cardId, $config['listenattente'], $config);
            break;
            
        case 'lavage':
            $result = checkAndMoveCard($cardId, $cardAction, $config['listinspection'], $config);
            break;

        default:
            returnResponse(400, 'error', "Action non reconnue : {$cardAction}");
            return;
    }

    returnResponse($result['code'], $result['status'], $result['message']);
}

function checkAndMoveCard($cardId, $cardAction, $listId, $config)
{
    $checkitemId = getCheckitemIdByName($cardId, $cardAction, $config);
    if ($checkitemId) {
        $result = setCheckItem($cardId, $checkitemId, $config);
        if ($result['status'] === 'error') {
            return $result;
        }
        return moveCardToList($cardId, $listId, $config);

    } else {
        return [
            'status' => 'error', 
            'code' => 404, 
            'message' => "Impossible de trouver l'élément de checklist nommé : {$cardAction}"
        ];
    }
}

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
    
    $mapping = [
        'deshrink'  => 'De-Shrink',
        'mecanique' => 'Mécanique',
        'lavage'    => 'Lavage'
    ];

    $checkAction = $mapping[$actionName] ?? 'Action inconnue';

    foreach ($checklists as $checklist) {
        if (isset($checklist['checkItems'])) {
            foreach ($checklist['checkItems'] as $item) {
                if (trim($item['name']) === $checkAction) {
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
        return ['status' => 'error', 'code' => $httpCode, 'message' => "Erreur cURL : {$error}"];
    } else {
        if ($httpCode === 200) {
            return ['status' => 'success', 'code' => $httpCode, 'message' => 'Carte déplacée avec succès'];
        } else {
            return ['status' => 'error', 'code' => $httpCode, 'message' => "Trello a répondu avec le code {$httpCode}"];
        }
    }
}

function moveCardToList($cardId, $idListDest, $config) {
    $url = "https://api.trello.com/1/cards/{$cardId}";
    
    $queryParams = http_build_query([
        'key'    => $config['key'],
        'token'  => $config['token'],
        'idList' => $idListDest,
        'pos'    => 'top'
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '?' . $queryParams,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_SSL_VERIFYPEER => false, 
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['status' => 'error', 'code' => $httpCode, 'message' => "Erreur cURL : {$error}"];
    } else {
        if ($httpCode === 200) {
            return ['status' => 'success', 'code' => $httpCode, 'message' => 'Carte déplacée avec succès'];
        } else {
            return ['status' => 'error', 'code' => $httpCode, 'message' => "Trello a répondu avec le code {$httpCode}"];
        }
    }
}

function addCheckListAFaire($config) {
    // 1. Aller chercher toutes les cartes du tableau
    $url = "https://api.trello.com/1/boards/{$config['boardId']}/cards?key={$config['key']}&token={$config['token']}&fields=id";
    
    $response = @file_get_contents($url);
    $cards = $response ? json_decode($response, true) : [];

    if (empty($cards)) {
        return 0; // Aucune carte trouvée
    }

    $count = 0;
    // 2. Pour chaque carte, appeler la fonction d'initialisation de checklist
    foreach ($cards as $card) {
        addCheckListAFaireCard($card['id'], $config);
        $count++;
    }

    return $count; // Retourne le nombre de cartes traitées
}

function addCheckListAFaireCard($cardId, $config) {
    $checklistName = "À faire";
    $items = ["De-Shrink", "Mécanique", "Lavage", "Inspection"];

    // 1. Vérifier si la checklist existe déjà
    $urlGet = "https://api.trello.com/1/cards/{$cardId}/checklists?key={$config['key']}&token={$config['token']}";
    $response = @file_get_contents($urlGet);
    $checklists = $response ? json_decode($response, true) : [];

    foreach ($checklists as $checklist) {
        if (trim($checklist['name']) === $checklistName) {
            return; // Elle existe déjà, on ne fait rien
        }
    }

    // 2. Créer la checklist si elle n'existe pas
    $urlPost = "https://api.trello.com/1/checklists";
    $queryChecklist = http_build_query([
        'idCard' => $cardId,
        'name'   => $checklistName,
        'key'    => $config['key'],
        'token'  => $config['token']
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $urlPost . '?' . $queryChecklist,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $resChecklist = json_decode(curl_exec($ch), true);
    $checklistId = $resChecklist['id'] ?? null;

    // 3. Ajouter les items à la nouvelle checklist
    if ($checklistId) {
        foreach ($items as $itemName) {
            $queryItem = http_build_query([
                'name'  => $itemName,
                'key'   => $config['key'],
                'token' => $config['token']
            ]);
            curl_setopt($ch, CURLOPT_URL, "https://api.trello.com/1/checklists/{$checklistId}/checkItems?" . $queryItem);
            curl_exec($ch);
        }
    }
    curl_close($ch);
}

/**
 * Trie les cartes d'une liste spécifique par date d'échéance (Due Date)
 */
function sortListByDueDate($listId, $config) {
    // 1. Récupérer les cartes de la liste avec le champ 'due'
    $url = "https://api.trello.com/1/lists/{$listId}/cards?key={$config['key']}&token={$config['token']}&fields=id,due,name";
    $response = @file_get_contents($url);
    $cards = $response ? json_decode($response, true) : [];

    if (empty($cards)) return 0;

    // 2. Trier le tableau en PHP
    usort($cards, function($a, $b) {
        $dateA = $a['due'] ? strtotime($a['due']) : PHP_INT_MAX; // Pas de date = à la fin
        $dateB = $b['due'] ? strtotime($b['due']) : PHP_INT_MAX;
        
        if ($dateA == $dateB) return 0;
        return ($dateA < $dateB) ? -1 : 1;
    });

    // 3. Mettre à jour la position de chaque carte sur Trello
    $count = 0;
    $ch = curl_init();
    foreach ($cards as $index => $card) {
        // La position 'pos' peut être un nombre. On utilise l'index du tri.
        $newPos = $index + 1; 
        $urlUpdate = "https://api.trello.com/1/cards/{$card['id']}?key={$config['key']}&token={$config['token']}&pos={$newPos}";
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $urlUpdate,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        curl_exec($ch);
        $count++;
    }
    curl_close($ch);

    return $count;
}

/**
 * Récupère le contenu des listes pour l'impression
 */
function getPrintData($config) {
    $listsToPrint = [
        'PARKING LOT'                   => $config['listparkinglot']
        'SEMAINE prêt pour de-shrink'   => $config['listsemaine'],
        'PRÊT POUR MÉCANIQUE'           => $config['listmecanique'],
        'EN ATTENTE'                    => $config['listenattente'],
        'PRÊT POUR LAVAGE'              => $config['listlavage'],
        'PRÊT POUR INSPECTION'          => $config['listinspection'],
        'PRÊT À LIVRER'                 => $config['listpretaliver']
    ];

    $printData = [];

    foreach ($listsToPrint as $label => $listId) {
        $url = "https://api.trello.com/1/lists/{$listId}/cards?key={$config['key']}&token={$config['token']}&fields=name";
        $response = @file_get_contents($url);
        $cards = $response ? json_decode($response, true) : [];
        
        $printData[$label] = $cards;
    }

    return $printData;
}

?>