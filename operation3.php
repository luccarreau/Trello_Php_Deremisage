<?php
$config = require 'config3.php';

// Configuration de la durée de session
ini_set('session.gc_maxlifetime', $config['SESSION_DURATION']);
ini_set('session.cookie_lifetime', $config['SESSION_DURATION']);
session_start();

// Si l'utilisateur n'est pas authentifié, on le redirige vers loginemp.php
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: loginemp.php');
    exit;
}

// Récupération des informations de l'utilisateur
$username = $_SESSION['username'] ?? '';
$userPermissions = $config['USERS'][$username]['permissions'] ?? [];

/**
 * Fonction utilitaire pour vérifier une permission
 */
function can($permission, $userPermissions) {
    return in_array($permission, $userPermissions) || in_array('admin', $userPermissions);
}

// --- LOGIQUE DE SELECTION AUTOMATIQUE ---
// On définit la liste des actions possibles
$allActions = ['deshrink', 'mecanique', 'lavage'];
$availableActions = [];

// On filtre les actions selon les permissions de l'utilisateur
foreach ($allActions as $act) {
    if (can($act, $userPermissions)) {
        $availableActions[] = $act;
    }
}

// Déterminer si on doit cacher le groupe radio (si 1 seule option disponible)
$hideRadioGroup = (count($availableActions) === 1);
$defaultAction = ($hideRadioGroup) ? $availableActions[0] : '';

// Configuration API Trello
$TRELLO_KEY      = $config['API_KEY'];
$TRELLO_TOKEN    = $config['API_TOKEN'];
$TRELLO_BOARD_ID = $config['BOARD_ID'];
$EXCLUDE_LIST_IDS = [$config['LIST_PARKINGLOT'], $config['LIST_LIVRE']];

$url   = "https://api.trello.com/1/boards/{$TRELLO_BOARD_ID}/cards?key={$TRELLO_KEY}&token={$TRELLO_TOKEN}&fields=name,idList";
$json  = @file_get_contents($url);
$cards = $json ? json_decode($json, true) : [];
$filteredCards = array_filter($cards, fn($card) => !in_array($card['idList'], $EXCLUDE_LIST_IDS));

$options = array_map(function($card) {
    return ['id' => $card['id'], 'name' => $card['name']];
}, $filteredCards);

usort($options, fn($a, $b) => strcmp($a['name'], $b['name']));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisie d'Intervention</title>
    <style>
    /* --- Base et Layout --- */
    body { 
        font-family: 'Segoe UI', sans-serif; 
        background-color: #f4f7f6; 
        display: flex; 
        justify-content: center; 
        padding: 20px; 
    }

    .container { 
        background: white; 
        padding: 2rem; 
        border-radius: 8px; 
        box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        width: 100%; 
        max-width: 400px; 
    }

    /* --- Barre de déconnexion --- */
    .logout-bar { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 20px; 
        padding-bottom: 10px; 
        border-bottom: 1px solid #eee; 
    }

    .logout-btn { 
        background-color: #dc3545; 
        color: white; 
        text-decoration: none; 
        padding: 6px 12px; 
        border-radius: 4px; 
        font-size: 0.85rem; 
        transition: background 0.2s;
    }

    .logout-btn:hover {
        background-color: #c82333;
    }

    /* --- Éléments de formulaire --- */
    h2 { 
        color: #333; 
        text-align: center; 
    }

    .form-group { 
        margin-bottom: 1.2rem; 
    }

    label { 
        display: block; 
        margin-bottom: 0.5rem; 
        font-weight: bold; 
        color: #555; 
    }

    input[type="text"] { 
        width: 100%; 
        padding: 10px; 
        border: 1px solid #ddd; 
        border-radius: 4px; 
        box-sizing: border-box; 
    }

    .radio-group { 
        background: #f9f9f9; 
        padding: 10px; 
        border-radius: 4px; 
        border: 1px solid #eee; 
    }

    .radio-item { 
        display: flex; 
        align-items: center; 
        margin-bottom: 8px; 
        cursor: pointer; 
    }

    button { 
        width: 100%; 
        padding: 12px; 
        background-color: #007bff; 
        color: white; 
        border: none; 
        border-radius: 4px; 
        cursor: pointer; 
        font-size: 1rem;
        transition: background 0.3s;
    }

    button:hover {
        background-color: #0056b3;
    }

    /* --- Messages d'état --- */
    #statusMessage { 
        display: none; 
        padding: 12px; 
        margin-bottom: 20px; 
        border-radius: 4px; 
        text-align: center; 
        font-weight: 500; 
    }

    /* --- Dropdown Dynamique (Même Largeur) --- */
    .dropdown-wrap { 
        position: relative; 
        width: 100%;
        box-sizing: border-box;
    }

    .dropdown-input-row { 
        display: flex; 
        align-items: center; 
        border: 1px solid #ddd; 
        border-radius: 4px; 
        padding: 0 10px; 
        background: white;
        width: 100%;
        box-sizing: border-box; /* Inclus padding et border dans la largeur */
    }

    .dropdown-input-row input {
        border: none;
        outline: none;
        padding: 10px 0;
        width: 100%;
        font-family: inherit;
    }

    .dropdown-list { 
        display: none; 
        position: absolute; 
        top: calc(100% + 2px);
        left: 0;
        width: 100%; /* S'aligne sur la largeur du parent dropdown-wrap */
        background: white; 
        border: 1px solid #ddd; 
        border-radius: 4px; 
        z-index: 100; 
        max-height: 200px; 
        overflow-y: auto; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        box-sizing: border-box;
    }

    .dropdown-wrap.open .dropdown-list { 
        display: block; 
    }

    .dropdown-list li { 
        padding: 10px; 
        cursor: pointer; 
        list-style: none;
        border-bottom: 1px solid #f0f0f0;
    }

    .dropdown-list li:last-child {
        border-bottom: none;
    }

    .dropdown-list li:hover { 
        background: #f0f7ff; 
        color: #007bff;
    }
</style>
</head>
<body>

<div class="container">
    <div class="logout-bar">
        <span><strong><?= htmlspecialchars($username) ?></strong></span>
        <a href="logout3.php" class="logout-btn">Déconnexion</a>
    </div>

    <h2>Suivi d'Intervention</h2>
    <div id="statusMessage"></div>

    <form id="interventionForm">
        <div class="form-group">
            <label>Embarcation</label>
            <div class="dropdown-wrap" id="dd">
                <div class="dropdown-input-row" id="ddTrigger">
                    <input type="text" id="ddInput" placeholder="Chercher..." autocomplete="off" />
                </div>
                <ul class="dropdown-list" id="ddList"></ul>
            </div>
            <input type="hidden" name="embarcationName" id="ddHiddenName" required />
            <input type="hidden" name="cardId" id="ddHiddenId" required />
        </div>

        <?php if ($hideRadioGroup): ?>
            <input type="hidden" name="action" value="<?= $defaultAction ?>">
        <?php else: ?>
            <div class="form-group">
                <label>Action effectuée</label>
                <div class="radio-group">
                    <?php if (can('deshrink', $userPermissions)): ?>
                        <label class="radio-item"><input type="radio" name="action" value="deshrink" required> Deshrink terminé</label>
                    <?php endif; ?>
                    <?php if (can('mecanique', $userPermissions)): ?>
                        <label class="radio-item"><input type="radio" name="action" value="mecanique"> Mécanique terminé</label>
                        <label class="radio-item"><input type="radio" name="action" value="mecanique_standby"> Mécanique en attente</label>
                    <?php endif; ?>
                    <?php if (can('lavage', $userPermissions)): ?>
                        <label class="radio-item"><input type="radio" name="action" value="lavage"> Lavage terminé</label>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (can('deshrink', $userPermissions)): ?>
        <div class="form-group" id="deshrink-details">
            <label>Détails De-Shrink</label>
            <div class="radio-group">
                <label class="radio-item"><input type="radio" name="deshrink" value="petitpoteaux"> Petit poteaux</label>
                <label class="radio-item"><input type="radio" name="deshrink" value="grandpoteaux"> Grand Poteaux</label>
                <label class="radio-item"><input type="radio" name="deshrink" value="frame"> Frame</label>
                <input type="text" name="numframe" placeholder="Numéro de frame">
            </div>
        </div>
        <?php endif; ?>

        <button type="submit">Procéder</button>
    </form>
</div>

<script>
    const OPTIONS = <?= json_encode($options) ?>;
    // ... (Le reste du code JavaScript reste identique à votre version précédente pour le dropdown et le fetch) ...
    // Note : Si l'utilisateur a plusieurs permissions mais qu'il sélectionne 'mecanique', 
    // vous pourriez vouloir cacher/afficher le bloc #deshrink-details via JS.
    
    // Simplification pour le dropdown :
    const input = document.getElementById('ddInput'), list = document.getElementById('ddList'), wrap = document.getElementById('dd');
    function render(q) {
        list.innerHTML = '';
        OPTIONS.filter(o => o.name.toLowerCase().includes(q.toLowerCase())).forEach(o => {
            const li = document.createElement('li');
            li.textContent = o.name;
            li.onclick = () => { 
                input.value = o.name; 
                document.getElementById('ddHiddenName').value = o.name;
                document.getElementById('ddHiddenId').value = o.id;
                wrap.classList.remove('open');
            };
            list.appendChild(li);
        });
    }
    input.onfocus = () => wrap.classList.add('open');
    input.oninput = (e) => render(e.target.value);
    render('');

    document.getElementById('interventionForm').onsubmit = function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('operationAction', 'applyAction');
        fetch('action3.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(data => {
            if(data.status === 'success') { alert('Envoyé'); location.reload(); }
        });
    };
</script>
</body>
</html>