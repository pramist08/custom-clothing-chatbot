# Custom Clothing WhatsApp Chatbot (PHP + GPT-5 via RapidAPI)

This repo is a lightweight WhatsApp chatbot that connects Twilio Sandbox (WhatsApp) to a GPT-5 model available via RapidAPI. It saves order state to MySQL and can optionally generate preview images via OpenAI Images API.

## Files included
- `webhook.php` — main Twilio webhook (responds with TwiML)
- `config.php` — edit database and API keys here
- `helpers.php` — helper functions (RapidAPI calls, DB helpers, image save)
- `database.sql` — SQL to create database and tables
- `public/uploads/.gitkeep` — keeps uploads folder in Git

## Quick setup (local testing)
1. Edit `config.php` to set DB credentials and replace `RAPIDAPI_KEY` with your RapidAPI key.
2. Create the database/tables:
   ```bash
   mysql -u root -p < database.sql
   ```
3. Ensure uploads folder exists:
   ```bash
   mkdir -p public/uploads
   chmod 755 public/uploads
   ```
4. Start PHP built-in server:
   ```bash
   php -S 0.0.0.0:8080
   ```
5. Expose to the internet for Twilio using `ngrok`:
   ```bash
   ngrok http 8080
   ```
   Set `BASE_URL` in `config.php` to the ngrok URL (e.g. https://abcd.ngrok.io).

## Deploy to Render (no Git required locally)
1. Push these files to a GitHub repo (or upload via GitHub web UI).
2. Connect the repo to Render → New Web Service → choose PHP environment.
3. Deploy. Render will publish your app at `https://<service>.onrender.com`.
4. In Twilio Sandbox settings, set **When a message comes in** to:
   `https://<your-render-url>/webhook.php`

## Twilio Sandbox setup
1. Sign up or log into Twilio, go to Messaging → Try WhatsApp → Sandbox.
2. Join sandbox from your phone using the provided join code.
3. Set the incoming webhook URL to your Render webhook URL (see above).

## Notes & security
- This project stores API keys in `config.php` for simplicity. For production, use Render Environment Variables and `getenv()`.
- The model is instructed to return JSON only; if the model returns invalid JSON the bot asks the user to try again.
- For persistent storage of images, use Cloud Storage (S3 / Google Cloud Storage) instead of local disk.

## Next steps (optional)
- Add Twilio request validation
- Add an admin/vendor panel (`/admin/orders`) behind authentication
- Use Cloud SQL (managed DB) or external MySQL service for production
- Use Render environment variables for secrets (recommended)
