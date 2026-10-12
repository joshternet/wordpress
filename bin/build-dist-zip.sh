#!/usr/bin/env bash
# Build the WordPress.org / Upload Plugin zip from .distignore.
#
# Output: dist/joshternet-<version>.zip
# Expands to slug folder joshternet/ (not versioned).
#
# Usage:
#   bin/build-dist-zip.sh [version]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SLUG='joshternet'
VERSION="${1:-}"
if [ -z "$VERSION" ]; then
	VERSION="$(sed -n "s/^define( 'JOSHTERNET_VERSION', '\\([^']*\\)' );/\\1/p" joshternet.php | head -1)"
fi
VERSION="${VERSION#v}"

"$ROOT/bin/verify-version.sh" "$VERSION"

STAGE="$(mktemp -d)"
PLUGIN_DIR="${STAGE}/${SLUG}"
mkdir -p "$PLUGIN_DIR"
trap 'rm -rf "$STAGE"' EXIT

RSYNC_EXCLUDES=(
	--exclude '.DS_Store'
	--exclude '.git'
	--exclude '*.zip'
	--exclude 'dist'
	--exclude '.dist-stage'
	--exclude '.wp-tests'
	--exclude '.wp-core'
	--exclude '.tmp'
)

while IFS= read -r line || [ -n "$line" ]; do
	line="${line%%#*}"
	line="$(echo "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
	[ -z "$line" ] && continue
	pat="${line#/}"
	RSYNC_EXCLUDES+=(--exclude "$pat")
done <"$ROOT/.distignore"

rsync -a "${RSYNC_EXCLUDES[@]}" "$ROOT/" "$PLUGIN_DIR/"

test -f "$PLUGIN_DIR/joshternet.php"
test -f "$PLUGIN_DIR/readme.txt"
test -f "$PLUGIN_DIR/uninstall.php"
test -f "$PLUGIN_DIR/LICENSE"
test -d "$PLUGIN_DIR/assets/buttons"
test -f "$PLUGIN_DIR/assets/buttons/join-the-joshternet.png"
test -f "$PLUGIN_DIR/screenshot-1.png"
test -f "$PLUGIN_DIR/screenshot-2.png"

for forbidden in \
	NOTICE.md \
	CHANGELOG.md \
	README.md \
	composer.json \
	composer.lock \
	vendor \
	tests \
	bin \
	.github \
	.git
do
	if [ -e "$PLUGIN_DIR/$forbidden" ]; then
		echo "error: install package must not include ${forbidden}" >&2
		exit 1
	fi
done

if ! grep -q '^=== Joshternet ===$' "$PLUGIN_DIR/readme.txt"; then
	echo "error: readme.txt missing WordPress.org title header" >&2
	exit 1
fi

mkdir -p "$ROOT/dist"
ZIP="$ROOT/dist/${SLUG}-${VERSION}.zip"
rm -f "$ZIP"
(
	cd "$STAGE"
	zip -r -q "$ZIP" "$SLUG" -x '*.DS_Store'
)

TOP_ENTRIES="$(unzip -Z1 "$ZIP" | awk -F/ 'NF { print $1 }' | sort -u)"
TOP_COUNT="$(printf '%s\n' "$TOP_ENTRIES" | grep -c . || true)"
if [ "$TOP_COUNT" -ne 1 ] || [ "$TOP_ENTRIES" != "$SLUG" ]; then
	echo "error: zip must contain only top-level directory '${SLUG}/'" >&2
	printf '%s\n' "$TOP_ENTRIES" >&2
	exit 1
fi

if unzip -l "$ZIP" | grep -Eiq "(^|/)(vendor/|tests/|bin/|composer\.(json|lock)|NOTICE\.md|CHANGELOG\.md|README\.md|\.git/)"; then
	echo "error: dist zip contains paths excluded for WordPress.org" >&2
	unzip -l "$ZIP" >&2
	exit 1
fi

python3 "$ROOT/bin/release-tools.py" extract "$ZIP" --version "$VERSION" --destination "$STAGE/validated"

echo "Built $ZIP"
ls -lh "$ZIP"
