#!/bin/bash
# Runs PHPStan on edited PHP files to catch type errors immediately
# Runs after Pint formatting, catches issues early

input=$(cat)
file_path=$(echo "$input" | jq -r '.tool_input.file_path // empty')

if [[ -z "$file_path" ]]; then
  exit 0
fi

# Only check PHP files (skip tests — PHPStan config may exclude them)
if [[ "$file_path" == *.php && "$file_path" != */tests/* ]]; then
  vendor/bin/phpstan analyse "$file_path" --memory-limit=256M --no-progress 2>/dev/null || true
fi

exit 0
