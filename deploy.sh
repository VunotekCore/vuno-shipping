#!/bin/bash
set -euo pipefail

echo "=== 1. Build Astro ==="
pnpm build

echo "=== 2. Build Backoffice ==="
(cd backoffice && npm run build)

echo "=== 3. Preparar directorio temporal ==="
DEPLOY_DIR=$(mktemp -d)

cp -r dist/* "$DEPLOY_DIR/"

cp -r api "$DEPLOY_DIR/api/"
rm -f "$DEPLOY_DIR/api/.env"

mkdir -p "$DEPLOY_DIR/backoffice"
cp -r backoffice/dist/* "$DEPLOY_DIR/backoffice/"
cp backoffice/.htaccess "$DEPLOY_DIR/backoffice/"

echo "=== 4. Empaquetar ==="
tar -czf shipping-deploy.tar.gz -C "$DEPLOY_DIR" .
rm -rf "$DEPLOY_DIR"

echo "=== Listo: shipping-deploy.tar.gz ($(du -sh shipping-deploy.tar.gz | cut -f1)) ==="
