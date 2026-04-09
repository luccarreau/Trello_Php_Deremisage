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

$config = require 'config3.php';
$TRELLO_KEY      = $config['API_KEY'];
$TRELLO_TOKEN    = $config['API_TOKEN'];
$TRELLO_BOARD_ID = $config['BOARD_ID'];
$EXCLUDE_LIST_IDS = [$config['LIST_PARKINGLOT'], $config['LIST_LIVRE']];

$url   = "https://api.trello.com/1/boards/{$TRELLO_BOARD_ID}/cards?key={$TRELLO_KEY}&token={$TRELLO_TOKEN}&fields=name,idList";
$json  = @file_get_contents($url);

$cards = $json ? json_decode($json, true) : [];
$filteredCards = array_filter($cards, fn($card) => !in_array($card['idList'], $EXCLUDE_LIST_IDS));

$options = array_map(function($card) {
    return [
        'id'   => $card['id'],
        'name' => $card['name']
    ];
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
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        h2 { color: #333; margin-bottom: 1.5rem; text-align: center; }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; color: #555; }

        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        /* --- Nouveau : Style du message flash --- */
        #statusMessage {
            display: none;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
            font-weight: 500;
            animation: fadeIn 0.3s;
        }

        /* ── Dropdown filtrable ── */
        .dropdown-wrap { position: relative; width: 100%; }
        .dropdown-input-row {
            display: flex; align-items: center; background: white; border: 1px solid #ddd;
            border-radius: 4px; padding: 0 10px; transition: border-color 0.2s; cursor: text;
        }
        .dropdown-wrap.open .dropdown-input-row,
        .dropdown-input-row:focus-within { border-color: #007bff; outline: none; }
        .dropdown-input-row input {
            flex: 1; background: transparent; border: none; outline: none;
            color: #333; font-family: inherit; font-size: 0.95rem; padding: 10px 0;
        }
        .arrow { width: 16px; height: 16px; color: #aaa; transition: transform 0.2s; flex-shrink: 0; cursor: pointer; }
        .dropdown-wrap.open .arrow { transform: rotate(180deg); color: #007bff; }
        .count-badge { font-size: 0.72rem; color: #aaa; margin-right: 6px; flex-shrink: 0; }
        .clear-btn {
            display: none; align-items: center; justify-content: center;
            width: 18px; height: 18px; border-radius: 50%; background: #ddd;
            color: #666; font-size: 0.75rem; cursor: pointer; margin-right: 6px;
        }
        .dropdown-wrap.has-selection .clear-btn { display: flex; }
        .dropdown-list {
            display: none; position: absolute; top: calc(100% + 4px);
            left: 0; right: 0; background: white; border: 1px solid #ddd;
            border-radius: 4px; z-index: 100; max-height: 240px; overflow-y: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .dropdown-wrap.open .dropdown-list { display: block; }
        .dropdown-list li {
            padding: 10px 12px; font-size: 0.88rem; cursor: pointer;
            display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f0f0f0;
        }
        .dropdown-list li:hover { background: #f0f7ff; }
        .dropdown-list li.selected { color: #007bff; font-weight: 600; }
        .dropdown-list li .check { width: 13px; height: 13px; opacity: 0; color: #007bff; }
        .dropdown-list li.selected .check { opacity: 1; }
        .dropdown-list li mark { background: transparent; color: #007bff; font-weight: 600; }

        /* ── Radio group ── */
        .radio-group { background: #f9f9f9; padding: 10px; border-radius: 4px; border: 1px solid #eee; }
        .radio-item { display: flex; align-items: center; margin-bottom: 8px; cursor: pointer; color: #333; }
        .radio-item input { margin-right: 10px; }

        button {
            width: 100%; padding: 12px; background-color: #007bff; color: white;
            border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; transition: background 0.3s;
        }
        button:hover { background-color: #0056b3; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>

<div class="container">
    <h2>Formulaire de suivi</h2>

    <div id="statusMessage"></div>

    <form id="interventionForm">
        <div class="form-group">
            <label>Embarcation</label>
            <div class="dropdown-wrap" id="dd">
                <div class="dropdown-input-row" id="ddTrigger">
                    <input type="text" id="ddInput" placeholder="Tapez pour filtrer…" autocomplete="off" autofocus />
                    <span class="count-badge" id="ddCount"></span>
                    <span class="clear-btn" id="ddClear" title="Réinitialiser">✕</span>
                    <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </div>
                <ul class="dropdown-list" id="ddList" role="listbox"></ul>
            </div>
            <input type="hidden" name="embarcationName" id="ddHiddenName" required />
            <input type="hidden" name="cardId" id="ddHiddenId" required />
        </div>

        <div class="form-group">
            <label>Action effectuée</label>
            <div class="radio-group">
                <label class="radio-item"><input type="radio" name="action" value="deshrink" required> Deshrink terminé</label>
                <label class="radio-item"><input type="radio" name="action" value="mecanique"> Mécanique terminé</label>
                <label class="radio-item"><input type="radio" name="action" value="mecanique_standby"> Mécanique en attente</label>
                <label class="radio-item"><input type="radio" name="action" value="lavage"> Lavage terminé</label>
            </div>
        </div>

        <div class="form-group">
            <label>De-Shrink</label>
            <div class="radio-group">
                <label class="radio-item"><input type="radio" name="deshrink" value="petitpoteaux"> Petit poteaux</label>
                <label class="radio-item"><input type="radio" name="deshrink" value="grandpoteaux"> Grand Poteaux</label>
                <label class="radio-item"><input type="radio" name="deshrink" value="frame"> Frame</label>
                Numéro de frame : <input type="text" name="numframe">
            </div>
        </div>

        <button type="submit">Procéder</button>
    </form>
</div>

<script>
    const OPTIONS = <?= json_encode($options, JSON_UNESCAPED_UNICODE) ?>;
    const wrap = document.getElementById('dd'), input = document.getElementById('ddInput'), 
          list = document.getElementById('ddList'), count = document.getElementById('ddCount'),
          hiddenName = document.getElementById('ddHiddenName'), hiddenId = document.getElementById('ddHiddenId'),
          clearBtn = document.getElementById('ddClear'), arrow = wrap.querySelector('.arrow'),
          statusMsg = document.getElementById('statusMessage');

    let selectedObj = null;
    let filtered = [...OPTIONS];

    // --- Fonctions utilitaires Dropdown ---
    function render(query) {
        const q = query.trim().toLowerCase();
        filtered = q ? OPTIONS.filter(o => o.name.toLowerCase().includes(q)) : [...OPTIONS];
        list.innerHTML = '';
        count.textContent = q ? `${filtered.length}/${OPTIONS.length}` : '';
        filtered.forEach(opt => {
            const li = document.createElement('li');
            li.innerHTML = `<span>${opt.name}</span>`;
            if (selectedObj && opt.id === selectedObj.id) li.classList.add('selected');
            li.addEventListener('mousedown', e => { e.preventDefault(); choose(opt); });
            list.appendChild(li);
        });
    }

    function choose(opt) {
        selectedObj = opt; input.value = opt.name; hiddenName.value = opt.name;
        hiddenId.value = opt.id; wrap.classList.add('has-selection');
        wrap.classList.remove('open');
    }

    function clearSelection() {
        selectedObj = null; input.value = ''; hiddenName.value = ''; hiddenId.value = '';
        wrap.classList.remove('has-selection'); render('');
    }

    input.addEventListener('focus', () => { wrap.classList.add('open'); render(input.value); });
    input.addEventListener('input', () => { if(!input.value) clearSelection(); render(input.value); });
    arrow.addEventListener('mousedown', e => { e.preventDefault(); wrap.classList.toggle('open'); });
    clearBtn.addEventListener('mousedown', e => { e.preventDefault(); clearSelection(); });
    document.addEventListener('mousedown', e => { if (!wrap.contains(e.target)) wrap.classList.remove('open'); });

    // --- NOUVEAU : Fonction pour afficher le message flash ---
    function showFlash(text, type) {
        statusMsg.textContent = text;
        statusMsg.style.display = 'block';
        
        if (type === 'success') {
            statusMsg.style.backgroundColor = '#d4edda';
            statusMsg.style.color = '#155724';
            statusMsg.style.border = '1px solid #c3e6cb';
        } else {
            statusMsg.style.backgroundColor = '#f8d7da';
            statusMsg.style.color = '#721c24';
            statusMsg.style.border = '1px solid #f5c6cb';
        }

        // Cache le message après 5 secondes
        setTimeout(() => {
            statusMsg.style.display = 'none';
        }, 5000);
    }

    // --- Envoi du formulaire ---
    document.getElementById('interventionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!hiddenId.value) {
            showFlash("Veuillez sélectionner une embarcation.", "error");
            input.focus();
            return;
        }
        
        const formData = new FormData(this);
        formData.append('operationAction', 'applyAction');

        fetch('action3.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                //showFlash("Envoyé " + data.message, "success");
                showFlash("Envoyé", "success");
                this.reset();
                clearSelection();
            } else {
                showFlash("Erreur : " + data.message, "error");
            }
        })
        .catch(error => {
            console.error('Erreur :', error);
            showFlash("Erreur de connexion au serveur: " + error, "error");
        });
    });

    render('');
</script>
</body>
</html>