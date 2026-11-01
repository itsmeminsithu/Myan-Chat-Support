# Upgrade 1.0.1 → 1.0.2 (Mac)

This update changes application files only. No SQL migration is required.

## Back up the database

From your existing project folder:

```bash
cd ~/Downloads/Myan-Chat-Support-v1.0.1
docker compose exec -T db sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction myanchat' > "$HOME/Downloads/myanchat-backup-$(date +%Y%m%d-%H%M%S).sql"
```

The SQL dump contains chat messages and customer data. Move it to a private location and check that it is not empty.

## Apply the patch

Extract `Myan-Chat-Support-v1.0.2-Update-Patch.zip`, then run:

```bash
cd "$HOME/Downloads/Myan-Chat-Support-v1.0.2-Update-Patch"
bash ./apply-update-mac.sh "$HOME/Downloads/Myan-Chat-Support-v1.0.1"
```

The script backs up replaced source files under `.mcs-update-backups/`, copies the update and rebuilds Compose without removing volumes. Adjust the paths if macOS renamed your extracted folders.

## Verify

1. Open `http://localhost:8088/admin.php`; reload if assets are cached.
2. Check Online/Away/Offline on the dashboard and Operators page.
3. Open **Settings**; change the HEX color and check the preview.
4. Upload a PNG/JPEG/WebP logo under **Settings → Brand Logo**.
5. Open `http://localhost:8088/demo.php`; verify two-way chat and long-message scrolling.
6. Confirm previous conversations and operator accounts remain available.

## Notes

- Keep `.env` and the `mysql_data` / `app_storage` volumes.
- Do not run `docker compose down -v`.
- For 1.0.0 installations, apply the 1.0.1 database migration first (`UPGRADE_v1.0.1.md`).
- A new project folder can reference the same Compose project volumes. Update the existing folder instead of rerunning setup.
