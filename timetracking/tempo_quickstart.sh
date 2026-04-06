#!/bin/bash
#
# Quick Start Script: Git Commits → Tempo Timesheets
# 
# Dit script automatiseert het hele proces van tijd tracken tot Tempo import:
# 1. Scan git repositories
# 2. Genereer tijd tracking
# 3. Converteer naar Tempo formaat
# 4. (Optioneel) Upload naar Tempo API
#
# Usage:
#   ./tempo_quickstart.sh
#   ./tempo_quickstart.sh --auto-upload  # Met automatische API upload
#

set -e  # Exit on error

# Configuration
GITHUB_USER="Ruben van der Linde"
JIRA_PROJECT="COND"
ACTIVITY="Development"
CURRENT_YEAR=$(date +%Y)
START_DATE="${CURRENT_YEAR}-01-01"
END_DATE="${CURRENT_YEAR}-12-31"

# Kleuren voor output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}=========================================="
echo "Git Commits → Tempo Timesheets"
echo -e "==========================================${NC}"
echo ""
echo "GitHub User: $GITHUB_USER"
echo "Period: $START_DATE to $END_DATE"
echo "Jira Project: $JIRA_PROJECT"
echo ""

# Check if running from correct directory
if [ ! -f "generate_github_user_tracking.py" ]; then
    echo -e "${RED}Error: Run this script from apps-extra directory${NC}"
    echo "cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra"
    exit 1
fi

# Step 1: Generate time tracking
echo -e "${GREEN}[1/3] Scanning git repositories and generating time tracking...${NC}"
python3 generate_github_user_tracking.py \
    --github-user "$GITHUB_USER" \
    --start-date "$START_DATE" \
    --end-date "$END_DATE" \
    --search-paths /home/rubenlinde/nextcloud-docker-dev

if [ $? -ne 0 ]; then
    echo -e "${RED}Error: Time tracking generation failed${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}✓ Time tracking generated${NC}"
echo ""

# Step 2: Convert to Tempo format
echo -e "${GREEN}[2/3] Converting to Tempo format...${NC}"
python3 convert_to_tempo.py \
    --user "$GITHUB_USER" \
    --format both \
    --include-overtime \
    --jira-project "$JIRA_PROJECT" \
    --activity "$ACTIVITY"

if [ $? -ne 0 ]; then
    echo -e "${RED}Error: Tempo conversion failed${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}✓ Tempo files generated${NC}"
echo ""

# Find the generated files
TRACKING_DIR="timetracking/github_Ruben_van_der_Linde"
NORMAL_CSV="$TRACKING_DIR/Ruben van der Linde_normal_tempo.csv"
OVERTIME_CSV="$TRACKING_DIR/Ruben van der Linde_overtime_tempo.csv"

# Count entries
if [ -f "$NORMAL_CSV" ]; then
    NORMAL_COUNT=$(($(wc -l < "$NORMAL_CSV") - 1))  # Subtract header
    echo -e "${BLUE}Normal hours entries: $NORMAL_COUNT${NC}"
fi

if [ -f "$OVERTIME_CSV" ]; then
    OVERTIME_COUNT=$(($(wc -l < "$OVERTIME_CSV") - 1))  # Subtract header
    echo -e "${BLUE}Overtime entries: $OVERTIME_COUNT${NC}"
fi

TOTAL=$((NORMAL_COUNT + OVERTIME_COUNT))
echo -e "${BLUE}Total entries: $TOTAL${NC}"
echo ""

# Step 3: Show results and next steps
echo -e "${GREEN}[3/3] Summary${NC}"
echo -e "${BLUE}==========================================${NC}"
echo ""
echo "✓ Generated files in: $TRACKING_DIR/"
echo ""
echo "CSV Files (for manual import):"
echo "  - Ruben van der Linde_normal_tempo.csv"
echo "  - Ruben van der Linde_overtime_tempo.csv"
echo ""
echo "API Files (for automated import):"
echo "  - Ruben van der Linde_normal_tempo_api.json"
echo "  - Ruben van der Linde_overtime_tempo_api.json"
echo ""
echo "Upload Scripts:"
echo "  - Ruben van der Linde_normal_tempo_upload.sh"
echo "  - Ruben van der Linde_overtime_tempo_upload.sh"
echo ""

# Check if auto-upload was requested
if [ "$1" == "--auto-upload" ]; then
    echo -e "${YELLOW}Attempting automatic upload...${NC}"
    echo ""
    
    # Check if API token is set
    if [ -z "$TEMPO_API_TOKEN" ]; then
        echo -e "${RED}Error: TEMPO_API_TOKEN not set${NC}"
        echo "Export your Tempo API token first:"
        echo "  export TEMPO_API_TOKEN='your-token-here'"
        exit 1
    fi
    
    # Run upload scripts
    cd "$TRACKING_DIR"
    
    echo "Uploading normal hours..."
    bash "Ruben van der Linde_normal_tempo_upload.sh"
    
    echo ""
    echo "Uploading overtime..."
    bash "Ruben van der Linde_overtime_tempo_upload.sh"
    
    echo ""
    echo -e "${GREEN}✓ Upload complete!${NC}"
else
    echo -e "${BLUE}==========================================${NC}"
    echo -e "${YELLOW}Next Steps:${NC}"
    echo ""
    echo "Option 1: Manual CSV Import"
    echo "  1. Open Tempo in Jira"
    echo "  2. Go to Settings → Import"
    echo "  3. Upload the *_tempo.csv files"
    echo "  4. Map columns and import"
    echo ""
    echo "Option 2: Automated API Import"
    echo "  1. Get your Tempo API token"
    echo "  2. Edit the upload scripts:"
    echo "     cd $TRACKING_DIR"
    echo '     nano "Ruben van der Linde_normal_tempo_upload.sh"'
    echo "  3. Set TEMPO_API_TOKEN and JIRA_BASE_URL"
    echo "  4. Run the upload scripts"
    echo ""
    echo "Or run with --auto-upload for automatic import:"
    echo "  export TEMPO_API_TOKEN='your-token'"
    echo "  ./tempo_quickstart.sh --auto-upload"
    echo ""
fi

echo -e "${BLUE}==========================================${NC}"
echo ""
echo -e "${GREEN}Done! 🎉${NC}"
echo ""
echo "For detailed instructions, see:"
echo "  - TEMPO_IMPORT_HANDLEIDING.md"
echo "  - GITHUB_USER_TRACKING_README.md"
echo ""








