#!/bin/bash

PATCH_FILE="/home/sergeniyrus/www/daodes/ai_last_patch.txt"

while true
do
  if [ -f "$PATCH_FILE" ]; then
    echo "Patch detected, applying..."

    php /home/sergeniyrus/www/daodes/ai/engine/run_apply.php "$PATCH_FILE"

    rm -f "$PATCH_FILE"
  fi

  sleep 2
done