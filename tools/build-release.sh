#!/usr/bin/env bash
# Construit l'archive de release : dist/cantine-vX.Y.Z.zip
# Contenu : le dossier "les-reco-de-la-cantine/" sans data/, tests/, tools/, fichiers git, projet.md ni fichiers de développement.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(tr -d '[:space:]' < "$ROOT/VERSION")"
NAME="les-reco-de-la-cantine"
DIST="$ROOT/dist"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

echo "Vérification de la syntaxe PHP…"
find "$ROOT" -name '*.php' -not -path "$ROOT/data/*" -not -path "$ROOT/dist/*" -not -path "$ROOT/vendor/*" -print0 \
    | xargs -0 -n1 php -l > /dev/null

echo "Lancement des tests (SQLite)…"
php "$ROOT/tests/run.php" > /dev/null || { echo "Des tests échouent : release annulée."; exit 1; }

mkdir -p "$WORK/$NAME" "$DIST"
rsync -a "$ROOT/" "$WORK/$NAME/" \
    --exclude '/data/' --exclude '/dist/' --exclude '/tests/' --exclude '/tools/' --exclude '/vendor/' \
    --exclude '/projet.md' --exclude '/assets/palettes.html' --exclude '/docs/' \
    --exclude '.git' --exclude '.gitignore' --exclude '.github' --exclude '.DS_Store' --exclude '.idea' --exclude '.vscode'

ZIP="$DIST/cantine-v$VERSION.zip"
rm -f "$ZIP"
(cd "$WORK" && zip -qr -X "$ZIP" "$NAME")

# Garde-fou : aucune donnée ni secret dans l'archive.
if unzip -l "$ZIP" | grep -E "data/|config\.php|\.sqlite|projet\.md" > /dev/null; then
    echo "ERREUR : l'archive contient des fichiers interdits."; exit 1
fi

echo "Archive créée : $ZIP ($(du -h "$ZIP" | cut -f1))"
