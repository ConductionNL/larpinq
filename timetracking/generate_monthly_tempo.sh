#!/bin/bash
#
# Generate Tempo CSV files per month with monthly issues
#
# This script generates separate Tempo CSV files for each month,
# allowing you to assign different Jira issues per month.
#

set -e

GITHUB_USER="Ruben van der Linde"
JIRA_PROJECT="COND"
YEAR=2025

# Issue mapping per month (edit these with your actual issue keys)
declare -A MONTHLY_ISSUES=(
    ["01"]="COND-1201"  # January
    ["02"]="COND-1202"  # February
    ["03"]="COND-1203"  # March
    ["04"]="COND-1204"  # April
    ["05"]="COND-1205"  # May
    ["06"]="COND-1206"  # June
    ["07"]="COND-1207"  # July
    ["08"]="COND-1208"  # August
    ["09"]="COND-1209"  # September
    ["10"]="COND-1210"  # October
    ["11"]="COND-1211"  # November
    ["12"]="COND-1212"  # December
)

echo "=========================================="
echo "Generating Monthly Tempo CSV Files"
echo "=========================================="
echo ""
echo "Year: $YEAR"
echo "User: $GITHUB_USER"
echo ""

# Create output directory
OUTPUT_DIR="timetracking/github_Ruben_van_der_Linde/monthly"
mkdir -p "$OUTPUT_DIR"

# Process each month
for MONTH in {01..12}; do
    # Calculate last day of month
    LAST_DAY=$(date -d "$YEAR-$MONTH-01 +1 month -1 day" +%d)
    START_DATE="$YEAR-$MONTH-01"
    END_DATE="$YEAR-$MONTH-$LAST_DAY"
    ISSUE_KEY="${MONTHLY_ISSUES[$MONTH]}"
    
    echo "Processing $YEAR-$MONTH..."
    echo "  Issue: $ISSUE_KEY"
    echo "  Period: $START_DATE to $END_DATE"
    
    # Generate tracking for this month
    python3 generate_github_user_tracking.py \
        --github-user "$GITHUB_USER" \
        --start-date "$START_DATE" \
        --end-date "$END_DATE" \
        --output-dir "timetracking/temp_monthly/$MONTH" \
        >/dev/null 2>&1 || true
    
    # Convert to Tempo CSV with monthly issue
    if [ -f "timetracking/temp_monthly/$MONTH/${GITHUB_USER}_normal_time.csv" ]; then
        python3 convert_to_tempo.py \
            --input "timetracking/temp_monthly/$MONTH/${GITHUB_USER}_normal_time.csv" \
            --format csv \
            --issue-key "$ISSUE_KEY" \
            --output-dir "$OUTPUT_DIR" \
            >/dev/null 2>&1
        
        # Rename to include month
        mv "$OUTPUT_DIR/${GITHUB_USER}_normal_tempo.csv" \
           "$OUTPUT_DIR/${YEAR}-${MONTH}_normal_tempo.csv" 2>/dev/null || true
    fi
    
    # Same for overtime
    if [ -f "timetracking/temp_monthly/$MONTH/${GITHUB_USER}_overtime.csv" ]; then
        python3 convert_to_tempo.py \
            --input "timetracking/temp_monthly/$MONTH/${GITHUB_USER}_overtime.csv" \
            --format csv \
            --issue-key "$ISSUE_KEY" \
            --output-dir "$OUTPUT_DIR" \
            >/dev/null 2>&1
        
        mv "$OUTPUT_DIR/${GITHUB_USER}_overtime_tempo.csv" \
           "$OUTPUT_DIR/${YEAR}-${MONTH}_overtime_tempo.csv" 2>/dev/null || true
    fi
    
    # Count entries
    NORMAL_COUNT=0
    OVERTIME_COUNT=0
    [ -f "$OUTPUT_DIR/${YEAR}-${MONTH}_normal_tempo.csv" ] && \
        NORMAL_COUNT=$(($(wc -l < "$OUTPUT_DIR/${YEAR}-${MONTH}_normal_tempo.csv") - 1))
    [ -f "$OUTPUT_DIR/${YEAR}-${MONTH}_overtime_tempo.csv" ] && \
        OVERTIME_COUNT=$(($(wc -l < "$OUTPUT_DIR/${YEAR}-${MONTH}_overtime_tempo.csv") - 1))
    
    echo "  ✓ Generated: $NORMAL_COUNT normal + $OVERTIME_COUNT overtime entries"
    echo ""
done

# Cleanup temp directory
rm -rf "timetracking/temp_monthly"

echo "=========================================="
echo "Monthly CSV Files Generated!"
echo "=========================================="
echo ""
echo "Location: $OUTPUT_DIR/"
echo ""
echo "Files created:"
ls -1 "$OUTPUT_DIR"/*.csv 2>/dev/null | while read file; do
    COUNT=$(($(wc -l < "$file") - 1))
    echo "  - $(basename "$file") ($COUNT entries)"
done
echo ""
echo "Next steps:"
echo "1. Create the Jira issues (one per month)"
echo "2. Update MONTHLY_ISSUES array in this script with actual issue keys"
echo "3. Re-run this script"
echo "4. Upload the monthly CSV files to Tempo"
echo ""








