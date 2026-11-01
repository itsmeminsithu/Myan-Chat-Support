# Security

## Implemented controls

- Apache serves only `app/public/`. Database credentials and private configuration remain outside the document root.
- Setup requires a private setup key. The installer refuses to overwrite an existing operator installation.
- Operator passwords use PHP password hashing; sessions use HttpOnly/SameSite cookies and inactivity expiry.
- Operator writes require authentication, permission checks and CSRF tokens.
- Guest requests require a conversation ID and guest token; stored tokens are hashed.
- Operator website access is restricted through `mcs_user_sites`.
- Database queries use PDO prepared statements. Chat text is escaped or rendered as text nodes.
- Visitor URL tracking strips query strings, fragments and credentials.
- Embed origins are checked against configured origins and the iframe's CSP `frame-ancestors` rules.
- Guest and login endpoints have database-backed rate limits; IPs are stored as keyed hashes.
- Brand logo uploads require admin permission and CSRF validation. PNG/JPEG/WebP type, file size and dimensions are checked. Files are served as images from private storage with `nosniff`; SVG uploads are disabled.

## Production checklist

- [ ] Deploy over HTTPS; enable HSTS at the public edge where appropriate.
- [ ] Set `MCS_FORCE_HTTPS=1` behind a trusted TLS-terminating proxy.
- [ ] Configure `MCS_TRUSTED_PROXIES` with exact proxy IPs. The proxy must replace incoming forwarded-IP headers.
- [ ] Confirm the installer cannot be reused after initial setup.
- [ ] Test permission boundaries for two or more websites and operators.
- [ ] Test parallel claims, chat closure and guest-token expiry under load.
- [ ] Check CSP, iframe embedding and cookie behavior from external domains.
- [ ] Configure database and private-file backups; test restoration.
- [ ] Set a retention policy for messages, contacts and backups.
- [ ] Test offline-email delivery using the selected SMTP/mail service.
- [ ] Add monitoring and abuse controls for public endpoints.
- [ ] Review data export/deletion procedures and applicable privacy obligations.

The public site key is not a secret. An attacker can send HTTP requests without a browser; origin checks are not a substitute for authentication, rate limiting or monitoring.

The project has automated tests but has not undergone an independent penetration test. Verify a production configuration before accepting real customer data.
