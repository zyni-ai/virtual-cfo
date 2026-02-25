#!/bin/bash
# Blocks Edit/Write to sensitive files
# Exit code 2 = block with message shown to Claude

input=$(cat)
# Windows paths have backslashes that break jq — escape them first
sanitized=$(echo "$input" | sed 's/\\/\\\\/g')
file_path=$(echo "$sanitized" | jq -r '.tool_input.file_path // empty')

if [[ -z "$file_path" ]]; then
  exit 0
fi

# Protect .env files
if [[ "$file_path" == *.env* ]]; then
  echo "BLOCKED: .env files must be edited manually, not by Claude." >&2
  exit 2
fi

# Protect composer.lock
if [[ "$(basename "$file_path")" == "composer.lock" ]]; then
  echo "BLOCKED: composer.lock is auto-generated. Run 'composer update' instead." >&2
  exit 2
fi

exit 0
