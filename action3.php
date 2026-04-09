<?php
// action3.php
$config = require 'config3.php';

// Configuration de la session basée sur la config
ini_set('session.gc_maxlifetime', $config['SESSION_DURATION']);
ini_set('session.cookie_lifetime', $config['SESSION_DURATION']);
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: loginadm.php');
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Préparation du tableau de configuration Trello local
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
    'listlivre'      => $config['LIST_LIVRE'],
    // Labels
    'labelBateau'     => $config['LABEL_BATEAU'],
    'labelPonton'     => $config['LABEL_PONTON'],
    'labelMotomarine' => $config['LABEL_MOTOMARINE'],
    'labelChaloupe'   => $config['LABEL_CHALOUPE']
];

// Vérification de la méthode POST
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
                $total += sortListByDueDate($trelloConfig['listsemaine'], $trelloConfig);
                $total += sortListByDueDate($trelloConfig['listmecanique'], $trelloConfig);
                $total += sortListByDueDate($trelloConfig['listenattente'], $trelloConfig);
                $total += sortListByDueDate($trelloConfig['listlavage'], $trelloConfig);
                $total += sortListByDueDate($trelloConfig['listinspection'], $trelloConfig);
                returnResponse(200, 'success', "{$total} cartes ont été triées par date d'échéance.");
                break;
                
            case 'getPrintDataService':
				// IDs des listes pour l'atelier (Semaine, Mécanique, Lavage, etc.)
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
				$lists = [
					'PARKING LOT' => $trelloConfig['listparkinglot']
				];
				getPrintData($lists, $trelloConfig);
				break;

			case 'getPrintDataLivrer':
				$lists = [
					'LIVRÉ' => $trelloConfig['listlivre']
				];
				getPrintData($lists, $trelloConfig);
				break;

            default:
                returnResponse(400, 'error', "Action administrative inconnue.");
                break;
        }
        exit;
    }
    
    // --- Contrôleur OPÉRATION ---
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
                    returnResponse(400, 'error', "Action d'opération inconnue.");
                    break;
            }
        } else {
            returnResponse(400, 'error', 'Données manquantes (cardId ou action)');
        }
        exit;
    }
} else {
    returnResponse(405, 'error', 'Méthode non autorisée');
}

/**
 * Fonctions de traitement
 */

function applyAction($cardId, $cardAction, $config) {
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

function checkAndMoveCard($cardId, $cardAction, $listId, $config) {
    $checkitemId = getCheckitemIdByName($cardId, $cardAction, $config);
    if ($checkitemId) {
        $result = setCheckItem($cardId, $checkitemId, $config);
        if ($result['status'] === 'error') return $result;
        return moveCardToList($cardId, $listId, $config);
    } else {
        return ['status' => 'error', 'code' => 404, 'message' => "Élément de checklist '{$cardAction}' introuvable."];
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

function getCheckitemIdByName($cardId, $actionName, $config) {
    $url = "{$config['baseUrl']}cards/{$cardId}/checklists?key={$config['key']}&token={$config['token']}";

    $response = @file_get_contents($url);
    if ($response === false) return null;
    
    $checklists = json_decode($response, true);
    $mapping = ['deshrink' => 'De-Shrink', 'mecanique' => 'Mécanique', 'lavage' => 'Lavage'];
    $checkAction = $mapping[$actionName] ?? '';

    foreach ($checklists as $checklist) {
        foreach ($checklist['checkItems'] as $item) {
            if (trim($item['name']) === $checkAction) return $item['id'];
        }
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
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode === 200) ? ['status' => 'success', 'code' => 200] : ['status' => 'error', 'code' => $httpCode];
}

function moveCardToList($cardId, $idListDest, $config) {
    $url = "{$config['baseUrl']}cards/{$cardId}";
    $queryParams = http_build_query(['key' => $config['key'], 'token' => $config['token'], 'idList' => $idListDest, 'pos' => 'top']);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '?' . $queryParams,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode === 200) ? ['status' => 'success', 'code' => 200, 'message' => 'Succès'] : ['status' => 'error', 'code' => $httpCode];
}

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
    $url = "{$config['baseUrl']}lists/{$listId}/cards?key={$config['key']}&token={$config['token']}&fields=id,due";
    $cards = json_decode(@file_get_contents($url), true) ?: [];
    if (empty($cards)) return 0;

    usort($cards, function($a, $b) {
        $da = $a['due'] ? strtotime($a['due']) : PHP_INT_MAX;
        $db = $b['due'] ? strtotime($b['due']) : PHP_INT_MAX;
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

?>