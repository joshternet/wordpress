#!/usr/bin/env bash
# Bump version strings in joshternet.php and readme.txt.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-}"

if [ -z "$VERSION" ]; then
	echo "usage: $0 <version>" >&2
	echo "example: $0 0.1.1" >&2
	exit 1
fi

VERSION="${VERSION#v}"

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.+-]+)?$ ]]; then
	echo "error: version must look like 0.1.0 (got: $VERSION)" >&2
	exit 1
fi

python3 - "$ROOT" "$VERSION" <<'PY'
import pathlib
import re
import sys

root = pathlib.Path(sys.argv[1])
version = sys.argv[2]

plugin = root / "joshternet.php"
text = plugin.read_text(encoding="utf-8")
text, n1 = re.subn(
    r"(^\s*\*\s*Version:\s*).*$",
    rf"\g<1>{version}",
    text,
    count=1,
    flags=re.M,
)
text, n2 = re.subn(
    r"^define\(\s*'JOSHTERNET_VERSION',\s*'[^']*'\s*\);$",
    f"define( 'JOSHTERNET_VERSION', '{version}' );",
    text,
    count=1,
    flags=re.M,
)
if n1 != 1 or n2 != 1:
    raise SystemExit(f"failed to update joshternet.php (header={n1}, const={n2})")
plugin.write_text(text, encoding="utf-8")

readme = root / "readme.txt"
rtext = readme.read_text(encoding="utf-8")
rtext, n3 = re.subn(
    r"^Stable tag:\s*.*$",
    f"Stable tag: {version}",
    rtext,
    count=1,
    flags=re.M,
)
if n3 != 1:
    raise SystemExit(f"failed to update readme.txt Stable tag (n={n3})")
readme.write_text(rtext, encoding="utf-8")
PY

"$ROOT/bin/verify-version.sh" "$VERSION"
echo "Updated plugin version to ${VERSION}."
echo "Next: update CHANGELOG.md, commit, wait for CI, then tag v${VERSION}."
