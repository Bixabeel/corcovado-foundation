#!/usr/bin/env bash
# Builds installable ZIPs of the theme and the plugin into dist/.
# The plugin ZIP includes the original images and PDFs (migration/source/) used by the importer.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN="$ROOT/wp-content/plugins/corcovado-foundation-core"
THEME="$ROOT/wp-content/themes/corcovado-foundation"
DIST="$ROOT/dist"

python3 "$ROOT/tools/extract-content.py" >/dev/null

# Copy every file referenced by content.json into migration/source/ (same relative paths).
rm -rf "$PLUGIN/migration/source"
python3 - "$ROOT" "$PLUGIN" <<'PY'
import json, os, shutil, sys
root, plugin = sys.argv[1], sys.argv[2]
data = json.load(open(os.path.join(plugin, 'migration/data/content.json'), encoding='utf-8'))
for m in data['media']:
    src = os.path.join(root, m['key'])
    dst = os.path.join(plugin, 'migration/source', m['key'])
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    shutil.copy2(src, dst)
print('copied %d source files' % len(data['media']))
PY

mkdir -p "$DIST"
rm -f "$DIST/corcovado-foundation.zip" "$DIST/corcovado-foundation-core.zip"
(cd "$ROOT/wp-content/themes" && zip -qr -X "$DIST/corcovado-foundation.zip" corcovado-foundation -x '*.DS_Store')
(cd "$ROOT/wp-content/plugins" && zip -qr -X "$DIST/corcovado-foundation-core.zip" corcovado-foundation-core -x '*.DS_Store')
ls -l "$DIST"
