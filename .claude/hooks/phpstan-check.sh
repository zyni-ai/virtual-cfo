#!/bin/bash
# Runs PHPStan on edited PHP files to catch type errors immediately
# Runs after Pint formatting, catches issues early

input=$(cat)
# Windows paths have backslashes that break jq — escape them first
sanitized=$(echo "$input" | sed 's/\\/\\\\/g')
file_path=$(echo "$sanitized" | jq -r '.tool_input.file_path // empty')

if [[ -z "$file_path" ]]; then
  exit 0
fi

# Only check PHP files (skip tests — PHPStan config may exclude them)
if [[ "$file_path" == *.php && "$file_path" != */tests/* ]]; then
  output=$(vendor/bin/phpstan analyse "$file_path" --memory-limit=256M --no-progress --error-format=raw 2>&1)
  status=$?
  if [[ $status -ne 0 && -n "$output" ]]; then
    echo "PHPStan errors in $(basename "$file_path"):"
    echo "$output"
  fi
fi

exit 0
