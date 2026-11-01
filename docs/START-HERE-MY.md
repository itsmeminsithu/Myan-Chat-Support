# Myan Chat Support — Mac မှာ Run လုပ်ရန်

**Version:** 1.0.2  
**Developed by Min SiThu**

## ပထမဆုံး Install လုပ်ခြင်း

Docker Desktop ကို ဖွင့်ပြီး Terminal မှာ—

```bash
cd ~/Downloads/Myan-Chat-Support-v1.0.2
chmod +x scripts/*.sh
./scripts/start-mac.sh
```

Installer: `http://localhost:8088/install.php`

Terminal မှာ ပြထားတဲ့ `MCS_SETUP_KEY` ကို Installer မှာ ဖြည့်ပါ။ Database Host `db`၊ Name `myanchat`၊ User `myanchat` ကို Default အတိုင်းထားပါ။ Admin Email နဲ့ Password သတ်မှတ်ပြီး Install လုပ်ပါ။

- Operator Dashboard: `http://localhost:8088/admin.php`
- Guest Demo: `http://localhost:8088/demo.php`

Browser Window နှစ်ခုမှာ ဖွင့်ပြီး Customer ↔ Operator စကားပြောခြင်းကို စမ်းပါ။

## v1.0.1 ကနေ Update

အလုပ်လုပ်နေတဲ့ v1.0.1 Folder ပေါ်မှာ Update Patch တင်ပါ။ Database Migration မလိုပါ။ Commands အတွက် `docs/UPGRADE_v1.0.2.md` ကိုကြည့်ပါ။

## v1.0.0 ကနေ Update

1. MySQL နဲ့ `.env` ကို Backup ယူပါ။
2. Source Update လုပ်ပြီး `scripts/migrate-1.0.1.php` Run ပါ။
3. **Admin → Operators → Website access** မှာ Operator တစ်ယောက်ချင်းစီအတွက် Website ရွေးပါ။
4. ပြီးမှ v1.0.2 Patch တင်ပါ။

အသေးစိတ် `docs/UPGRADE_v1.0.1.md` မှာ ရှိပါတယ်။

## သတိပြုရန်

- `.env`၊ `app_storage` နဲ့ `mysql_data` ကို မဖျက်ပါနဲ့။
- `docker compose down -v` မသုံးပါနဲ့။
- Public Server အတွက် HTTPS၊ Backup၊ Privacy Policy နဲ့ Mobile/Browser Tests လိုပါတယ်။
- `MCS_FORCE_HTTPS=1` ကို HTTPS Reverse Proxy မှာပဲ သုံးပါ။
