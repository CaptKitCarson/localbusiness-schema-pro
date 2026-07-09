#!/usr/bin/env bash
# Build a distributable ZIP of the plugin.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
SLUG="localbusiness-schema-pro"
VERSION="$(grep -oE "Version:\s+[0-9]+\.[0-9]+\.[0-9]+" "$ROOT/$SLUG.php" | awk '{print $2}')"

if [[ -z "${VERSION:-}" ]]; then
	echo "Could not detect version from plugin header." >&2
	exit 1
fi

# Sync shared kitmobley/wp-plugin-core library into includes/vendor/.
CORE_SRC="${WP_PLUGIN_CORE:-$HOME/Projects/wp-plugin-core}"
CORE_DEST="$ROOT/includes/vendor/kitmobley-core"
if [[ -d "$CORE_SRC/src" ]]; then
	mkdir -p "$CORE_DEST/src"
	rsync -a --delete "$CORE_SRC/src/" "$CORE_DEST/src/"
	cp "$CORE_SRC/LICENSE.txt" "$CORE_DEST/LICENSE.txt" 2>/dev/null || true
	echo "Synced wp-plugin-core from $CORE_SRC"
fi

STAGE="$ROOT/dist/build/$SLUG"
DIST="$ROOT/dist"
rm -rf "$STAGE"
mkdir -p "$STAGE"

rsync -a --exclude='dist' --exclude='.git' --exclude='node_modules' \
	--exclude='build.sh' --exclude='*.log' --exclude='.DS_Store' \
	--exclude='tests' --exclude='.editorconfig' \
	"$ROOT/" "$STAGE/"

ZIP="$DIST/$SLUG-$VERSION.zip"
rm -f "$ZIP"
if command -v zip >/dev/null 2>&1; then
	( cd "$DIST/build" && zip -qr "$ZIP" "$SLUG" )
else
	python3 -c "
import os, zipfile
root = os.path.join('$DIST', 'build')
slug = '$SLUG'
out = '$ZIP'
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
	base = os.path.join(root, slug)
	for dirpath, _, filenames in os.walk(base):
		for fn in filenames:
			abs_path = os.path.join(dirpath, fn)
			arcname = os.path.relpath(abs_path, root)
			z.write(abs_path, arcname)
"
fi

cp "$ZIP" "$DIST/$SLUG-latest.zip"
echo "Built: $ZIP"
ls -lh "$DIST"/*.zip
