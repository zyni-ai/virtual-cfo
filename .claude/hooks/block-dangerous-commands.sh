#!/bin/bash
# Blocks destructive bash commands
# Exit code 2 = block with message shown to Claude

input=$(cat)
# Windows paths have backslashes that break jq — escape them first
sanitized=$(echo "$input" | sed 's/\\/\\\\/g')
command=$(echo "$sanitized" | jq -r '.tool_input.command // empty')

if [[ -z "$command" ]]; then
  exit 0
fi

# Block rm -rf (covers -rf, -fr, --recursive, and flags in any order)
if echo "$command" | grep -qE 'rm\s+(-[a-zA-Z]*r[a-zA-Z]*f|-[a-zA-Z]*f[a-zA-Z]*r|--recursive)\s'; then
  echo "BLOCKED: Use targeted file deletion instead of rm -rf." >&2
  exit 2
fi

# Block force push to main/master (covers --force, --force-with-lease, -f)
if echo "$command" | grep -qE 'git\s+push\s+.*(--force|--force-with-lease|-f)\b.*\s+(main|master)'; then
  echo "BLOCKED: Force push to main/master is not allowed. Use a feature branch." >&2
  exit 2
fi
if echo "$command" | grep -qE 'git\s+push\s+.*\s+(main|master)\b.*(--force|--force-with-lease|-f)\b'; then
  echo "BLOCKED: Force push to main/master is not allowed. Use a feature branch." >&2
  exit 2
fi

# Block git reset --hard without confirmation
if echo "$command" | grep -qE 'git\s+reset\s+--hard'; then
  echo "BLOCKED: git reset --hard discards changes. Ask the user before proceeding." >&2
  exit 2
fi

exit 0
