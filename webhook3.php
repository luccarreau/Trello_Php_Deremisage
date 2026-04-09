<?php
/**
 * webhook3.php - Récepteur de Webhook
 */

// 1. Récupérer le contenu brut envoyé par Trello
$rawBody = file_get_contents('php://input');

// 2. Décoder le JSON
$data = json_decode($rawBody, true);

// 3. Journaliser (Log) les informations dans un fichier texte
// On ajoute la date et le contenu pour analyse
$logEntry = "[" . date('Y-m-d H:i:s') . "] WEBHOOK REÇU :\n";
$logEntry .= $rawBody . "\n";
$logEntry .= "------------------------------------------\n";

file_put_contents('webhook_log.txt', $logEntry, FILE_APPEND);

// 4. Répondre à Trello (Important : Trello exige un code 200)
// Si vous ne répondez pas 200, Trello réessaiera ou désactivera le webhook.
http_response_code(200);

// Optionnel : Affichage pour test manuel (si vous ouvrez la page dans un navigateur)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo "<h1>Récepteur de Webhook Actif</h1>";
    echo "Dernières données reçues : <pre>" . htmlspecialchars(file_get_contents('webhook_log.txt')) . "</pre>";
}
?>