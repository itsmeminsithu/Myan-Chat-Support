# Version 1.0.1 — QA record

## Automated checks

- `php tests/security_unit.php`: origin, proxy, URL and authorization checks.
- `php tests/chat_paging_unit.php`: 500-message mock-PDO paging and operator site restrictions.
- `node tests/widget_unit.cjs`: blocked storage, polling focus, drafts, session expiry and JavaScript API sends.
- `python3 tests/verify_project.py`: source checks, lint and packaged-file verification.

## Not covered by these checks

- Real MySQL migration or rollback against a populated database.
- Concurrent operator claims and high-volume guest traffic.
- iPhone Safari, Android Chrome, Firefox private mode or real WordPress embedding.
- HTTPS proxy cookies, CSP, email delivery, accessibility and penetration testing.

## Staging checklist

- [ ] Install a fresh instance and verify login/logout.
- [ ] Restore a 1.0.0 backup, migrate and assign operator site access.
- [ ] Send 120+ messages and page older history.
- [ ] Attempt competing operator claims.
- [ ] Confirm site A operators cannot access site B resources.
- [ ] Test widget focus, browser storage restrictions and expired tokens.
- [ ] Confirm page query strings and fragments are not recorded.
- [ ] Test Secure/HttpOnly/SameSite cookies over HTTPS.
- [ ] Test contact forms, alerts, retention and backup restoration.

Historical record; see `RELEASE_QA_v1.0.2.md` for current release checks.
