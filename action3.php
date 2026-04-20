<?php
// action3.php
$config = require 'config3.php';

// Configuration de la session
ini_set('session.gc_maxlifetime', $config['SESSION_DURATION']);
ini_set('session.cookie_lifetime', $config['SESSION_DURATION']);
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: loginadm.php');
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Configuration Trello
$trelloConfig = [
    'key'             => $config['API_KEY'],
    'token'           => $config['API_TOKEN'],
    'baseUrl'         => $config['BASE_URL'],
    'boardId'         => $config['BOARD_ID'],
    'listparkinglot'  => $config['LIST_PARKINGLOT'],
    'listsemaine'     => $config['LIST_SEMAINE'],
    'listmecanique'   => $config['LIST_MECANIQUE'],
    'listenattente'   => $config['LIST_ENATTENTE'],
    'listlavage'      => $config['LIST_LAVAGE'],
    'listinspection'  => $config['LIST_INSPECTION'],
    'listpretalivrer' => $config['LIST_PRETALIVRER'],
    'listlivre'        => $config['LIST_LIVRE']
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // --- Contrôleur ADMIN ---
    if (isset($_POST['adminAction'])) {
        switch ($_POST['adminAction']) {
            case 'initChecklist':
                $total = addCheckListAFaire($trelloConfig);
                returnResponse(200, 'success', "Opération terminée : {$total} cartes traitées.");
                break;

            case 'sortCards':
                $total = 0;
                $listsToSort = ['listsemaine', 'listmecanique', 'listenattente', 'listlavage', 'listinspection'];
                foreach ($listsToSort as $listKey) {
                    $total += sortListByDueDate($trelloConfig[$listKey], $trelloConfig);
                }
                returnResponse(200, 'success', "{$total} cartes ont été triées.");
                break;
                
            case 'getPrintDataService':
                $lists = [
                    'SEMAINE prêt pour de-shrink' => $trelloConfig['listsemaine'],
                    'PRÊT POUR MÉCANIQUE'         => $trelloConfig['listmecanique'],
                    'EN ATTENTE'                  => $trelloConfig['listenattente'],
                    'PRÊT POUR LAVAGE'            => $trelloConfig['listlavage'],
                    'PRÊT POUR INSPECTION'        => $trelloConfig['listinspection'],
                    'PRÊT À LIVRER'               => $trelloConfig['listpretalivrer']
                ];
                getPrintData($lists, $trelloConfig);
                break;

            case 'getPrintDataParking':
                getPrintData(['PARKING LOT' => $trelloConfig['listparkinglot']], $trelloConfig);
                break;

            case 'getPrintDataLivrer':
                getPrintData(['LIVRÉ' => $trelloConfig['listlivre']], $trelloConfig);
                break;

            default:
                returnResponse(400, 'error', "Action administrative inconnue.");
        }
    }
    
    // --- Contrôleur OPÉRATION ---
    if (isset($_POST['operationAction'])) {
        $formParams = getCleanPostData();
        if ($formParams && $_POST['operationAction'] === 'applyAction') {
            $result = applyAction($formParams['cardId'], $formParams['action'], $trelloConfig);
            returnResponse($result['code'], $result['status'], $result['message']);
        } else {
            returnResponse(400, 'error', 'Données manquantes ou action invalide');
        }
    }
} else {
    returnResponse(405, 'error', 'Méthode non autorisée');
}

/**
 * Fonctions de traitement
 */

function applyAction($cardId, $cardAction, $config) {
    $checkListName = 'À faire';
    
    $checkItems = getCheckListItems($cardId, $checkListName, $config);
    if ($checkItems === null) {
        return ['status' => 'error', 'code' => 404, 'message' => "Checklist '{$checkListName}' introuvable."];
    }

    $checkitemId = getCheckitemIdByName($checkItems, $cardAction);
    if ($checkitemId === null && $cardAction !== 'mecanique_standby') {
        return ['status' => 'error', 'code' => 404, 'message' => "Élément '{$cardAction}' introuvable dans la checklist."];
    }

    switch ($cardAction) {
        case 'deshrink':
            setCheckItem($cardId, $checkitemId, $config);
            return moveCardToList($cardId, $config['listmecanique'], $config);
            
        case 'mecanique':
        case 'lavage':
            setCheckItem($cardId, $checkitemId, $config);
            
            // Recharger/analyser les items pour décider du mouvement
            $mecaniqueDone = false;
            $lavageDone = false;

            // Note : Pour être 100% précis, il faudrait refaire un appel API ici 
            // ou mettre à jour localement $checkItems. Ici on met à jour localement :
            foreach ($checkItems as &$item) {
                $name = trim($item['name']);
                if ($name === 'Mécanique' && ($cardAction === 'mecanique' || $item['state'] === 'complete')) $mecaniqueDone = true;
                if ($name === 'Lavage' && ($cardAction === 'lavage' || $item['state'] === 'complete')) $lavageDone = true;
            }
            
            if ($mecaniqueDone && $lavageDone) {
                return moveCardToList($cardId, $config['listinspection'], $config);
            } elseif ($mecaniqueDone) {
                return moveCardToList($cardId, $config['listlavage'], $config);
            } elseif ($lavageDone) {
                return moveCardToList($cardId, $config['listmecanique'], $config);
            }
            return ['status' => 'success', 'code' => 200, 'message' => 'Item coché, mais aucun déplacement requis.'];

        case 'mecanique_standby':
            return moveCardToList($cardId, $config['listenattente'], $config);
            
        default:
            return ['status' => 'error', 'code' => 400, 'message' => "Action non reconnue : {$cardAction}"];
    }
}

function returnResponse($code, $status, $message) {
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

function getCleanPostData() {
    if (empty($_POST['cardId']) || empty($_POST['action'])) return null;
    return [
        'cardId' => htmlspecialchars($_POST['cardId']),
        'action' => htmlspecialchars($_POST['action'])
    ];
}

function getCheckListItems($cardId, $checkListName, $config) {
    $url = "{$config['baseUrl']}cards/{$cardId}/checklists?key={$config['key']}&token={$config['token']}";
    $response = @file_get_contents($url);
    if ($response === false) return null;
    
    $checklists = json_decode($response, true);
    if (!is_array($checklists)) return null;

    foreach ($checklists as $checklist) {
        if (isset($checklist['name']) && trim($checklist['name']) === $checkListName) {
            return $checklist['checkItems'];
        }
    }
    return null;
}

function getCheckitemIdByName($checkItems, $actionKey) {
    $mapping = ['deshrink' => 'De-Shrink', 'mecanique' => 'Mécanique', 'lavage' => 'Lavage', 'inspection' => 'Inspection'];
    $targetName = $mapping[$actionKey] ?? '';

    foreach ($checkItems as $item) {
        if (trim($item['name']) === $targetName) return $item['id'];
    }
    return null;
}

function setCheckItem($cardId, $checkitemId, $config) {
    $url = "{$config['baseUrl']}cards/{$cardId}/checkItem/{$checkitemId}";
    $queryParams = http_build_query(['key' => $config['key'], 'token' => $config['token'], 'state' => 'complete']);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '?' . $queryParams,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode === 200);
}

function moveCardToList($cardId, $idListDest, $config) {
    if (!$idListDest) return ['status' => 'error', 'code' => 400, 'message' => 'ID de liste de destination manquant'];

    $url = "{$config['baseUrl']}cards/{$cardId}";
    $queryParams = http_build_query(['key' => $config['key'], 'token' => $config['token'], 'idList' => $idListDest, 'pos' => 'top']);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '?' . $queryParams,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode === 200) 
        ? ['status' => 'success', 'code' => 200, 'message' => 'Carte mise à jour et déplacée'] 
        : ['status' => 'error', 'code' => $httpCode, 'message' => 'Erreur lors du déplacement'];
}

// ... (Le reste des fonctions addCheckListAFaire, sortListByDueDate, getPrintData semble correct)

function addCheckListAFaire($config) {
    $url = "{$config['baseUrl']}boards/{$config['boardId']}/cards?key={$config['key']}&token={$config['token']}&fields=id";
    $response = @file_get_contents($url);
    $cards = $response ? json_decode($response, true) : [];
    $count = 0;
    foreach ($cards as $card) {
        addCheckListAFaireCard($card['id'], $config);
        $count++;
    }
    return $count;
}

function addCheckListAFaireCard($cardId, $config) {
    $checklistName = "À faire";
    $items = ["De-Shrink", "Mécanique", "Lavage", "Inspection"];

    $urlGet = "{$config['baseUrl']}cards/{$cardId}/checklists?key={$config['key']}&token={$config['token']}";
    $checklists = json_decode(@file_get_contents($urlGet), true) ?: [];

    foreach ($checklists as $checklist) {
        if (trim($checklist['name']) === $checklistName) return;
    }

    $urlPost = "{$config['baseUrl']}checklists";
    $query = http_build_query(['idCard' => $cardId, 'name' => $checklistName, 'key' => $config['key'], 'token' => $config['token']]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $urlPost.'?'.$query, CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_SSL_VERIFYPEER => false]);
    $res = json_decode(curl_exec($ch), true);
    $checklistId = $res['id'] ?? null;

    if ($checklistId) {
        foreach ($items as $itemName) {
            $qItem = http_build_query(['name' => $itemName, 'key' => $config['key'], 'token' => $config['token']]);
            curl_setopt($ch, CURLOPT_URL, "{$config['baseUrl']}checklists/{$checklistId}/checkItems?".$qItem);
            curl_exec($ch);
        }
    }
    curl_close($ch);
}

function sortListByDueDate($listId, $config) {
    if (!$listId) return 0;
    $url = "{$config['baseUrl']}lists/{$listId}/cards?key={$config['key']}&token={$config['token']}&fields=id,due";
    $cards = json_decode(@file_get_contents($url), true) ?: [];
    if (empty($cards)) return 0;

    usort($cards, function($a, $b) {
        $da = !empty($a['due']) ? strtotime($a['due']) : PHP_INT_MAX;
        $db = !empty($b['due']) ? strtotime($b['due']) : PHP_INT_MAX;
        return $da <=> $db;
    });

    $ch = curl_init();
    foreach ($cards as $index => $card) {
        $pos = $index + 1;
        curl_setopt_array($ch, [
            CURLOPT_URL => "{$config['baseUrl']}cards/{$card['id']}?key={$config['key']}&token={$config['token']}&pos={$pos}",
            CURLOPT_CUSTOMREQUEST => "PUT", CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false
        ]);
        curl_exec($ch);
    }
    curl_close($ch);
    return count($cards);
}

function getPrintData($lists, $config) {
    $data = [];
    foreach ($lists as $name => $id) {
        if (!$id) continue;
        $url = "https://api.trello.com/1/lists/{$id}/cards?key={$config['key']}&token={$config['token']}&fields=name";
        $response = @file_get_contents($url);
        $data[$name] = $response ? json_decode($response, true) : [];
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
    exit;
}