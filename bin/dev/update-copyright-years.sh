#!/bin/bash
# SPDX-FileCopyrightText: 2010-2026 the Friendica project
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

NEW_YEAR=$(date +%Y)
EXCLUDE="-path /var/www/html/vendor -prune -o -path /var/www/html/node_modules -prune -o -path /var/www/html/.git -prune -o"

echo "▶️ Aktualisiere Friendica Copyright auf 2010-${NEW_YEAR}..."

find /var/www/html "$EXCLUDE" -type f -print | \
  xargs sed -i "/[Ff]riendica/s/2010[ ]*-[ ]*20[0-9][0-9]/2010-${NEW_YEAR}/g"

sed -i "s/2010-20[0-9][0-9]/2010-${NEW_YEAR}/g" /var/www/html/REUSE.toml

echo "▶️ Validiere mit reuse lint..."
reuse lint

echo "✅ Fertig."
