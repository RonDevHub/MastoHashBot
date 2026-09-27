# 🤖 MastoHashBot

MastoHashBot is a lightweight automation bot for Mastodon.  
It automatically searches for posts (toots) containing specific hashtags and boosts them.  
The bot ensures that no post is boosted twice by storing processed IDs in a JSON file and also cleans up old entries after a configurable period of time.

[![Buy me a coffee](https://mini-badges.rondev.de/icon/cuptogo/Buy_me_a_Coffee-c1d82f-222/social "Buy me a coffee")](https://www.buymeacoffee.com/RonDev)
[![Buy me a coffee](https://mini-badges.rondev.de/icon/cuptogo/ko--fi.com-c1d82f-222/social "Buy me a coffee")](https://ko-fi.com/U6U31EV2VS)
[![Sponsor me](https://mini-badges.rondev.de/icon/hearts-red/Sponsor_me/social "Sponsor me")](https://github.com/sponsors/RonDevHub)
[![Pizza Power](https://mini-badges.rondev.de/icon/pizzaslice/Buy_me_a_pizza/social "Pizza Power")](https://www.paypal.com/donate/?hosted_button_id=PWY939TPCQ3RA)
---

## ✨ Features

- 🔎 Searches for multiple hashtags across any Mastodon instance  
- 🚀 Automatically boosts posts (no duplicates)  
- 🧹 Cleans up old entries from the JSON file after `X` days  
- 📂 Stores boosted IDs locally in `posted_ids.json`  
- ⚙️ Configurable via environment variables or script variables  

---

## 📋 Requirements

- PHP **>= 7.4** (for the PHP version of the bot)  
- cURL extension enabled in PHP  
- Bash + `curl` + `jq` (for the Bash version of the bot)  
- A Mastodon **access token** with `write:accounts` and `write:statuses` permissions  
- A Mastodon **instance URL** (e.g. `mastodon.social`)

---

## ⚙️ Setup

### 1. Get a Mastodon Access Token

1. Log in to your Mastodon instance  
2. Go to **Preferences > Development > New Application**  
3. Give it a name, e.g. `MastoHashBot`  
4. Select required permissions:
   - `read:statuses`
   - `write:accounts`
   - `write:statuses`
5. Copy the generated **Access Token**

---

### 2. Configure the Bot

Edit the script (PHP or Bash) and set:

```php
// PHP Version
$accessToken = 'INSERT_YOUR_ACCESS_TOKEN_HERE';
$instance = 'YOUR_INSTANCE_HERE';
$hashtags = ['hashtag1', 'hashtag2'];
$cleanupDays = 30;
```
```bash
# Bash Version
ACCESS_TOKEN="INSERT_YOUR_ACCESS_TOKEN_HERE"
INSTANCE="YOUR_INSTANCE_HERE"
HASHTAGS="hashtag1 hashtag2 hashtag3"
CLEANUP_DAYS=30
```
---

### 3. Run the Bot

PHP Version:
```
php mastohashbot.php
```
Bash Version:
```
bash mastohashbot.sh
```
You should see log messages like:
```
Starting Mastodon bot run...
There are 0 IDs that have already been boosted.
Searching for posts with hashtag #fediGive...
Post ID 123456 boosted successfully!
Bot run completed.
```
---

### 🗑 JSON File Cleanup

The bot stores boosted post IDs in a JSON file (`posted_ids.json`).
To prevent the file from growing indefinitely, entries older than `$cleanupDays` are automatically removed.

---

### 🤝 Usage in Production

- Run the bot regularly via cronjob or a systemd timer, for example:
```
# Run every 15 minutes
*/15 * * * * php /path/to/mastohashbot.php >> /path/to/mastohashbot.log 2>&1
```
- Monitor the logs (`mastohashbot.log`) to check the activity.

---

### 📦 Folder Structure
```
.
├── mastohashbot.php    # PHP version of the bot
├── mastohashbot.sh     # Bash version of the bot
├── posted_ids.json     # Stores boosted IDs
└── README.md           # Project documentation
```

---

### 🔒 Security Notes
- Keep your Access Token secret – it allows actions on your Mastodon account.
- Do not commit your token into version control (e.g. GitHub).
- Consider using environment variables or `.env` files instead of hardcoding tokens.

---

### 🌐 Example Use Cases
- 📢 Community hashtags (e.g. `#fediGive`, `#fediHelp`)
- 🛒 Local marketplaces (e.g. `#flohmarkt`)
- 🐘 Automated content boosting for niche topics

---

### 🛠 Troubleshooting
- Error: API request error (401 Unauthorized)
→ Check your access token and permissions
- Error: No results found
→ Make sure the hashtag exists and is used
- Error: JSON decode error
→ The Mastodon API returned unexpected data (try again later)

---

### 📜 License
MIT License – free to use, modify, and distribute.

---

### 👨‍💻 Author
Developed with ❤️ for the Fediverse.
Feel free to fork, contribute, or open issues!