<?php

// ----------------------------------------------------
// Configuration
// ----------------------------------------------------

// Your Mastodon access token (from Settings > Development)
$accessToken = 'INSERT_YOUR_ACCESS_TOKEN_HERE'; 

// Your Mastodon instance (e.g. mastodon.social)
$instance = 'YOUR_INSTANCE_HERE.de';

// The hashtag or hashtags to search for
$hashtags = ['hashtag1', 'hashtag2', '...'];

// After how many days should an ID be deleted from the JSON file?
// (Prevents the file from getting too large)
$cleanupDays = 30; 

// Path to the JSON file where boosted IDs are stored
$jsonFile = __DIR__ . '/posted_ids.json';

// ----------------------------------------------------
// Functions
// ----------------------------------------------------

/**
 * Retrieves the IDs of already boosted posts from the JSON file.
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
 * Stores the ID of a newly boosted post in the JSON file.
 * @param string $jsonFile
 * @param int $statusId
 */
function addPostedId($jsonFile, $statusId) {
    $ids = getPostedIds($jsonFile);
    $ids[$statusId] = time();
    file_put_contents($jsonFile, json_encode($ids, JSON_PRETTY_PRINT));
}

/**
 * Removes old entries from the JSON file.
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
        echo "Old IDs deleted from $jsonFile.\n";
    }
}

/**
 * Executes an API request to Mastodon.
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
            error_log("JSON decode error: " . json_last_error_msg());
            return false;
        }
        return $data;
    } else {
        error_log("API request error ($httpCode): " . $response);
        return false;
    }
}

// ----------------------------------------------------
// Main bot logic (adapted for multiple hashtags)
// ----------------------------------------------------

echo "Starting Mastodon bot run...\n";

// 1. Retrieve all boosted IDs from the JSON file
$postedIds = getPostedIds($jsonFile);
echo "There are " . count($postedIds) . " IDs that have already been boosted.\n";

foreach ($hashtags as $hashtag) {
    echo "Searching for posts with hashtag #$hashtag...\n";

    // Fetch the hashtag timeline
    $timelineUrl = "https://$instance/api/v1/timelines/tag/$hashtag?limit=40";
    $timelineResult = callApi($timelineUrl, $accessToken);

    if ($timelineResult === false || count($timelineResult) === 0) {
        echo "No results found or API error for #$hashtag.\n";
        continue; // Skip to next hashtag
    }

    // Reverse the order of posts to boost newest ones first
    $statuses = array_reverse($timelineResult);

    // 2. Process the found posts
    foreach ($statuses as $status) {
        $statusId = $status['id'];

        if (isset($status['reblog']) && $status['reblog'] !== null) {
            echo "Post ID $statusId is a reblog and will be ignored.\n";
            continue;
        }

        if (isset($postedIds[$statusId])) {
            echo "Post ID $statusId has already been boosted and will be ignored.\n";
            continue;
        }

        // Boost the post
        $boostUrl = "https://$instance/api/v1/statuses/$statusId/reblog";
        $boostResult = callApi($boostUrl, $accessToken, 'POST');

        if ($boostResult) {
            echo "Post ID $statusId boosted successfully!\n";
            addPostedId($jsonFile, $statusId);
        } else {
            echo "Error boosting post ID $statusId.\n";
        }
    }
}

// 3. Clean up old entries from the JSON file
cleanupPostedIds($jsonFile, $cleanupDays);

echo "Bot run completed.\n";

?>
