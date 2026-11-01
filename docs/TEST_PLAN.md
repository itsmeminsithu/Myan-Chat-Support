# Test plan

## Automated tests

Run from the repository root:

```bash
php tests/security_unit.php
php tests/chat_paging_unit.php
node tests/widget_unit.cjs
python3 tests/verify_project.py
python3 tests/ui_browser_regression.py  # optional; needs Playwright + Chromium
```

These checks cover helper functions, mock database pagination, widget interactions, source validation and browser-simulated UI behavior. They do not replace an actual server test.

## Integration checklist

- [ ] Start Docker and complete installation on a fresh database.
- [ ] Log in/out; verify expired sessions and rejected CSRF requests.
- [ ] Start a guest chat and exchange messages with an operator.
- [ ] Send 120+ messages; check latest-message view and older-message paging.
- [ ] Claim the same chat from two operator accounts; only one claim succeeds.
- [ ] Transfer and close a conversation; verify guest sends stop after closure.
- [ ] Confirm operators cannot access websites they have not been assigned.
- [ ] Test offline contact storage and configured email delivery.
- [ ] Check guest drafts and focus during four or more polling cycles.
- [ ] Test storage-restricted browsers and expired guest tokens.
- [ ] Upload/remove a brand logo; confirm it appears in admin and widget views.
- [ ] Check Online/Away/Offline status across dashboard and operator list.
- [ ] Test English/Myanmar text, emoji, color controls and narrow screens.
- [ ] Test the WordPress plugin on an allowed external site.
- [ ] Test HTTPS proxy, cookies, iframe CSP and allowed/disallowed origins.
- [ ] Run and restore a real database backup.

## Release conditions

Complete live MySQL, cross-browser, multi-operator and HTTPS verification before public deployment. See `RELEASE_QA_v1.0.2.md` for checks completed in the packaged development environment.
