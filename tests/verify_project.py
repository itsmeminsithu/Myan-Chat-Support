"""v1.0.2 source/package regression checks; not a real MySQL or Docker e2e test."""
from pathlib import Path
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[1]
required = [
 'README.md','CHANGELOG.md','LICENSE','Dockerfile','compose.yaml','.dockerignore',
 'app/sql/schema.sql','app/sql/migrations/001_operator_sites.sql',
 'app/src/security.php','app/src/auth.php','app/src/database.php','app/src/chat.php','app/src/bootstrap.php',
 'app/public/install.php','app/public/login.php','app/public/admin.php','app/public/api.php',
 'app/public/brand-logo.php','app/public/brand-logo-upload.php','docs/LOGO-SETUP-MY.md',
 'app/public/widget.php','app/public/widget.js','app/public/assets/widget-frame.js',
 'app/public/assets/admin.js','app/public/assets/admin.css','app/public/assets/widget.css',
 'app/public/demo.php','scripts/start-mac.sh','scripts/stop-mac.sh',
 'app/public/assets/fonts/Pyidaungsu-Regular.ttf','app/public/assets/fonts/Pyidaungsu-Bold.ttf',
 'tests/ui_browser_regression.py','docs/UPGRADE_v1.0.2.md','docs/RELEASE_QA_v1.0.2.md',
 'scripts/cleanup.php','scripts/migrate-1.0.1.php',
 'wordpress/myan-chat-widget/myan-chat-widget.php','docs/UPGRADE_v1.0.1.md',
 'docs/RELEASE_QA_v1.0.1.md','tests/chat_paging_unit.php','tests/widget_unit.cjs',
]
for path in required:
 assert (ROOT/path).is_file(), f'Missing {path}'
assert not (ROOT/'.env').exists(), 'Live passwords must never ship'
assert 'Version: 1.0.2' in (ROOT/'wordpress/myan-chat-widget/myan-chat-widget.php').read_text()
assert 'Myan Chat Support v1.0.2' in (ROOT/'README.md').read_text()
assert 'mcs_user_sites' in (ROOT/'app/sql/schema.sql').read_text()
api=(ROOT/'app/public/api.php').read_text()
for snippet in ['mcs_allowed_site_ids(', 'mcs_require_conversation(', 'mcs_require_site(',
                'mcs_can_access_site(', "case 'operator_sites'", "case 'alerts'", "case 'messages'",
                'mcs_has_older_messages(', 'next_before_at', "case 'contacts'", "case 'canned'",
                'mcs_check_csrf', 'FOR UPDATE', "guest_send", "guest_end"]:
 assert snippet in api, f'Expected scope/security/pagination: {snippet}'
chat=(ROOT/'app/src/chat.php').read_text()
assert 'ORDER BY m.id DESC LIMIT' in chat and 'ORDER BY m.id ASC LIMIT' in chat
security=(ROOT/'app/src/security.php').read_text()
assert "MCS_TRUSTED_PROXIES" in security and "['query']" not in security
widget=(ROOT/'app/public/assets/widget-frame.js').read_text()
for marker in ['function updateChat()', 'function resetExpired()', 'function getStore(',
               "const body=draft.trim()", "state==='chat'", 'setTimeout(()=>poll(),0)']:
 assert marker in widget, marker
assert "page_url:location.origin+location.pathname" in (ROOT/'app/public/widget.js').read_text()
compose=(ROOT/'compose.yaml').read_text()
for marker in ['maintenance:', 'MCS_FORCE_HTTPS', 'MCS_TRUSTED_PROXIES','MCS_RETENTION_DAYS']:
 assert marker in compose
assert 'Developed by Min SiThu' in (ROOT/'app/public/admin.php').read_text()
adminjs=(ROOT/'app/public/assets/admin.js').read_text()
assert "availability=d.user.availability" in adminjs
assert 'function reflectOwnAvailability(next)' in adminjs
assert 'function syncColorControl(value)' in adminjs
assert 'inputmode="text"' in (ROOT/'app/public/admin.php').read_text()
assert 'brandLogoForm' in (ROOT/'app/public/admin.php').read_text()
assert 'mcs_need_admin()' in (ROOT/'app/public/brand-logo-upload.php').read_text()
assert 'mcs_check_csrf(' in (ROOT/'app/public/brand-logo-upload.php').read_text()
assert 'is_uploaded_file(' in (ROOT/'app/public/brand-logo-upload.php').read_text()
assert 'brand-logo.php' in (ROOT/'app/public/assets/widget-frame.js').read_text()
assert 'function refreshBrandLogo()' in adminjs

assert 'CASE WHEN availability=' in api and 'last_seen_at < UTC_TIMESTAMP()' in api
assert 'forceScrollBottom' in widget and 'chat-lines' in (ROOT/'app/public/assets/widget.css').read_text()

if shutil.which('php'):
 for file in ROOT.rglob('*.php'):
  result=subprocess.run(['php','-l',str(file)],capture_output=True,text=True)
  assert result.returncode==0,f'PHP syntax: {file}\n{result.stderr}'
 for name in ['security_unit.php','chat_paging_unit.php']:
  result=subprocess.run(['php',str(ROOT/'tests'/name)],capture_output=True,text=True)
  assert result.returncode==0, result.stdout+'\n'+result.stderr
  print(f'{name}: {result.stdout.count("PASS ")} passed')
if shutil.which('node'):
 for file in ROOT.rglob('*.js'):
  result=subprocess.run(['node','--check',str(file)],capture_output=True,text=True)
  assert result.returncode==0,f'JS syntax: {file}\n{result.stderr}'
 result=subprocess.run(['node',str(ROOT/'tests/widget_unit.cjs')],capture_output=True,text=True)
 assert result.returncode==0, result.stdout+'\n'+result.stderr
 print(result.stdout.strip())
try:
 import yaml
 parsed=yaml.safe_load(compose)
 assert set(['db','web','maintenance']).issubset(parsed['services'])
 print('Docker Compose YAML: parsed')
except ImportError:
 print('YAML validation skipped: PyYAML unavailable')
print(f'PASS package verification: {len(required)} required files + source checks + executable regressions')
