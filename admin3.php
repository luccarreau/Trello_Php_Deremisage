<?php
$config = require 'config3.php';

ini_set('session.gc_maxlifetime', $config['SESSION_DURATION']);
ini_set('session.cookie_lifetime', $config['SESSION_DURATION']);
session_start();

// Si l'utilisateur n'est pas authentifié, on le redirige vers loginadm.php
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: loginadm.php');
    exit;
}

// On garde le chargement de la config pour les appels API, 
// mais on n'a plus besoin de récupérer la liste des cartes Trello ici.
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gestion Trello</title>
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
        h2 { color: #333; text-align: center; margin-bottom: 2rem; }
        
        #statusMessage { 
            display: none; 
            padding: 12px; 
            margin-bottom: 20px; 
            border-radius: 4px; 
            text-align: center; 
            font-weight: 500; 
            white-space: pre-line; /* Permet d'afficher les retours à la ligne du PHP */
        }

        .btn-admin { 
            width: 100%; 
            padding: 15px; 
            border: none; 
            border-radius: 4px; 
            font-size: 1rem; 
            cursor: pointer; 
            transition: background 0.3s, transform 0.1s; 
            margin-bottom: 15px; 
            color: white; 
            font-weight: bold;
        }
        .btn-admin:active { transform: scale(0.98); }
        .btn-admin:disabled { background-color: #ccc; cursor: not-allowed; }
        
        .btn-blue { background-color: #007bff; }
        .btn-blue:hover { background-color: #0056b3; }
        
        .btn-secondary { background-color: #6c757d; }
        .btn-secondary:hover { background-color: #5a6268; }
    </style>
</head>
<body>

<div class="container">
    <h2>Administration</h2>

    <div id="statusMessage"></div>

    <button type="button" class="btn-admin btn-blue" id="btnPrintService">
        Imprimer les listes de service
    </button>
	
	<button type="button" class="btn-admin btn-blue" id="btnPrintParkingLot">
        Imprimer la liste du Parking Lot
    </button>
	
	<button type="button" class="btn-admin btn-blue" id="btnPrintLivrer">
        Imprimer la liste Livré
    </button>
    
    <button type="button" class="btn-admin btn-blue" id="btnSort">
        Trier les listes (semaine, mécanique, en attente, lavage, inspection)
    </button>
    
    <button type="button" class="btn-admin btn-blue" id="btnInit">
        Créer checklist "À faire" (s'il n'existe pas)
    </button>    
</div>

<script>
    const statusMsg = document.getElementById('statusMessage');
    const btnInit = document.getElementById('btnInit');
    const btnSort = document.getElementById('btnSort');
    const btnPrintService = document.getElementById('btnPrintService');
	const btnPrintParkingLot = document.getElementById('btnPrintParkingLot');
	const btnPrintLivrer = document.getElementById('btnPrintLivrer');

    function showFlash(text, type) {
        statusMsg.textContent = text;
        statusMsg.style.display = 'block';
        statusMsg.style.backgroundColor = type === 'success' ? '#d4edda' : '#f8d7da';
        statusMsg.style.color = type === 'success' ? '#155724' : '#721c24';
        statusMsg.style.border = `1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'}`;
        
        // On laisse le message visible plus longtemps si c'est un gros traitement
        setTimeout(() => {
            statusMsg.style.display = 'none';
        }, 8000);
    }

    // Action : Initialiser Checklist
    btnInit.addEventListener('click', function() {
        // Désactiver le bouton pendant le traitement des 400 cartes
        btnInit.disabled = true;
        btnInit.textContent = "Traitement en cours...";
        
        const formData = new FormData();
        formData.append('adminAction', 'initChecklist');

        fetch('action3.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            showFlash(data.message, data.status);
        })
        .catch(error => {
            console.error(error);
            showFlash("Erreur de communication avec le serveur", "error");
        })
        .finally(() => {
            btnInit.disabled = false;
            btnInit.textContent = "Initialiser Checklist \"À faire\"";
        });
    });
    
    // Action : Trier les listes par Due Date
    btnSort.addEventListener('click', function() {
        // 1. État visuel pendant le chargement
        btnSort.disabled = true;
        btnSort.textContent = "Tri en cours...";
        
        const formData = new FormData();
        formData.append('adminAction', 'sortCards'); // Envoie l'action pour le switch PHP

        // 2. Appel au serveur
        fetch('action3.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            // Affiche le message de confirmation (ex: "400 cartes triées")
            showFlash(data.message, data.status);
        })
        .catch(error => {
            console.error(error);
            showFlash("Erreur lors du tri des listes", "error");
        })
        .finally(() => {
            // 3. Rétablir le bouton
            btnSort.disabled = false;
            btnSort.textContent = "Trier les listes (semaine, mécanique, en attente, lavage, inspection)";
        });
    });
    
    
	// Fonction générique pour l'impression
    function handlePrint(actionType) {
        const formData = new FormData();
        formData.append('adminAction', actionType);

        fetch('action3.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                const printWindow = window.open('', '_blank');
                let content = `<html><head><title>Impression Trello</title>
                    <style>
                        body { font-family: sans-serif; padding: 20px; }
                        .list-section { page-break-before: always; padding-top: 0px; }
                        .list-section:first-of-type { page-break-before: auto; }
                        h1 { text-transform: uppercase; font-size: 1.1rem; text-align:center; }
                        h2 { color: #2c3e50; border-left: 5px solid #007bff; padding-left: 10px; text-transform: uppercase; border-bottom: 2px solid #eee; padding-bottom: 10px; font-size: 1.0rem; }
                        ul { list-style: none; padding: 0; }
                        li { padding: 8px 0; border-bottom: 1px dotted #ccc; font-size: 0.8rem; }
                        .date-header { text-align: right; font-size: 0.8rem; color: #666; }
                    </style></head><body>`;
                
                for (const [listName, cards] of Object.entries(res.data)) {
                    content += `<div class="list-section">
                                    <div class="date-header">Imprimé le : ${new Date().toLocaleString('fr-FR')}</div>
                                    <h1>Rapport d'Atelier</h1>
                                    <h2>${listName} (${cards.length} unités)</h2>
                                    <ul>`;
                    
                    if (cards.length === 0) {
                        content += "<li>Aucune unité dans cette liste</li>";
                    } else {
                        cards.forEach(card => {
                            content += `<li>[ &nbsp;&nbsp; ] &nbsp;&nbsp; ${card.name}</li>`;
                        });
                    }
                    content += `</ul></div>`;
                }

                content += `</body></html>`;
                printWindow.document.write(content);
                printWindow.document.close();
                setTimeout(() => { printWindow.print(); }, 500);
            } else {
                showFlash("Erreur: " + res.message, "error");
            }
        });
    }

    // Liaison des boutons aux actions
    btnPrintService.addEventListener('click', () => handlePrint('getPrintDataService'));
    btnPrintParkingLot.addEventListener('click', () => handlePrint('getPrintDataParking'));
    btnPrintLivrer.addEventListener('click', () => handlePrint('getPrintDataLivrer'));
</script>

</body>
</html>