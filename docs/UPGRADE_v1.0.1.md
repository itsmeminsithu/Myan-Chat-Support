# Upgrade 1.0.0 → 1.0.1

Version 1.0.1 adds `mcs_user_sites`. Existing non-admin operators have no website access until the administrator assigns it.

## Back up

Run in the existing Docker project directory:

```bash
mkdir -p backups
docker compose exec -T db sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction myanchat' > backups/myanchat-pre-v101.sql
cp -p .env backups/mcs-env-backup
chmod 600 backups/*
```

Backups contain secrets and customer messages. Keep them outside public folders and Git repositories. Confirm the SQL file is nonempty.

## Apply

1. Keep the original `.env` and Docker volumes.
2. Replace application source with the new version; do not replace private storage.
3. Rebuild and migrate:

```bash
docker compose up -d --build
docker compose exec web php /var/www/myan-chat/scripts/migrate-1.0.1.php
```

4. Open **Admin → Operators → Website access** and assign sites to existing operators.
5. Test guest chat, operator permissions and stored history.

The migration is designed to be rerunnable. If it fails, inspect `docker compose logs web db`, correct the cause and retry. Restore the database only from a verified backup.

**Do not run `docker compose down -v`.**
