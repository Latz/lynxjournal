#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
CREDENTIALS="$PROJECT_DIR/ftp.login"
SOURCE="$PROJECT_DIR/dist/linkdigest"

FTP_HOST=$(grep '^host:'     "$CREDENTIALS" | awk '{print $2}' | tr -d '\r')
FTP_USER=$(grep '^user:'     "$CREDENTIALS" | awk '{print $2}' | tr -d '\r')
FTP_PASS=$(grep '^password:' "$CREDENTIALS" | awk '{print $2}' | tr -d '\r')
FTP_PATH=$(grep '^path:'     "$CREDENTIALS" | awk '{print $2}' | tr -d '\r')

if [ ! -d "$SOURCE" ]; then
    echo "ERROR: dist/linkdigest not found. Run bin/build-plugin.sh first." >&2
    exit 1
fi

FTP_IP=$(getent hosts "$FTP_HOST" | awk '{print $1; exit}')
FTP_ADDR="${FTP_IP:-$FTP_HOST}"

echo "Deploying to $FTP_HOST$FTP_PATH ..."

lftp -c "
  set ftp:ssl-allow no;
  open -u $FTP_USER,$FTP_PASS ftp://$FTP_ADDR;
  mirror --reverse --delete --verbose $SOURCE/ $FTP_PATH/;
  bye
"

echo "Deploy complete."
