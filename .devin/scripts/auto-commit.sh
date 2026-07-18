#!/usr/bin/env bash
#
# PostToolUse hook: auto-commit each file edit/write individually.
#
# Author (git):  Ghazi Triki <ghazi.triki@riadvice.com>  (from repo git config)
# Co-authored:   Devin <158243242+devin-ai-integration[bot]@users.noreply.github.com>
#
# Stdin (JSON):  { "tool_name": "edit|write", "tool_input": { "file_path": "..." }, "tool_response": { "success": true, ... } }
#
# Behaviour:
#   - Extracts the file_path from the tool input.
#   - Resolves the git repo root that contains the file.
#   - Stages only that file and commits it (one commit per change).
#   - Skips silently if the file is not inside a git repo, nothing changed,
#     or the tool call failed.

set -euo pipefail

# Read hook event data from stdin
INPUT="$(cat)"

# Parse the file path and tool success status with python3
PARSED="$(python3 -c '
import json, sys
try:
    data = json.load(sys.stdin)
except Exception:
    print("\n".join(["", ""]))
    sys.exit(0)
tool_input  = data.get("tool_input", {}) or {}
tool_resp   = data.get("tool_response", {}) or {}
file_path   = tool_input.get("file_path", "") or ""
success     = tool_resp.get("success", True)
print(file_path)
print("true" if success else "false")
' <<< "$INPUT")"

FILE_PATH="$(echo "$PARSED" | sed -n '1p')"
SUCCESS="$(echo "$PARSED" | sed -n '2p')"

# Skip if no file path or the tool call failed
[ -z "$FILE_PATH" ] && exit 0
[ "$SUCCESS" = "false" ] && exit 0

# Resolve to absolute path
[ -f "$FILE_PATH" ] || exit 0
ABS_PATH="$(cd "$(dirname "$FILE_PATH")" && pwd)/$(basename "$FILE_PATH")"

# Find the git repo root containing this file
GIT_ROOT="$(git -C "$(dirname "$ABS_PATH")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -z "$GIT_ROOT" ] && exit 0

# Relative path within the repo
REL_PATH="$(realpath --relative-to="$GIT_ROOT" "$ABS_PATH")"

# Stage only this file
cd "$GIT_ROOT"
git add -- "$REL_PATH" 2>/dev/null || exit 0

# Skip if nothing is staged for this file
git diff --cached --quiet -- "$REL_PATH" && exit 0

# Build a concise commit message from the filename
BASENAME="$(basename "$REL_PATH")"

git commit -m "$(cat <<EOF
Update ${BASENAME}

Generated with [Devin](https://devin.ai)

Co-Authored-By: Devin <158243242+devin-ai-integration[bot]@users.noreply.github.com>
EOF
)" --quiet 2>/dev/null || true

exit 0
