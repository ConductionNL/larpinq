#!/bin/bash
#
# Load Tempo Import Configuration
# 
# Usage:
#   source load_tempo_config.sh
#   (or)
#   . load_tempo_config.sh
#

CONFIG_FILE="tempo_config.env"

if [ ! -f "$CONFIG_FILE" ]; then
    echo "❌ Error: $CONFIG_FILE not found!"
    echo ""
    echo "Please create $CONFIG_FILE with your API tokens."
    echo "See tempo_config.env.example for template."
    return 1
fi

echo "Loading Tempo configuration from $CONFIG_FILE..."

# Load environment variables
set -a
source "$CONFIG_FILE"
set +a

echo ""
echo "✓ Configuration loaded!"
echo ""
echo "Loaded variables:"
echo "  - JIRA_API_TOKEN: ${JIRA_API_TOKEN:0:20}... (${#JIRA_API_TOKEN} chars)"
echo "  - JIRA_URL: $JIRA_URL"
echo "  - JIRA_EMAIL: $JIRA_EMAIL"
echo "  - TEMPO_API_TOKEN: ${TEMPO_API_TOKEN:0:20}... (${#TEMPO_API_TOKEN} chars)"
echo "  - TEMPO_EMAIL: $TEMPO_EMAIL"
echo "  - GITHUB_USER: $GITHUB_USER"
echo ""

# Validate required variables
MISSING=0

if [ -z "$JIRA_API_TOKEN" ] || [ "$JIRA_API_TOKEN" = "your-jira-token-here" ]; then
    echo "⚠️  JIRA_API_TOKEN not set properly"
    MISSING=1
fi

if [ -z "$TEMPO_API_TOKEN" ] || [ "$TEMPO_API_TOKEN" = "your-tempo-token-here" ]; then
    echo "⚠️  TEMPO_API_TOKEN not set properly"
    MISSING=1
fi

if [ -z "$JIRA_URL" ] || [ "$JIRA_URL" = "https://your-company.atlassian.net" ]; then
    echo "⚠️  JIRA_URL not set properly"
    MISSING=1
fi

if [ $MISSING -eq 1 ]; then
    echo ""
    echo "Please update $CONFIG_FILE with your actual values."
    return 1
fi

echo "✅ All required variables are set!"
echo ""
echo "You can now run:"
echo "  python3 auto_tempo_import.py --user \"\$GITHUB_USER\" --tempo-email \"\$TEMPO_EMAIL\" --dry-run"
echo ""







