#!/usr/bin/env bash
#
# Sync a release version into every file that carries it.
# Called by semantic-release (@semantic-release/exec) during the prepare step,
# before bin/build-zip.sh packages the ZIP, so the shipped files all match.
#
# Usage: bash bin/version.sh <version>
set -euo pipefail

VERSION="${1:?usage: bin/version.sh <version>}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# WordPress plugin header:  * Version: x.y.z
sed -i -E "s/^( \* Version:[[:space:]]*).*/\1${VERSION}/" "$ROOT/kwik-ai.php"

# readme.txt:  Stable tag: x.y.z
sed -i -E "s/^(Stable tag:[[:space:]]*).*/\1${VERSION}/" "$ROOT/readme.txt"

# package.json / package-lock.json (no commit or tag — semantic-release handles that)
( cd "$ROOT" && npm version "$VERSION" --no-git-tag-version --allow-same-version >/dev/null )

echo "Synced version to ${VERSION} (kwik-ai.php, readme.txt, package.json)"
