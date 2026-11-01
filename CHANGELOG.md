# Changelog

## 1.0.2 — 2026-11-01

### Added
- Admin logo upload (PNG, JPEG, WebP; 4 MB limit), preview and removal.
- Shared branding in the operator sidebar, login page and guest widget.
- Color swatch, HEX input and live theme preview.
- Pyidaungsu Regular/Bold styles for the Myanmar interface.
- Browser regression checks for status, scrolling, color controls and mobile layout.

### Fixed
- Guest messages remain visible above the composer and emoji row.
- Operator presence selector loads the saved status instead of defaulting to Online.
- Status indicator and operator listing update after a successful status change.
- Cache-busting query parameters for widget CSS and JavaScript.
- Re-running the 1.0.1 migration does not overwrite a newer recorded version.

### Upgrade
- Source patch from 1.0.1; no database migration.
- See `docs/UPGRADE_v1.0.2.md`.

## 1.0.1 — 2026-11-01

### Added
- `mcs_user_sites` operator-to-website permissions and migration command.
- Keyset pagination for chat messages and administration lists.
- Daily retention job for inactive conversations and offline contacts.
- Operator Online, Away and Offline controls.
- Tests for message pagination, site permissions and guest widget interaction.

### Fixed
- Latest 100 messages load first, with older-message navigation.
- Guest polling preserves draft text, cursor and input focus.
- Widget recovers from blocked browser storage or an expired guest token.
- `MyanChat.sendMessage(text)` delivers messages to an active conversation.
- Visitor URLs exclude query strings and fragments.
- Trusted-proxy handling and configurable HTTPS Secure cookies.
- Session/CSRF renewal, failed-login counting and installer storage checks.
- Website permissions enforced on operator read/write actions.
- Mobile form sizing and closed-conversation typing behavior.

### Upgrade
- Database migration required from 1.0.0: `scripts/migrate-1.0.1.php`.
- Admin must assign website access to existing operators.
- See `docs/UPGRADE_v1.0.1.md`.

## 1.0.0

- Initial PHP/MySQL chat server, operator dashboard, iframe widget and WordPress plugin.
