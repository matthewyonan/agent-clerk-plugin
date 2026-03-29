#!/bin/bash
# Build a WordPress-installable ZIP from the agentclerk/ plugin directory.
# Output: agentclerk.zip in the repo root.

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_DIR="$REPO_ROOT/agentclerk"
OUTPUT="$REPO_ROOT/agentclerk.zip"

if [ ! -d "$PLUGIN_DIR" ]; then
    echo "Error: agentclerk/ directory not found at $PLUGIN_DIR"
    exit 1
fi

# Remove old zip if present.
rm -f "$OUTPUT"

# Create zip from repo root so paths inside are agentclerk/...
cd "$REPO_ROOT"
zip -r "$OUTPUT" agentclerk/ \
    -x "agentclerk/.DS_Store" \
    -x "agentclerk/**/.DS_Store" \
    -x "agentclerk/**/__MACOSX/*"

echo ""
echo "Built: $OUTPUT"
echo "Size: $(du -h "$OUTPUT" | cut -f1)"
echo ""
echo "Upload this file to WordPress → Plugins → Add New → Upload Plugin"
