<?php

// ----------------------------------------------------
// Konfiguration
// ----------------------------------------------------

// Dein Mastodon-Access-Token (von den Einstellungen > Entwicklung)
$accessToken = 'DEIN_ACCESS_TOKEN_HIER_EINFUEGEN'; 

// Deine Mastodon-Instanz (z.B. mastodon.social)
$instance = 'DEINE_INSTANZ_HIER.de';

// Der Hashtag, nach dem gesucht werden soll
$hashtag = 'hashtag_suche';

// Nach wie vielen Tagen soll eine ID aus der JSON-Datei gelöscht werden?
// (Damit die Datei nicht zu groß wird)
$cleanupDays = 30; 

// Pfad zur JSON-Datei, in der geboostete IDs gespeichert werden
$jsonFile = 'posted_ids.json';

// ----------------------------------------------------
// Funktionen
// ----------------------------------------------------

/**
 * Holt die IDs der bereits geboosteten Beiträge aus der JSON-Datei.
 * @return array
 */
function getPostedIds($jsonFile) {
    if (!file_exists($jsonFile)) {
        return [];
    }
    $json = file_get_contents($jsonFile);
    return json_decode($json, true) ?: [];
}

/**
 * Speichert die ID eines neuen geboosteten Beitrags in der JSON-Datei.
 * @param string $jsonFile
 * @param int $statusId
 */
function addPostedId($jsonFile, $statusId) {
    $ids = getPostedIds($jsonFile);
    $ids[$statusId] = time(); // Speichert die ID mit dem aktuellen Timestamp
    file_put_contents($jsonFile, json_encode($ids, JSON_PRETTY_PRINT));
}

/**
 * Entfernt alte Einträge aus der JSON-Datei.
 * @param string $jsonFile
 * @param int $cleanupDays
 */
function cleanupPostedIds($jsonFile, $cleanupDays) {
    $ids = getPostedIds($jsonFile);
    $newIds = [];
    $cutoffTime = time() - ($cleanupDays * 24 * 60 * 60);

    foreach ($ids as $statusId => $timestamp) {
        if ($timestamp > $cutoffTime) {
            $newIds[$statusId] = $timestamp;
        }
    }

    if (count($ids) !== count($newIds)) {
        file_put_contents($jsonFile, json_encode($newIds, JSON_PRETTY_PRINT));
        echo "Alte IDs aus $jsonFile gelöscht.\n";
    }
}

/**
 * Führt eine API-Anfrage an Mastodon durch.
 * @param string $url
 * @param string $accessToken
 * @param string $method
 * @return mixed
 */
function callApi($url, $accessToken, $method = 'GET') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Fehler bei API-Anfrage ($httpCode): " . $response);
        return false;
    }
    
    return json_decode($response, true);
}

// ----------------------------------------------------
// Hauptlogik des Bots
// ----------------------------------------------------

echo "Starte Mastodon-Bot-Lauf...\n";

// 1. Hole alle geboosteten IDs aus der JSON-Datei
$postedIds = getPostedIds($jsonFile);
echo "Es gibt " . count($postedIds) . " IDs, die bereits geboostet wurden.\n";

// 2. Suche nach Beiträgen mit dem Hashtag
$searchUrl = "https://$instance/api/v2/search?q=%23$hashtag&type=hashtags&resolve=true";
$searchResults = callApi($searchUrl, $accessToken);

if (!$searchResults || !isset($searchResults['statuses'])) {
    echo "Keine Ergebnisse oder Fehler bei der Suche.\n";
    cleanupPostedIds($jsonFile, $cleanupDays);
    die();
}

$statuses = $searchResults['statuses'];

// 3. Verarbeite die gefundenen Beiträge
foreach ($statuses as $status) {
    $statusId = $status['id'];

    // Prüfe, ob es ein originaler Beitrag ist und keine Reblog (Boost)
    // Ein Reblog hat einen 'reblog'-Schlüssel. Wir wollen nur Originale.
    if ($status['reblog'] !== null) {
        echo "Beitrag ID $statusId ist ein Reblog und wird ignoriert.\n";
        continue;
    }
    
    // Prüfe, ob die ID bereits in unserer Liste ist
    if (isset($postedIds[$statusId])) {
        echo "Beitrag ID $statusId wurde bereits geboostet und wird ignoriert.\n";
        continue;
    }

    // Wenn der Beitrag neu und ein Original ist, booste ihn
    $boostUrl = "https://$instance/api/v1/statuses/$statusId/reblog";
    $boostResult = callApi($boostUrl, $accessToken, 'POST');

    if ($boostResult) {
        echo "Beitrag ID $statusId erfolgreich geboostet!\n";
        addPostedId($jsonFile, $statusId);
    } else {
        echo "Fehler beim Boosten von Beitrag ID $statusId.\n";
    }
}

// 4. Bereinige die JSON-Datei von alten Einträgen
cleanupPostedIds($jsonFile, $cleanupDays);

echo "Bot-Lauf abgeschlossen.\n";

?>