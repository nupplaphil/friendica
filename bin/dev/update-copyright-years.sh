#!/bin/bash
# SPDX-FileCopyrightText: 2010-2026 the Friendica project
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

NEW_YEAR=$(date +%Y)
PROJECT_ROOT=$(cd "$(dirname "$0")/../.." && pwd)

echo "▶ Updating Friendica copyright years to 2010-${NEW_YEAR}..."

find "$PROJECT_ROOT" \( \
  -path "$PROJECT_ROOT/vendor" -prune -o \
  -path "$PROJECT_ROOT/addon" -prune -o \
  -path "$PROJECT_ROOT/node_modules" -prune -o \
  -path "$PROJECT_ROOT/view/asset" -prune -o \
  -path "$PROJECT_ROOT/view/smarty3/compiled" -prune -o \
  -path "$PROJECT_ROOT/storage" -prune -o \
  -path "$PROJECT_ROOT/.git" -prune -o \
  -type f -print0 \) | \
  xargs -0 sed -i "/[Ff]riendica/s/2010[ ]*-[ ]*20[0-9][0-9]/2010-${NEW_YEAR}/g"

sed -i "/[Ff]riendica/s/2010-20[0-9][0-9]/2010-${NEW_YEAR}/g" "$PROJECT_ROOT/REUSE.toml"

echo "▶ Counting updated files..."

COUNT=$(find "$PROJECT_ROOT" \( \
  -path "$PROJECT_ROOT/vendor" -prune -o \
  -path "$PROJECT_ROOT/addon" -prune -o \
  -path "$PROJECT_ROOT/node_modules" -prune -o \
  -path "$PROJECT_ROOT/view/asset" -prune -o \
  -path "$PROJECT_ROOT/view/smarty3/compiled" -prune -o \
  -path "$PROJECT_ROOT/storage" -prune -o \
  -path "$PROJECT_ROOT/.git" -prune -o \
  -type f -print0 \) | \
  xargs -0 grep -l "2010-${NEW_YEAR}" 2>/dev/null | wc -l)

echo "  ${COUNT} files now contain copyright year 2010-${NEW_YEAR}"

if command -v reuse &> /dev/null; then
  echo "▶ Validating with reuse lint..."
  reuse lint
fi

echo "✅ Done."
