#!/usr/bin/env bash
# Verify plugin version strings match an expected release version.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
EXPECTED="${1:-}"

if [ -z "$EXPECTED" ]; then
	echo "usage: $0 <version>" >&2
	echo "example: $0 0.1.1" >&2
	exit 1
fi

EXPECTED="${EXPECTED#v}"

if ! [[ "$EXPECTED" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.+-]+)?$ ]]; then
	echo "error: version must look like 0.1.0 (got: $EXPECTED)" >&2
	exit 1
fi

HEADER_VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$ROOT/joshternet.php" | head -1 | tr -d '[:space:]')"
CONST_VERSION="$(sed -n "s/^define( 'JOSHTERNET_VERSION', '\\([^']*\\)' );/\\1/p" "$ROOT/joshternet.php" | head -1)"
STABLE_TAG="$(sed -n 's/^Stable tag:[[:space:]]*//p' "$ROOT/readme.txt" | head -1 | tr -d '[:space:]')"

fail=0
if [ "$HEADER_VERSION" != "$EXPECTED" ]; then
	echo "error: joshternet.php Version header is '$HEADER_VERSION', expected '$EXPECTED'" >&2
	fail=1
fi
if [ "$CONST_VERSION" != "$EXPECTED" ]; then
	echo "error: JOSHTERNET_VERSION is '$CONST_VERSION', expected '$EXPECTED'" >&2
	fail=1
fi
if [ "$STABLE_TAG" != "$EXPECTED" ]; then
	echo "error: readme.txt Stable tag is '$STABLE_TAG', expected '$EXPECTED'" >&2
	fail=1
fi

if [ "$fail" -ne 0 ]; then
	exit 1
fi

echo "version_ok=$EXPECTED"
