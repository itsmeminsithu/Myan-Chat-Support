# Myan Chat Support v1.0.2

Self-hosted live chat for PHP/MySQL servers. Embed the widget on a website and manage conversations from the operator dashboard.

**Version:** 1.0.2  
**Developer:** Min SiThu — https://minsithu.org  
**License:** MIT (see `LICENSE`)

## Live preview

**https://itsmeminsithu.github.io/Myan-Chat-Support/**

That page is the installer design only, as a static preview. The chat itself needs PHP and MySQL, so it does not run on GitHub Pages — run it with Docker (below) or on your own PHP/MySQL server.


## Requirements

- Docker Desktop with Compose (local setup), or PHP 8.4, PDO MySQL, mbstring, OpenSSL and MySQL 8.4.
- A modern browser with JavaScript enabled. PHP 5 and Internet Explorer 8 are not supported.
- HTTPS for public deployments.

## Run locally (macOS)

```bash
cd ~/Downloads/Myan-Chat-Support-v1.0.2
chmod +x scripts/*.sh
./scripts/start-mac.sh
```

Docker Desktop must be running. The script creates a private `.env` on the first run and starts the containers.

| Page | Local URL |
| --- | --- |
| Setup | http://localhost:8088/install.php |
| Operator dashboard | http://localhost:8088/admin.php |
| Guest widget demo | http://localhost:8088/demo.php |

Use the `MCS_SETUP_KEY` displayed in Terminal to complete the installer. Keep it private. The Docker database values are preconfigured.

To stop the containers, run `./scripts/stop-mac.sh`. Do **not** use `docker compose down -v`: it deletes the database and application-storage volumes.

## Install the widget

Add the script to a page on an allowed website:

```html
<script src="https://chat.example.com/widget.js" data-site-id="YOUR_PUBLIC_SITE_KEY" async></script>
```

Replace the server URL and site key with the values in **Admin → Websites & Widget**. Add the website's exact origin to its allowlist. The site key is public; it is not an operator password or API secret.

JavaScript methods: `MyanChat.show()`, `hide()`, `toggle()`, `sendMessage(text)`. `sendMessage` sends only in an existing guest conversation; before that it prepares a draft.

WordPress plugin: `wordpress/myan-chat-widget/`.

## Operator features

- Multi-operator inbox with claim, transfer, reply and close actions.
- Conversation history, site-scoped operator access, offline requests and quick replies.
- English/Myanmar widget, welcome messages, colors, sound alerts and logo upload.
- Admin-only branding under **Settings → Brand Logo** (PNG/JPEG/WebP, up to 4 MB).
- `Developed by Min SiThu` stays in English for both interface languages.

## Upgrade

- **1.0.1 → 1.0.2:** Apply the source patch to the existing directory. No database migration. See `docs/UPGRADE_v1.0.2.md`.
- **1.0.0 → 1.0.1:** Back up the database, run the operator-site migration and assign website access. See `docs/UPGRADE_v1.0.1.md`.

Keep `.env`, private application configuration and Docker volumes. Verify chat history after updating.

## Configuration and operations

- Production deployments require HTTPS, backup/restore procedures and a privacy policy.
- Set `MCS_FORCE_HTTPS=1` only behind a correctly configured TLS reverse proxy.
- Set `MCS_TRUSTED_PROXIES` to specific trusted proxy IPs only.
- Docker's maintenance service uses `MCS_RETENTION_DAYS` (default: 90) to remove inactive conversations and old offline contacts.
- The supplied Compose file binds the web service to `127.0.0.1`; configure a production reverse proxy separately.

See `docs/SECURITY.md` and `docs/ARCHITECTURE.md` before deploying publicly.

## Tests

```bash
php tests/security_unit.php
php tests/chat_paging_unit.php
node tests/widget_unit.cjs
python3 tests/verify_project.py
# Optional: Playwright and Chromium required
python3 tests/ui_browser_regression.py
```

Automated checks cover syntax, helpers and simulated UI behavior. Live MySQL, concurrent operators, mobile devices and HTTPS still require deployment testing. Test coverage: `docs/RELEASE_QA_v1.0.2.md`.

## Documentation

- `docs/START-HERE-MY.md` — macOS setup (Myanmar)
- `docs/UPGRADE_v1.0.2.md` — patch installation
- `docs/UPGRADE_v1.0.1.md` — database migration
- `docs/LOGO-SETUP-MY.md` — branding
- `docs/ARCHITECTURE.md` — application structure
- `docs/SECURITY.md` — deployment controls
- `docs/TEST_PLAN.md` — manual verification

**Font licensing:** Confirm redistribution rights for the supplied Pyidaungsu font files before a public release.
