# Architecture

## Request flow

1. A website includes `widget.js` with its public site key.
2. The script mounts `widget.php` in an iframe served by the chat server.
3. The iframe communicates with `api.php` over same-origin HTTPS/HTTP and polls for messages.
4. The operator dashboard uses authenticated PHP sessions and CSRF tokens.
5. PHP uses PDO prepared statements to read/write MySQL data.

The widget iframe is isolated from the host page. Configured `frame-ancestors` rules limit permitted embedding origins. Host pages must allow third-party scripts and frames.

## Directories

| Path | Purpose |
| --- | --- |
| `app/public/` | HTTP entry points, widget and dashboard assets |
| `app/src/` | Authentication, database, chat and security code |
| `app/sql/schema.sql` | Initial MySQL schema |
| `app/storage/` | Private configuration and uploaded logo; persisted in Docker volume |
| `wordpress/myan-chat-widget/` | WordPress embed plugin |
| `scripts/` | macOS setup, migrations and retention job |
| `tests/` | CLI and browser-simulation checks |

## Main tables

`mcs_users`, `mcs_user_sites`, `mcs_sites`, `mcs_conversations`, `mcs_messages`, `mcs_contacts`, `mcs_typing`, `mcs_canned`, `mcs_rate_limits`, `mcs_audit`.

Operators get explicit website access through `mcs_user_sites`; admins have installation-wide access. Conversation claim uses an atomic update to reject competing claims. Visitor session tokens are random and stored as hashes on the server. The site key is public and cannot authenticate an operator.

## Polling and retention

Guest and operator clients poll for updates. PHP polling avoids a dedicated socket service but generates database traffic per active user. Load-test before scaling to high concurrency.

`maintenance` runs `scripts/cleanup.php` daily. It deletes conversations whose last-message activity exceeds `MCS_RETENTION_DAYS`, including inactive open chats. It also deletes offline contacts older than the retention period. MySQL audit and rate-limit data have separate cleanup windows. Backups require a separate retention policy.

## Compatibility

- Server: PHP 8.4, PDO MySQL, mbstring, MySQL 8.4.
- Clients: current Chrome, Safari, Firefox and Edge.
- Embeds: static HTML, WordPress and other sites that permit JavaScript and iframe embedding.
- Legacy PHP 5 and Internet Explorer 8 are not supported.
