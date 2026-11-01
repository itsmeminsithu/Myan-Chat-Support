# Version 1.0.2 — QA record

## Completed in the build environment

- PHP and JavaScript syntax checks.
- PHP security helper and mock-database paging/site-permission tests.
- Guest widget DOM-simulation tests.
- Chromium UI tests using mocked API responses: operator status, color controls, Myanmar locale, long-chat scroll and 375px viewport.
- Logo form interaction using a mocked response.
- HTTP serving test for a generated PNG through `brand-logo.php`: correct MIME type, PNG signature and `nosniff` header.
- Archive integrity checks.

## Not tested in that environment

- Actual Docker/MySQL installation and upgrade on a populated database.
- Cross-site iframe behavior through a public HTTPS reverse proxy.
- Real multi-operator status/claim races under network latency.
- iPhone Safari keyboard viewport, Android Chrome, Firefox privacy modes.
- Actual offline-email transport, sustained concurrency and restore from backup.

## Scope

1. Fixed the widget message-list scroll region so the composer does not cover new messages.
2. Loaded operator presence from server state and synchronized the status indicator after updates.
3. Added HEX, native color picker, swatch and immediate theme preview.
4. Added secured brand-logo upload and image serving. Source archives do not contain the user's original logo.
5. Added Pyidaungsu Regular/Bold for Myanmar UI. Font redistribution rights must be checked before public distribution.
6. Updated static asset versioning and WordPress plugin version.

**Database migration:** none for 1.0.1 → 1.0.2.
