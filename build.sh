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
