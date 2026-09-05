#!/usr/bin/env bash
# Build a distributable ZIP of the plugin.
#
#   ./build.sh           full build, self-hosted distribution (kitmobley.com)
#   ./build.sh --free    WordPress.org build
#
# The --free build strips the self-hosted updater. Plugins in the WordPress.org
# directory update through the directory, and shipping an updater that overrides
# that is an outright rejection, not a judgement call. Everything else stays:
# the licence system only contacts the API when a user explicitly enters a key
# (revalidate() returns early with no key stored), so a free install never phones
# home, and the free tier is a real feature set rather than a trial.
set -euo pipefail

FREE_BUILD=0
for arg in "$@"; do
	case "$arg" in
		--free) FREE_BUILD=1 ;;
		*) echo "Unknown option: $arg" >&2; exit 1 ;;
	esac
done

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

if [[ "$FREE_BUILD" == "1" ]]; then
	echo "Building WordPress.org (free) variant: stripping the self-hosted updater."

	# WordPress.org reads readme.txt; README.md and dotfiles are repo cruft.
	rm -f "$STAGE/README.md" "$STAGE/.gitignore"
	rm -f "$STAGE/includes/class-updater.php"
	rm -f "$STAGE/includes/vendor/kitmobley-core/src/Updater.php"

	MAIN="$STAGE/$SLUG.php"
	# Drop the requires and the registration call. Anchored to the exact lines so
	# this fails loudly if the main file is restructured, rather than silently
	# shipping an updater to the directory.
	for pattern in \
		"require_once LSP_DIR . 'includes/vendor/kitmobley-core/src/Updater.php';" \
		"require_once LSP_DIR . 'includes/class-updater.php';" \
		"( new LSP_Updater() )->register();"
	do
		grep -qF "$pattern" "$MAIN" || { echo "free build: expected line not found: $pattern" >&2; exit 1; }
		grep -vF "$pattern" "$MAIN" > "$MAIN.tmp" && mv "$MAIN.tmp" "$MAIN"
	done

	# Unguarded use is what fatals. A class_exists() guard is how the shared admin
	# code stays valid in both builds, so it is allowed through.
	if grep -rnE "new LSP_Updater|PluginCore\\Updater" "$STAGE" | grep -v "class_exists" | grep -q .; then
		echo "free build: unguarded updater references survive in the stage:" >&2
		grep -rnE "new LSP_Updater|PluginCore\\Updater" "$STAGE" | grep -v "class_exists" >&2
		exit 1
	fi
	php -l "$MAIN" >/dev/null 2>&1 || { command -v php >/dev/null 2>&1 && { echo "free build: main file is not valid PHP after stripping" >&2; exit 1; }; }

	ZIP="$DIST/$SLUG-free-$VERSION.zip"
else
	ZIP="$DIST/$SLUG-$VERSION.zip"
fi
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

if [[ "$FREE_BUILD" == "1" ]]; then
	echo "Built (WordPress.org variant): $ZIP"
else
	cp "$ZIP" "$DIST/$SLUG-latest.zip"
	echo "Built: $ZIP"
fi
ls -lh "$DIST"/*.zip
