#!/usr/bin/env bash
#
# Build a clean, WordPress.org-ready plugin ZIP.
#
# Produces dist/kwik-ai.zip containing a single top-level "kwik-ai/"
# folder with only the files that should ship (everything in .distignore is
# excluded). Requires: rsync, zip.
#
# Usage: bash bin/build-zip.sh
set -euo pipefail

SLUG="kwik-ai"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STAGE="$(mktemp -d)"
OUT_DIR="$ROOT/dist"
OUT_ZIP="$OUT_DIR/$SLUG.zip"

# Rebuild the bundled assets (the Gutenberg description block) so the ZIP always
# ships the freshest compiled JS, not a stale checkout artifact.
echo "Building assets (npm run build)..."
( cd "$ROOT" && npm run build )

# Build rsync --exclude args from .distignore (skip comments/blank lines).
EXCLUDES=()
while IFS= read -r line; do
  [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
  EXCLUDES+=("--exclude=$line")
done < "$ROOT/.distignore"

rsync -a "${EXCLUDES[@]}" "$ROOT/" "$STAGE/$SLUG/"

mkdir -p "$OUT_DIR"
rm -f "$OUT_ZIP"
( cd "$STAGE" && zip -rq "$OUT_ZIP" "$SLUG" )
rm -rf "$STAGE"

echo "Built: $OUT_ZIP"
unzip -l "$OUT_ZIP" | tail -n +4 | head -n -2 | awk '{print "  " $4}'
