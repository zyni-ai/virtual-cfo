#!/bin/bash
# Claude Code hook: Auto-format PHP files with Laravel Pint after edits
#
# This hook runs after Edit or Write tool operations on PHP files.
# It reads the tool input from stdin (JSON format) and runs Pint on the edited file.
#
# Prerequisites: jq must be installed (for JSON parsing)
# - Windows (Git Bash): pacman -S jq or download from https://jqlang.github.io/jq/
# - macOS: brew install jq
# - Linux: apt install jq

# Read tool input from stdin
input=$(cat)

# Windows paths have backslashes that break jq — escape them first
sanitized=$(echo "$input" | sed 's/\\/\\\\/g')
file_path=$(echo "$sanitized" | jq -r '.tool_input.file_path // empty')

# Exit if no file path provided
if [[ -z "$file_path" ]]; then
  exit 0
fi

# Only format PHP files
if [[ "$file_path" == *.php ]]; then
  # Run Pint silently, ignore errors (file might have syntax errors being fixed)
  vendor/bin/pint "$file_path" 2>/dev/null || true
fi
