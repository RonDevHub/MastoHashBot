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
$jsonFile = __DIR__ . '/posted_ids.json';

// ----------------------------------------------------
// Funktionen
// ----------------------------------------------------

/**
 * Holt die IDs der bereits geboosteten Beiträge aus der JSON-Datei.
 * @param string $jsonFile
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
    $ids[$statusId] = time();
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

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data === null && !empty($response)) {
            error_log("JSON-Decodierungsfehler: " . json_last_error_msg());
            return false;
        }
        return $data;
    } else {
        error_log("Fehler bei API-Anfrage ($httpCode): " . $response);
        return false;
    }
}

// ----------------------------------------------------
// Hauptlogik des Bots
// ----------------------------------------------------

echo "Starte Mastodon-Bot-Lauf...\n";

// 1. Hole die Hashtag-Timeline
$timelineUrl = "https://$instance/api/v1/timelines/tag/$hashtag?limit=40";
$timelineResult = callApi($timelineUrl, $accessToken);

if ($timelineResult === false || count($timelineResult) === 0) {
    echo "Keine Ergebnisse gefunden oder API-Fehler.\n";
    if (!file_exists($jsonFile)) {
        file_put_contents($jsonFile, json_encode([]));
    }
    die();
}

// 2. Kehre die Reihenfolge der Beiträge um, um die neuesten zuerst zu boosten
$statuses = array_reverse($timelineResult);

// 3. Hole alle geboosteten IDs aus der JSON-Datei
$postedIds = getPostedIds($jsonFile);
echo "Es gibt " . count($postedIds) . " IDs, die bereits geboostet wurden.\n";

// 4. Verarbeite die gefundenen Beiträge
foreach ($statuses as $status) {
    $statusId = $status['id'];

    if (isset($status['reblog']) && $status['reblog'] !== null) {
        echo "Beitrag ID $statusId ist ein Reblog und wird ignoriert.\n";
        continue;
    }
    
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

// 5. Bereinige die JSON-Datei von alten Einträgen
cleanupPostedIds($jsonFile, $cleanupDays);

echo "Bot-Lauf abgeschlossen.\n";

?>