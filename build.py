#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parent
VERSION = (ROOT / 'VERSION').read_text().strip()
NAME = 'smartfancontrol'
AUTHOR = 'blackboygirl'
GITHUB = 'blackboygirl/smartfancontrol'
PLUGIN_URL = f'https://raw.githubusercontent.com/{GITHUB}/main/{NAME}.plg'
SUPPORT_URL = f'https://github.com/{GITHUB}/issues'

FILES = [
    ('/usr/local/emhttp/plugins/smartfancontrol/SmartFanControl.page', '0644', ROOT / 'source/usr/local/emhttp/plugins/smartfancontrol/SmartFanControl.page'),
    ('/usr/local/emhttp/plugins/smartfancontrol/SmartFanControl.Dashboard.page', '0644', ROOT / 'source/usr/local/emhttp/plugins/smartfancontrol/SmartFanControl.Dashboard.page'),
    ('/usr/local/emhttp/plugins/smartfancontrol/api.php', '0644', ROOT / 'source/usr/local/emhttp/plugins/smartfancontrol/api.php'),
    ('/usr/local/emhttp/plugins/smartfancontrol/default.json', '0644', ROOT / 'source/usr/local/emhttp/plugins/smartfancontrol/default.json'),
    ('/usr/local/emhttp/plugins/smartfancontrol/scripts/smartfan-daemon.php', '0755', ROOT / 'source/usr/local/emhttp/plugins/smartfancontrol/scripts/smartfan-daemon.php'),
    ('/usr/local/emhttp/plugins/smartfancontrol/scripts/rc.smartfancontrol', '0755', ROOT / 'source/usr/local/emhttp/plugins/smartfancontrol/scripts/rc.smartfancontrol'),
    ('/usr/local/emhttp/plugins/smartfancontrol/scripts/diagnose.sh', '0755', ROOT / 'source/usr/local/emhttp/plugins/smartfancontrol/scripts/diagnose.sh'),
    ('/etc/rc.d/rc.smartfancontrol', '0755', ROOT / 'source/etc/rc.d/rc.smartfancontrol'),
]

def normalized(path: Path) -> str:
    s = path.read_text()
    if ']]>' in s:
        raise SystemExit(f'CDATA terminator found in {path}')
    s = re.sub(r"const SFC_VERSION = '[^']+';", f"const SFC_VERSION = '{VERSION}';", s)
    s = re.sub(r'Smart Fan Control (?:\d{4}\.\d{2}\.\d{2}[a-z]?|\d+\.\d+\.\d+)', f'Smart Fan Control {VERSION}', s)
    return s.rstrip('\n')

def cdata(s: str) -> str:
    if ']]>' in s:
        raise SystemExit('CDATA terminator found in embedded content')
    return '<![CDATA[\n' + s + '\n]]>'

changelog = (ROOT / 'CHANGELOG.md').read_text().strip()
pre = normalized(ROOT / 'packaging/install-pre.sh')
post = normalized(ROOT / 'packaging/install-post.sh')
remove = normalized(ROOT / 'packaging/remove.sh')

parts = [
    "<?xml version='1.0' standalone='yes'?>",
    '<!DOCTYPE PLUGIN [',
    f'<!ENTITY name "{NAME}">',
    f'<!ENTITY author "{AUTHOR}">',
    f'<!ENTITY version "{VERSION}">',
    '<!ENTITY launch "Settings/SmartFanControl">',
    f'<!ENTITY github "{GITHUB}">',
    f'<!ENTITY pluginURL "{PLUGIN_URL}">',
    ']>',
    f'<PLUGIN name="&name;" author="&author;" version="&version;" launch="&launch;" pluginURL="&pluginURL;" min="6.12.0" support="{SUPPORT_URL}" icon="icon-fan">',
    '<CHANGES>',
    cdata(changelog),
    '</CHANGES>',
    '<FILE Run="/bin/bash">',
    '<INLINE>' + cdata(pre) + '</INLINE>',
    '</FILE>',
]

for dest, mode, src in FILES:
    parts.extend([
        f'<FILE Name="{dest}" Mode="{mode}">',
        '<INLINE>' + cdata(normalized(src)) + '</INLINE>',
        '</FILE>',
    ])

parts.extend([
    '<FILE Run="/bin/bash">',
    '<INLINE>' + cdata(post) + '</INLINE>',
    '</FILE>',
    '<FILE Run="/bin/bash" Method="remove">',
    '<INLINE>' + cdata(remove) + '</INLINE>',
    '</FILE>',
    '</PLUGIN>',
])

out = ROOT / f'{NAME}.plg'
out.write_text('\n'.join(parts) + '\n')

import hashlib
digest = hashlib.sha256(out.read_bytes()).hexdigest()
(ROOT / 'SHA256SUMS').write_text(f'{digest}  {out.name}\n')
print(out)
