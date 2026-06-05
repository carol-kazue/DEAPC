#!/bin/bash
# Servidor de desenvolvimento local — Casa da Música
# Uso: ./start.sh  ou  bash start.sh

PORT=8000
DIR="$(cd "$(dirname "$0")" && pwd)"

echo ""
echo "  Casa da Música — Servidor Local"
echo "  ================================"
echo "  URL:  http://localhost:$PORT"
echo "  Dir:  $DIR"
echo "  PHP:  $(php --version | head -1)"
echo ""
echo "  Ctrl+C para parar"
echo ""

cd "$DIR"
php -S "localhost:$PORT"
