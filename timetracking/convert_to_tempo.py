#!/usr/bin/env python3
"""
Convert time tracking CSV files to Tempo Timesheets format.

This script converts the generated time tracking CSV files to formats compatible with:
1. Tempo CSV Import (for manual upload)
2. Tempo REST API (for automated sync)

Usage:
    # Generate Tempo CSV for manual import
    python3 convert_to_tempo.py --input "timetracking/github_Ruben_van_der_Linde/Ruben van der Linde_normal_time.csv" --format csv
    
    # Generate Tempo API JSON for automated import
    python3 convert_to_tempo.py --input "timetracking/github_Ruben_van_der_Linde/Ruben van der Linde_normal_time.csv" --format api
    
    # Process both normal and overtime files
    python3 convert_to_tempo.py --user "Ruben van der Linde" --format both
"""

import csv
import json
import argparse
from pathlib import Path
from datetime import datetime, timedelta
from collections import defaultdict

def parse_date(date_str):
    """Parse date string to datetime object."""
    return datetime.strptime(date_str, '%Y-%m-%d')

def read_tracking_csv(csv_file):
    """
    Read time tracking CSV and return list of work entries.
    
    Returns:
        List of dicts with: date, hours, commits, repositories, summary
    """
    entries = []
    
    with open(csv_file, 'r', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            # Only include days where work was done.
            if row['Worked'] == 'Yes' and float(row['Hours']) > 0:
                entries.append({
                    'date': row['Date'],
                    'day': row['Day'],
                    'hours': float(row['Hours']),
                    'commits': int(row['Commits']),
                    'start_time': row['Start Time'],
                    'end_time': row['End Time'],
                    'repositories': row['Repositories'],
                    'summary': row['Work Summary'],
                    'is_weekend': row['Weekend'] == 'Yes'
                })
    
    return entries

def generate_tempo_csv(entries, output_file, jira_project_key='CONDUCTION', issue_key=None, activity_name='Development'):
    """
    Generate Tempo-compatible CSV file.
    
    Tempo CSV format:
    - Issue Key (required): JIRA issue key (e.g., PROJ-123) or generic
    - Start Date (required): YYYY-MM-DD
    - Hours (required): decimal hours
    - Work Description (optional): description of work
    - Worker (optional): username or email
    - Activity Name (optional): type of work
    
    Args:
        entries: List of work entries from tracking CSV
        output_file: Path to output CSV file
        jira_project_key: JIRA project key for generic issues
        issue_key: Specific issue key to use for all entries (if None, uses generic)
        activity_name: Type of activity (Development, Testing, Meeting, etc.)
    """
    
    with open(output_file, 'w', newline='', encoding='utf-8') as f:
        # Tempo CSV headers.
        fieldnames = ['Issue Key', 'Start Date', 'Hours', 'Work Description', 'Activity Name']
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        
        for entry in entries:
            # Generate work description from commits and repositories.
            description = f"{entry['commits']} commits"
            if entry['repositories']:
                description += f" in {entry['repositories']}"
            if entry['summary'] and entry['summary'] != 'No commits':
                # Trim summary to reasonable length.
                summary_text = entry['summary'][:200]
                description += f": {summary_text}"
            
            # Use specific issue key or generate generic one.
            if issue_key:
                issue = issue_key
            else:
                # Generic format: PROJECT-YYYY-MM-DD.
                issue = f"{jira_project_key}-{entry['date'].replace('-', '')}"
            
            writer.writerow({
                'Issue Key': issue,
                'Start Date': entry['date'],
                'Hours': entry['hours'],
                'Work Description': description,
                'Activity Name': activity_name
            })
    
    return len(entries)

def generate_tempo_api_json(entries, output_file, account_id, issue_key='CONDUCTION-1', activity_name='Development'):
    """
    Generate JSON file for Tempo REST API bulk import.
    
    Tempo REST API v4 format:
    https://tempo-io.github.io/tempo-api-docs/
    
    POST /rest/tempo-timesheets/4/worklogs
    {
        "issueKey": "PROJ-123",
        "timeSpentSeconds": 3600,
        "startDate": "2025-01-15",
        "startTime": "09:00:00",
        "description": "Working on feature",
        "authorAccountId": "account-id",
        "attributes": {
            "_Activity_": {"value": "Development"}
        }
    }
    
    Args:
        entries: List of work entries
        output_file: Path to output JSON file
        account_id: Jira/Tempo account ID of the user
        issue_key: JIRA issue key to log time against
        activity_name: Activity type
    """
    
    worklogs = []
    
    for entry in entries:
        # Convert hours to seconds.
        time_spent_seconds = int(entry['hours'] * 3600)
        
        # Generate description.
        description = f"{entry['commits']} commits"
        if entry['repositories']:
            description += f" in {entry['repositories']}"
        if entry['summary'] and entry['summary'] != 'No commits':
            summary_text = entry['summary'][:500]
            description += f"\n\n{summary_text}"
        
        # Use start time if available, otherwise default to 09:00.
        start_time = entry['start_time'] if entry['start_time'] else '09:00'
        start_time_formatted = f"{start_time}:00"
        
        worklog = {
            'issueKey': issue_key,
            'timeSpentSeconds': time_spent_seconds,
            'startDate': entry['date'],
            'startTime': start_time_formatted,
            'description': description,
            'authorAccountId': account_id,
            'attributes': {
                '_Activity_': {'value': activity_name}
            }
        }
        
        worklogs.append(worklog)
    
    # Write as JSON array.
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(worklogs, f, indent=2, ensure_ascii=False)
    
    return len(worklogs)

def generate_tempo_api_script(json_file, output_script, tempo_api_token, jira_base_url='https://your-domain.atlassian.net'):
    """
    Generate a bash script to upload worklogs via Tempo API.
    
    Args:
        json_file: Path to JSON file with worklogs
        output_script: Path to output bash script
        tempo_api_token: Tempo API token
        jira_base_url: Base URL of your Jira instance
    """
    
    script_content = f"""#!/bin/bash
#
# Tempo Worklogs Upload Script
# 
# This script uploads time entries to Tempo via the REST API.
# Generated by convert_to_tempo.py
#
# Prerequisites:
# - Tempo API token (set in this script or via TEMPO_API_TOKEN env var)
# - jq (for JSON processing): sudo apt-get install jq
# - curl (usually pre-installed)
#
# Usage:
#   chmod +x {Path(output_script).name}
#   ./{Path(output_script).name}
#

set -e  # Exit on error

# Configuration
TEMPO_API_TOKEN="${tempo_api_token}"
JIRA_BASE_URL="{jira_base_url}"
JSON_FILE="{json_file}"
TEMPO_API_URL="$JIRA_BASE_URL/rest/tempo-timesheets/4/worklogs"

# Check if jq is installed
if ! command -v jq &> /dev/null; then
    echo "Error: jq is not installed. Install it with: sudo apt-get install jq"
    exit 1
fi

# Check if JSON file exists
if [ ! -f "$JSON_FILE" ]; then
    echo "Error: JSON file not found: $JSON_FILE"
    exit 1
fi

# Check if API token is set
if [ -z "$TEMPO_API_TOKEN" ]; then
    echo "Error: TEMPO_API_TOKEN not set"
    echo "Set it in this script or export it: export TEMPO_API_TOKEN='your-token'"
    exit 1
fi

echo "=========================================="
echo "Tempo Worklogs Upload"
echo "=========================================="
echo ""
echo "JSON file: $JSON_FILE"
echo "Tempo API URL: $TEMPO_API_URL"
echo ""

# Count total worklogs
TOTAL=$(jq length "$JSON_FILE")
echo "Total worklogs to upload: $TOTAL"
echo ""

# Confirmation
read -p "Do you want to proceed with upload? (yes/no): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Upload cancelled."
    exit 0
fi

# Upload each worklog
SUCCESS=0
FAILED=0
COUNTER=1

while IFS= read -r worklog; do
    echo "[$COUNTER/$TOTAL] Uploading worklog..."
    
    # Extract date for logging
    DATE=$(echo "$worklog" | jq -r '.startDate')
    HOURS=$(echo "$worklog" | jq -r '.timeSpentSeconds / 3600')
    
    # Upload via API
    RESPONSE=$(curl -s -w "\\n%{{http_code}}" \\
        -X POST "$TEMPO_API_URL" \\
        -H "Authorization: Bearer $TEMPO_API_TOKEN" \\
        -H "Content-Type: application/json" \\
        -d "$worklog")
    
    # Parse response
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    if [ "$HTTP_CODE" -eq 200 ] || [ "$HTTP_CODE" -eq 201 ]; then
        echo "  ✓ Success: $DATE - ${{HOURS}}h"
        ((SUCCESS++))
    else
        echo "  ✗ Failed: $DATE - ${{HOURS}}h (HTTP $HTTP_CODE)"
        echo "    Response: $BODY"
        ((FAILED++))
    fi
    
    ((COUNTER++))
    
    # Rate limiting: small delay between requests
    sleep 0.5
done < <(jq -c '.[]' "$JSON_FILE")

echo ""
echo "=========================================="
echo "Upload Complete"
echo "=========================================="
echo "Success: $SUCCESS"
echo "Failed: $FAILED"
echo "Total: $TOTAL"
echo ""

if [ $FAILED -gt 0 ]; then
    echo "Some uploads failed. Check error messages above."
    exit 1
fi

echo "All worklogs uploaded successfully!"
"""
    
    with open(output_script, 'w', encoding='utf-8') as f:
        f.write(script_content)
    
    # Make script executable.
    import os
    os.chmod(output_script, 0o755)
    
    return output_script

def main():
    parser = argparse.ArgumentParser(
        description='Convert time tracking CSV to Tempo format',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Convert single CSV to Tempo CSV format
  python3 convert_to_tempo.py --input "normal_time.csv" --format csv
  
  # Convert to Tempo API JSON format
  python3 convert_to_tempo.py --input "normal_time.csv" --format api --account-id "abc123"
  
  # Process both normal and overtime files for a user
  python3 convert_to_tempo.py --user "Ruben van der Linde" --format both
  
  # Full workflow: CSV + API + upload script
  python3 convert_to_tempo.py --user "Ruben van der Linde" --format both --api-token "your-token"
        """
    )
    
    parser.add_argument('--input', type=str, help='Input CSV file path')
    parser.add_argument('--user', type=str, help='User name (processes both normal and overtime files)')
    parser.add_argument('--format', type=str, choices=['csv', 'api', 'both'], default='csv',
                        help='Output format: csv (Tempo CSV), api (Tempo API JSON), both')
    parser.add_argument('--output-dir', type=str, default=None,
                        help='Output directory (default: same as input file)')
    parser.add_argument('--jira-project', type=str, default='CONDUCTION',
                        help='JIRA project key (default: CONDUCTION)')
    parser.add_argument('--issue-key', type=str, default=None,
                        help='Specific JIRA issue key to log all time against')
    parser.add_argument('--account-id', type=str, default='YOUR_ACCOUNT_ID',
                        help='Jira/Tempo account ID for API format')
    parser.add_argument('--activity', type=str, default='Development',
                        help='Activity name (Development, Testing, Meeting, etc.)')
    parser.add_argument('--api-token', type=str, default='YOUR_TEMPO_API_TOKEN',
                        help='Tempo API token (for generating upload script)')
    parser.add_argument('--jira-url', type=str, default='https://your-domain.atlassian.net',
                        help='Jira base URL')
    parser.add_argument('--include-overtime', action='store_true',
                        help='Include overtime file when using --user')
    
    args = parser.parse_args()
    
    # Determine input files.
    input_files = []
    if args.input:
        input_files.append(Path(args.input))
    elif args.user:
        # Find user's tracking files.
        base_path = Path('/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/timetracking')
        safe_name = args.user.replace('@', '_').replace(' ', '_')
        
        # Try both regular and github tracking directories.
        possible_dirs = [
            base_path / safe_name,
            base_path / f'github_{safe_name}'
        ]
        
        for user_dir in possible_dirs:
            if user_dir.exists():
                normal_file = user_dir / f'{args.user}_normal_time.csv'
                if normal_file.exists():
                    input_files.append(normal_file)
                
                if args.include_overtime:
                    overtime_file = user_dir / f'{args.user}_overtime.csv'
                    if overtime_file.exists():
                        input_files.append(overtime_file)
                
                break
        
        if not input_files:
            print(f"Error: No tracking files found for user: {args.user}")
            print(f"Searched in: {possible_dirs}")
            return
    else:
        print("Error: Either --input or --user must be specified")
        parser.print_help()
        return
    
    # Process each input file.
    for input_file in input_files:
        print(f"\nProcessing: {input_file}")
        print("=" * 60)
        
        # Read entries.
        entries = read_tracking_csv(input_file)
        print(f"Found {len(entries)} work entries")
        
        if not entries:
            print("No work entries found in file")
            continue
        
        # Determine output directory.
        if args.output_dir:
            output_dir = Path(args.output_dir)
        else:
            output_dir = input_file.parent
        
        output_dir.mkdir(parents=True, exist_ok=True)
        
        # Determine output file base name.
        base_name = input_file.stem.replace('_time', '')
        
        # Generate Tempo CSV.
        if args.format in ['csv', 'both']:
            csv_output = output_dir / f'{base_name}_tempo.csv'
            count = generate_tempo_csv(
                entries, csv_output,
                jira_project_key=args.jira_project,
                issue_key=args.issue_key,
                activity_name=args.activity
            )
            print(f"✓ Generated Tempo CSV: {csv_output}")
            print(f"  - {count} entries")
        
        # Generate Tempo API JSON.
        if args.format in ['api', 'both']:
            json_output = output_dir / f'{base_name}_tempo_api.json'
            issue_key = args.issue_key if args.issue_key else f'{args.jira_project}-1'
            count = generate_tempo_api_json(
                entries, json_output,
                account_id=args.account_id,
                issue_key=issue_key,
                activity_name=args.activity
            )
            print(f"✓ Generated Tempo API JSON: {json_output}")
            print(f"  - {count} worklogs")
            
            # Generate upload script.
            script_output = output_dir / f'{base_name}_tempo_upload.sh'
            generate_tempo_api_script(
                json_output, script_output,
                tempo_api_token=args.api_token,
                jira_base_url=args.jira_url
            )
            print(f"✓ Generated upload script: {script_output}")
            print(f"  - Make sure to set your API token in the script!")
    
    print("\n" + "=" * 60)
    print("Conversion complete!")
    print("\nNext steps:")
    print("1. For CSV import: Upload the *_tempo.csv file to Tempo")
    print("   - Go to Tempo > Settings > Import")
    print("   - Select CSV file and map columns")
    print("2. For API import: Edit the *_tempo_upload.sh script")
    print("   - Set your TEMPO_API_TOKEN")
    print("   - Set your JIRA_BASE_URL")
    print("   - Run: ./*_tempo_upload.sh")

if __name__ == '__main__':
    main()








