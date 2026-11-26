#!/bin/bash
# Films everything from a fresh demo database. The app must be serving on FILM_BASE (default http://127.0.0.1:8030).
set -e
cd "$(dirname "$0")/.."
bash scripts/reset.sh
rm -rf public/raw
node scripts/shoot-apply.mjs
node scripts/shoot-rest.mjs
node scripts/index.mjs
