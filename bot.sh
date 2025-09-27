##!/bin/bash

# Konfiguration
ACCESS_TOKEN="DEIN_ACCESS_TOKEN_HIER_EINFUEGEN"
INSTANCE="DEINE_INSTANZ_HIER.de"
HASHTAG="HASTAG_OHNE_#"
JSON_FILE="posted_ids.json"
CLEANUP_DAYS=30

# --- Funktionen ---

# Funktion zum Abrufen bereits geboosteter IDs
get_posted_ids() {
    if [ -f "$JSON_FILE" ]; then
        jq -r 'keys[]' "$JSON_FILE"
    else
        echo ""
    fi
}

# Funktion zum Hinzufügen einer neuen geboosteten ID
add_posted_id() {
    local status_id="$1"
    local timestamp=$(date +%s)
    
    if [ ! -f "$JSON_FILE" ]; then
        echo "{}" > "$JSON_FILE"
    fi
    
    jq --arg id "$status_id" --argjson ts "$timestamp" '.[$id] = $ts' "$JSON_FILE" > temp.json && mv temp.json "$JSON_FILE"
}

# --- Hauptlogik ---

echo "Starte Mastodon-Bot-Lauf..."

# 1. Hole alle geboosteten IDs
POSTED_IDS=$(get_posted_ids)
echo "Es gibt $(echo "$POSTED_IDS" | wc -l) IDs, die bereits geboostet wurden."

# 2. Hole die Hashtag-Timeline und behandle leere Antworten
TIMELINE_URL="https://$INSTANCE/api/v1/timelines/tag/$HASHTAG?limit=40"
TIMELINE_RESULT=$(curl -s -H "Authorization: Bearer $ACCESS_TOKEN" "$TIMELINE_URL")

# Überprüfen, ob die Antwort eine Fehlermeldung enthält
if [ "$(echo "$TIMELINE_RESULT" | jq -r 'if type == "object" and has("error") then .error else null end')" != "null" ]; then
    echo "API-Fehler: $(echo "$TIMELINE_RESULT" | jq -r '.error')"
    exit 1
fi

# Überprüfen, ob die Antwort leer ist
if [ -z "$(echo "$TIMELINE_RESULT" | jq -r '.[]?')" ]; then
    echo "Keine Ergebnisse gefunden."
    exit 0
fi

# 3. Verarbeite die gefundenen Beiträge in umgekehrter Reihenfolge
STATUSES=$(echo "$TIMELINE_RESULT" | jq -c 'reverse | .[]')

# 4. Verarbeite die gefundenen Beiträge
echo "$STATUSES" | while read -r status; do
    status_id=$(echo "$status" | jq -r '.id')
    reblog_status=$(echo "$status" | jq -r '.reblog')

    if [ "$reblog_status" != "null" ]; then
        echo "Beitrag $status_id ist ein Boost, wird ignoriert."
        continue
    fi

    if echo "$POSTED_IDS" | grep -q "$status_id"; then
        echo "Beitrag $status_id wurde bereits geboostet, wird ignoriert."
        continue
    fi

    # 5. Booste den Beitrag
    BOOST_URL="https://$INSTANCE/api/v1/statuses/$status_id/reblog"
    BOOST_RESULT=$(curl -s -X POST -H "Authorization: Bearer $ACCESS_TOKEN" "$BOOST_URL")

    if [ "$(echo "$BOOST_RESULT" | jq -r '.id' 2>/dev/null)" != "null" ]; then
        echo "Beitrag $status_id erfolgreich geboostet."
        add_posted_id "$status_id"
    else
        echo "Fehler beim Boosten von Beitrag $status_id."
    fi
done

echo "Bot-Lauf abgeschlossen."