<?php
$config = require 'config.php';
$TRELLO_KEY      = $config['API_KEY'];
$TRELLO_TOKEN    = $config['API_TOKEN'];
$TRELLO_BOARD_ID = $config['BOARD_ID'];
$EXCLUDE_LIST_IDS = ['66343c1434f37e5225f86e88', '66342bb2823f26f403883242'];

$url   = "https://api.trello.com/1/boards/{$TRELLO_BOARD_ID}/cards?key={$TRELLO_KEY}&token={$TRELLO_TOKEN}&fields=name,idList";
$json  = file_get_contents($url);
 
$cards = $json ? json_decode($json, true) : [];
// On filtre les cartes selon les IDs de listes exclus
$filteredCards = array_filter($cards, fn($card) => !in_array($card['idList'], $EXCLUDE_LIST_IDS));

// On prépare un tableau d'objets avec id et name
$options = array_map(function($card) {
    return [
        'id'   => $card['id'],
        'name' => $card['name']
    ];
}, $filteredCards);

// Tri par nom
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

        /* ── Dropdown filtrable ── */
        .dropdown-wrap {
            position: relative;
            width: 100%;
        }

        .dropdown-input-row {
            display: flex;
            align-items: center;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 0 10px;
            transition: border-color 0.2s;
            cursor: text;
        }

        .dropdown-wrap.open .dropdown-input-row,
        .dropdown-input-row:focus-within {
            border-color: #007bff;
            outline: none;
        }

        .dropdown-input-row input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: #333;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 0.95rem;
            padding: 10px 0;
        }

        .dropdown-input-row input::placeholder { color: #aaa; }

        .arrow {
            width: 16px;
            height: 16px;
            color: #aaa;
            transition: transform 0.2s, color 0.2s;
            flex-shrink: 0;
            cursor: pointer;
        }

        .dropdown-wrap.open .arrow {
            transform: rotate(180deg);
            color: #007bff;
        }

        .count-badge {
            font-size: 0.72rem;
            color: #aaa;
            margin-right: 6px;
            flex-shrink: 0;
            pointer-events: none;
        }

        .clear-btn {
            display: none;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #ddd;
            color: #666;
            font-size: 0.75rem;
            line-height: 1;
            cursor: pointer;
            flex-shrink: 0;
            margin-right: 6px;
            transition: background 0.15s, color 0.15s;
        }
        .clear-btn:hover { background: #bbb; color: #333; }
        .dropdown-wrap.has-selection .clear-btn { display: flex; }

        .dropdown-list {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0; right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
            z-index: 100;
            max-height: 240px;
            overflow-y: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            animation: fadeIn 0.12s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .dropdown-wrap.open .dropdown-list { display: block; }

        .dropdown-list li {
            list-style: none;
            padding: 10px 12px;
            font-size: 0.88rem;
            color: #333;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.1s;
        }

        .dropdown-list li:last-child { border-bottom: none; }

        .dropdown-list li:hover,
        .dropdown-list li.highlighted {
            background: #f0f7ff;
        }

        .dropdown-list li.selected {
            color: #007bff;
            font-weight: 600;
        }

        .dropdown-list li .check {
            width: 13px;
            height: 13px;
            opacity: 0;
            color: #007bff;
            flex-shrink: 0;
        }

        .dropdown-list li.selected .check { opacity: 1; }

        .dropdown-list li mark {
            background: transparent;
            color: #007bff;
            font-weight: 600;
        }

        .no-results {
            padding: 12px;
            color: #aaa;
            font-size: 0.85rem;
            text-align: center;
        }

        /* ── Radio group ── */
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
            font-weight: normal;
            color: #333;
        }
        .radio-item:last-child { margin-bottom: 0; }
        .radio-item input { margin-right: 10px; }

        button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>

<div class="container">
    <h2>Formulaire de Suivi</h2>
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
            
            <input type="hidden" name="embarcation_name" id="ddHiddenName" required />
            <input type="hidden" name="embarcation_id" id="ddHiddenId" required />
        </div>

        <div class="form-group">
            <label>Action effectuée</label>
            <div class="radio-group">
                <label class="radio-item"><input type="radio" name="action" value="deshrink" required> Deshrink terminé</label>
                <label class="radio-item"><input type="radio" name="action" value="mecanique"> Mécanique terminé</label>
                <label class="radio-item"><input type="radio" name="action" value="mecanique_standby"> Mécanique en attente<br/>(pièces ou réparation extenre)</label>
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
    // OPTIONS contient maintenant des objets {id, name}
    const OPTIONS = <?= json_encode($options, JSON_UNESCAPED_UNICODE) ?>;

    const wrap        = document.getElementById('dd');
    const input       = document.getElementById('ddInput');
    const list        = document.getElementById('ddList');
    const count       = document.getElementById('ddCount');
    const hiddenName  = document.getElementById('ddHiddenName');
    const hiddenId    = document.getElementById('ddHiddenId');
    const arrow       = wrap.querySelector('.arrow');
    const clearBtn    = document.getElementById('ddClear');

    let selectedObj = null; // Stockera l'objet complet {id, name}
    let hiIndex     = -1;
    let filtered    = [...OPTIONS];

    const escRe = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    function render(query) {
        const q = query.trim().toLowerCase();
        filtered = q 
            ? OPTIONS.filter(o => o.name.toLowerCase().includes(q)) 
            : [...OPTIONS];

        hiIndex = -1;
        list.innerHTML = '';

        if (!filtered.length) {
            list.innerHTML = `<li class="no-results">Aucun résultat</li>`;
            count.textContent = '';
            return;
        }

        count.textContent = q ? `${filtered.length}/${OPTIONS.length}` : '';
        const re = q ? new RegExp(`(${escRe(q)})`, 'gi') : null;

        filtered.forEach(opt => {
            const li = document.createElement('li');
            li.dataset.id = opt.id;
            li.dataset.name = opt.name;

            const chk = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            chk.setAttribute('viewBox', '0 0 24 24'); chk.setAttribute('class', 'check');
            chk.setAttribute('fill', 'none'); chk.setAttribute('stroke', 'currentColor');
            chk.setAttribute('stroke-width', '2.5');
            chk.innerHTML = '<polyline points="20 6 9 17 4 12"/>';
            li.appendChild(chk);

            const span = document.createElement('span');
            span.innerHTML = re ? opt.name.replace(re, '<mark>$1</mark>') : opt.name;
            li.appendChild(span);

            if (selectedObj && opt.id === selectedObj.id) li.classList.add('selected');

            li.addEventListener('mousedown', e => { 
                e.preventDefault(); 
                choose(opt); 
            });
            list.appendChild(li);
        });
    }

    function choose(opt) {
        selectedObj = opt;
        input.value = opt.name;
        hiddenName.value = opt.name;
        hiddenId.value = opt.id;
        wrap.classList.add('has-selection');
        close();
        render('');
    }

    function clearSelection() {
        selectedObj = null;
        input.value = '';
        hiddenName.value = '';
        hiddenId.value = '';
        wrap.classList.remove('has-selection');
        render('');
        input.focus();
    }

    function open()  { wrap.classList.add('open'); render(input.value); }
    function close() { wrap.classList.remove('open'); hiIndex = -1; }
    function toggle(){ wrap.classList.contains('open') ? close() : open(); }

    function moveHi(dir) {
        const items = list.querySelectorAll('li:not(.no-results)');
        if (!items.length) return;
        hiIndex = (hiIndex + dir + items.length) % items.length;
        items.forEach((li, i) => li.classList.toggle('highlighted', i === hiIndex));
        items[hiIndex]?.scrollIntoView({ block: 'nearest' });
    }

    input.addEventListener('focus', open);
    input.addEventListener('input', () => {
        if (input.value === '') { selectedObj = null; hiddenId.value = ''; hiddenName.value = ''; }
        if (!wrap.classList.contains('open')) open();
        render(input.value);
    });

    input.addEventListener('keydown', e => {
        if (!wrap.classList.contains('open') && (e.key === 'ArrowDown' || e.key === 'Enter')) { open(); return; }
        if (e.key === 'ArrowDown')    { e.preventDefault(); moveHi(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); moveHi(-1); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            const hi = list.querySelector('.highlighted');
            if (hi) {
                choose({ id: hi.dataset.id, name: hi.dataset.name });
            } else if (filtered.length === 1) {
                choose(filtered[0]);
            }
        }
        else if (e.key === 'Escape') { close(); input.blur(); }
    });

    arrow.addEventListener('mousedown', e => { e.preventDefault(); toggle(); });
    clearBtn.addEventListener('mousedown', e => { e.preventDefault(); clearSelection(); });

    document.addEventListener('mousedown', e => {
        if (!wrap.contains(e.target)) {
            input.value = selectedObj ? selectedObj.name : '';
            close();
        }
    });

    document.getElementById('interventionForm').addEventListener('submit', function(e) {
        e.preventDefault();

        if (!hiddenId.value) {
            alert('Veuillez sélectionner une embarcation.');
            input.focus();
            return;
        }

        const formData = new FormData(this);
        const data = {
            embarcation_name: formData.get('embarcation_name'),
            embarcation_id:   formData.get('embarcation_id'),
            action:           formData.get('action'),
            numframe:         formData.get('numframe')
        };

        console.log("Données récoltées pour envoi :", data);
        alert(`ID Trello : ${data.embarcation_id}\nNom : ${data.embarcation_name}\nAction : ${data.action}`);

        this.reset();
        clearSelection();
    });

    render('');
</script>
</body>
</html>