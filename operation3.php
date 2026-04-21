<?php
$config = require 'config3.php';

ini_set('session.gc_maxlifetime', $config['SESSION_DURATION']);
ini_set('session.cookie_lifetime', $config['SESSION_DURATION']);
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: loginemp.php');
    exit;
}

$username = $_SESSION['username'] ?? '';
$userPermissions = $config['USERS'][$username]['permissions'] ?? [];

function can($permission, $userPermissions) {
    return in_array($permission, $userPermissions) || in_array('admin', $userPermissions);
}

// Liste des catégories
$categories = ['deshrink', 'mecanique', 'lavage'];
$userCats = [];
foreach ($categories as $cat) {
    if (can($cat, $userPermissions)) { $userCats[] = $cat; }
}

// On cache les radios si 1 seule catégorie ET que ce n'est pas mécanique (car mécanique a 2 choix)
$hideRadioGroup = (count($userCats) === 1 && !can('mecanique', $userPermissions));
$defaultAction = ($hideRadioGroup) ? $userCats[0] : '';

// Trello API
$TRELLO_BASE_URL  = $config['BASE_URL'];
$TRELLO_KEY      = $config['API_KEY'];
$TRELLO_TOKEN    = $config['API_TOKEN'];
$TRELLO_BOARD_ID = $config['BOARD_ID'];
$EXCLUDE_LIST_IDS = [$config['LIST_PARKINGLOT'], $config['LIST_LIVRE']];

$url   = "{$TRELLO_BASE_URL}boards/{$TRELLO_BOARD_ID}/cards?key={$TRELLO_KEY}&token={$TRELLO_TOKEN}&fields=name,idList";
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
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; padding: 20px; }
        .container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .logout-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;}
        .logout-btn { background-color: #dc3545; color: white; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-size: 0.85rem; }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; color: #555; }
        .dropdown-wrap { position: relative; }
        .dropdown-input-row { border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: white; }
        .dropdown-input-row input { border: none; outline: none; width: 100%; }
        .dropdown-list { display: none; position: absolute; width: 100%; background: white; border: 1px solid #ddd; z-index: 100; max-height: 200px; overflow-y: auto; padding:0; margin:0; list-style:none; box-shadow: 0 4px 8px rgba(0,0,0,0.1);}
        .dropdown-wrap.open .dropdown-list { display: block; }
        .dropdown-list li { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .dropdown-list li:hover { background: #f0f7ff; }
        .radio-group { background: #f9f9f9; padding: 10px; border-radius: 4px; border: 1px solid #eee; }
        .radio-item { display: flex; align-items: center; margin-bottom: 8px; cursor: pointer; }
        .radio-item input { margin-right: 10px; }
        button { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="logout-bar">
        <span><strong><?= htmlspecialchars($username) ?></strong></span>
        <a href="logout3.php" class="logout-btn">Déconnexion</a>
    </div>

    <h2 style="text-align:center">Intervention</h2>

    <form id="interventionForm">
        <div class="form-group">
            <label>Embarcation</label>
            <div class="dropdown-wrap" id="dd">
                <div class="dropdown-input-row"><input type="text" id="ddInput" placeholder="Chercher..." autocomplete="off"></div>
                <ul class="dropdown-list" id="ddList"></ul>
            </div>
            <input type="hidden" name="embarcationName" id="ddHiddenName" required>
            <input type="hidden" name="cardId" id="ddHiddenId" required>
            <input type="hidden" name="username" id="ddHiddenUsername" value="<?= $username ?>" required>
        </div>

        <?php if ($hideRadioGroup): ?>
            <input type="hidden" name="action" id="actionValue" value="<?= $defaultAction ?>">
        <?php else: ?>
            <div class="form-group">
                <label>Action effectuée</label>
                <div class="radio-group">
                    <?php if (can('deshrink', $userPermissions)): ?>
                        <label class="radio-item"><input type="radio" name="action" value="deshrink"> Deshrink terminé</label>
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
            <label>Information poteaux/frame</label>
            <div class="radio-group">
                <label class="radio-item"><input type="radio" name="deshrink" value="petitspoteaux"> Petits poteaux</label>
                <label class="radio-item"><input type="radio" name="deshrink" value="grandspoteaux"> Grands Poteaux</label>
                <label class="radio-item"><input type="radio" name="deshrink" value="frame"> Frame</label>
                <input type="text" name="frame_number" placeholder="Numéro de frame" style="width:100%; padding:8px; margin-top:5px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">
            </div>
        </div>
        <?php endif; ?>

        <button type="submit">Processer</button>
    </form>
</div>

<script>
    const OPTIONS = <?= json_encode($options) ?>;
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

    function updateUI() {
        const block = document.getElementById('deshrink-details');
        if (!block) return;

        // On vérifie soit le bouton radio coché, soit le champ caché
        const radio = document.querySelector('input[name="action"]:checked');
        const hidden = document.getElementById('actionValue');
        const current = radio ? radio.value : (hidden ? hidden.value : "");

        block.style.display = (current === 'deshrink') ? 'block' : 'none';
    }

    window.onload = () => {
        render('');
        wrap.classList.add('open');
        input.focus();
        updateUI();
        document.querySelectorAll('input[name="action"]').forEach(r => r.onchange = updateUI);
    };

    input.oninput = (e) => render(e.target.value);
    input.onfocus = () => wrap.classList.add('open');
    document.onclick = (e) => { if(!wrap.contains(e.target)) wrap.classList.remove('open'); };

    document.getElementById('interventionForm').onsubmit = function(e) {
        e.preventDefault();
        
        if(!document.getElementById('ddHiddenId').value) { 
            alert("Choisissez une embarcation"); 
            return; }
        
        const fd = new FormData(this);
        const mainAction = fd.get('action');    
        if (mainAction === 'deshrink') {
            const deshrinkOption = fd.get('deshrink');
            const frame_number = fd.get('frame_number') ? fd.get('frame_number').trim() : "";
        
            if (!deshrinkOption) {
                alert("Veuillez sélectionner le type de poteaux ou frame.");
                return;
            }
            
            if (deshrinkOption === 'frame' && frame_number === "") {
                alert("Veuillez saisir le numéro de frame.");
                document.querySelector('input[name="frame_number"]').focus();
                return;
            }
        }
        
        fd.append('operationAction', 'applyAction');
        
        fetch('action3.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') { alert('Succès !'); location.reload(); }
            else { alert(data.message); }
        });
    };
</script>
</body>
</html>