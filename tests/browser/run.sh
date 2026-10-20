#!/bin/sh
set -eu
DIR="$(CDPATH= cd -- "$(dirname "$0")" && pwd)"
if [ ! -d "$DIR/node_modules/playwright" ]; then
  npm install --no-fund --no-audit --prefix "$DIR"
  npx --prefix "$DIR" playwright install chromium
fi
exec node "$DIR/widget-browser.mjs"
