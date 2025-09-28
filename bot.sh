#!/bin/bash

# Configuration
ACCESS_TOKEN="YOUR TOKEN"
INSTANCE="YOUR MASTODON INSTANZ"  # Your Mastodon instance without https://
HASHTAGS="hashtag1 haschtag2 ..."  # Add all hashtags here separated by spaces
JSON_FILE="posted_ids.json"
CLEANUP_DAYS=30

# --- Functions ---

# Function to fetch already boosted IDs
get_posted_ids() {
    if [ -f "$JSON_FILE" ]; then
        jq -r 'keys[]' "$JSON_FILE"
    else
        echo ""
    fi
}

# Function to add a new boosted ID
add_posted_id() {
    local status_id="$1"
    local timestamp=$(date +%s)
    
    if [ ! -f "$JSON_FILE" ]; then
        echo "{}" > "$JSON_FILE"
    fi
    
    jq --arg id "$status_id" --argjson ts "$timestamp" '.[$id] = $ts' "$JSON_FILE" > temp.json && mv temp.json "$JSON_FILE"
}

# Function to remove old entries from the JSON file
cleanup_posted_ids() {
    if [ -f "$JSON_FILE" ]; then
        local cutoff_time=$(($(date +%s) - ($CLEANUP_DAYS * 24 * 60 * 60)))
        local old_count=$(jq 'keys | length' "$JSON_FILE")
        
        jq "del(.[] | select(. < $cutoff_time))" "$JSON_FILE" > temp.json && mv temp.json "$JSON_FILE"
        
        local new_count=$(jq 'keys | length' "$JSON_FILE")
        if [ "$old_count" -ne "$new_count" ]; then
            echo "Old IDs deleted from $JSON_FILE."
        fi
    fi
}

# --- Main logic ---

echo "Starting Mastodon bot run..."

# 1. Fetch all boosted IDs
POSTED_IDS=$(get_posted_ids)
echo "There are $(echo "$POSTED_IDS" | wc -l) IDs that have already been boosted."

# 2. Loop through each hashtag in the list
for HASHTAG in $HASHTAGS; do
    echo "Searching for posts with hashtag #$HASHTAG..."

    # Fetch the hashtag timeline and handle empty responses
    TIMELINE_URL="https://$INSTANCE/api/v1/timelines/tag/$HASHTAG?limit=40"
    TIMELINE_RESULT=$(curl -s -H "Authorization: Bearer $ACCESS_TOKEN" "$TIMELINE_URL")

    if [ "$(echo "$TIMELINE_RESULT" | jq -r 'if type == "object" and has("error") then .error else null end')" != "null" ]; then
        echo "API error for #$HASHTAG: $(echo "$TIMELINE_RESULT" | jq -r '.error')"
        continue
    fi

    if [ -z "$(echo "$TIMELINE_RESULT" | jq -r '.[]?')" ]; then
        echo "No results found for #$HASHTAG."
        continue
    fi

    # Process the found posts in reverse order
    STATUSES=$(echo "$TIMELINE_RESULT" | jq -c 'reverse | .[]')

    # Process the found posts
    echo "$STATUSES" | while read -r status; do
        status_id=$(echo "$status" | jq -r '.id')
        reblog_status=$(echo "$status" | jq -r '.reblog')

        if [ "$reblog_status" != "null" ]; then
            echo "Post $status_id is a boost, ignoring."
            continue
        fi

        if echo "$POSTED_IDS" | grep -q "$status_id"; then
            echo "Post $status_id has already been boosted, ignoring."
            continue
        fi

        # Boost the post
        BOOST_URL="https://$INSTANCE/api/v1/statuses/$status_id/reblog"
        BOOST_RESULT=$(curl -s -X POST -H "Authorization: Bearer $ACCESS_TOKEN" "$BOOST_URL")

        if [ "$(echo "$BOOST_RESULT" | jq -r '.id' 2>/dev/null)" != "null" ]; then
            echo "Post $status_id boosted successfully."
            add_posted_id "$status_id"
        else
            echo "Error boosting post $status_id."
        fi
    done
done

# 3. Clean up old entries from the JSON file
cleanup_posted_ids

echo "Bot run completed."
