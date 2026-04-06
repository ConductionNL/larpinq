#!/usr/bin/env python3
"""
Intelligent Tempo CSV generator with automatic issue detection from commit messages.

This script analyzes commit messages and automatically extracts Jira issue keys,
mapping time entries to the actual issues you worked on.

Usage:
    python3 smart_tempo_converter.py --user "Ruben van der Linde" --fallback-issue "COND-9999"
"""

import csv
import re
import argparse
from pathlib import Path
from collections import defaultdict

def extract_issue_keys(commit_message, jira_projects=['COND', 'CONNECTOR', 'CATALOGI', 'REGISTER']):
    """
    Extract Jira issue keys from commit message.
    
    Patterns matched:
    - PROJ-123
    - feature/PROJ-123
    - #123 (if project known from context)
    """
    # Pattern: PROJECT-NUMBER
    pattern = r'\b(' + '|'.join(jira_projects) + r')-\d+'
    matches = re.findall(pattern, commit_message, re.IGNORECASE)
    
    if matches:
        return matches[0].upper()
    
    # Pattern: feature/PROJ-123 or hotfix/PROJ-123
    pattern = r'(?:feature|hotfix|bugfix)/(' + '|'.join(jira_projects) + r')-(\d+)'
    match = re.search(pattern, commit_message, re.IGNORECASE)
    if match:
        return f"{match.group(1).upper()}-{match.group(2)}"
    
    return None

def read_tracking_csv(csv_file):
    """Read time tracking CSV."""
    entries = []
    
    with open(csv_file, 'r', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
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

def map_entries_to_issues(entries, fallback_issue='COND-9999', jira_projects=None):
    """
    Map time entries to Jira issues by analyzing commit messages.
    
    Returns:
        Dict mapping issue keys to lists of entries
    """
    if jira_projects is None:
        jira_projects = ['COND', 'CONNECTOR', 'CATALOGI', 'REGISTER', 'OREG', 'OCAT', 'OCON']
    
    issue_mapping = defaultdict(list)
    unmapped_count = 0
    
    for entry in entries:
        # Try to extract issue key from work summary
        issue_key = extract_issue_keys(entry['summary'], jira_projects)
        
        if not issue_key:
            # Try repository name as hint
            if 'openconnector' in entry['repositories'].lower():
                issue_key = 'CONNECTOR-1'
            elif 'opencatalogi' in entry['repositories'].lower():
                issue_key = 'CATALOGI-1'
            elif 'openregister' in entry['repositories'].lower():
                issue_key = 'REGISTER-1'
            else:
                # Use fallback
                issue_key = fallback_issue
                unmapped_count += 1
        
        issue_mapping[issue_key].append(entry)
    
    return dict(issue_mapping), unmapped_count

def generate_tempo_csv_by_issue(issue_mapping, output_file, activity_name='Development'):
    """Generate Tempo CSV grouped by issue."""
    
    with open(output_file, 'w', newline='', encoding='utf-8') as f:
        fieldnames = ['Issue Key', 'Start Date', 'Hours', 'Work Description', 'Activity Name']
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        
        total_entries = 0
        
        # Sort by issue key for organized output
        for issue_key in sorted(issue_mapping.keys()):
            entries = issue_mapping[issue_key]
            
            for entry in entries:
                # Generate work description
                description = f"{entry['commits']} commits"
                if entry['repositories']:
                    description += f" in {entry['repositories']}"
                if entry['summary'] and entry['summary'] != 'No commits':
                    summary_text = entry['summary'][:200]
                    description += f": {summary_text}"
                
                writer.writerow({
                    'Issue Key': issue_key,
                    'Start Date': entry['date'],
                    'Hours': entry['hours'],
                    'Work Description': description,
                    'Activity Name': activity_name
                })
                
                total_entries += 1
        
        return total_entries

def generate_issue_summary(issue_mapping, output_file):
    """Generate summary report of hours per issue."""
    
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write("TEMPO IMPORT - ISSUE SUMMARY\n")
        f.write("=" * 60 + "\n\n")
        
        # Calculate totals per issue
        issue_totals = []
        total_hours = 0
        total_entries = 0
        
        for issue_key, entries in issue_mapping.items():
            hours = sum(e['hours'] for e in entries)
            issue_totals.append((issue_key, len(entries), hours))
            total_hours += hours
            total_entries += len(entries)
        
        # Sort by hours descending
        issue_totals.sort(key=lambda x: x[2], reverse=True)
        
        f.write(f"Total Issues: {len(issue_totals)}\n")
        f.write(f"Total Entries: {total_entries}\n")
        f.write(f"Total Hours: {total_hours:.1f}\n\n")
        
        f.write("BREAKDOWN BY ISSUE:\n")
        f.write("-" * 60 + "\n")
        f.write(f"{'Issue Key':<20} {'Entries':>10} {'Hours':>12}\n")
        f.write("-" * 60 + "\n")
        
        for issue_key, entry_count, hours in issue_totals:
            f.write(f"{issue_key:<20} {entry_count:>10} {hours:>12.1f}\n")
        
        f.write("-" * 60 + "\n")
        f.write(f"{'TOTAL':<20} {total_entries:>10} {total_hours:>12.1f}\n")
        f.write("=" * 60 + "\n\n")
        
        f.write("NOTES:\n")
        f.write("- Issues extracted from commit messages where possible\n")
        f.write("- Repository-based fallbacks used when no issue found\n")
        f.write("- Generic fallback issue used for unmapped commits\n")
        f.write("\n")
        f.write("NEXT STEPS:\n")
        f.write("1. Review the issue mapping\n")
        f.write("2. Create missing Jira issues if needed\n")
        f.write("3. Update CSV manually for any incorrect mappings\n")
        f.write("4. Import CSV to Tempo\n")

def main():
    parser = argparse.ArgumentParser(
        description='Smart Tempo converter with automatic issue detection'
    )
    
    parser.add_argument('--user', type=str, default='Ruben van der Linde',
                        help='User name')
    parser.add_argument('--fallback-issue', type=str, default='COND-9999',
                        help='Fallback issue for unmapped commits')
    parser.add_argument('--jira-projects', type=str, nargs='+',
                        default=['COND', 'CONNECTOR', 'CATALOGI', 'REGISTER', 'OREG', 'OCAT', 'OCON'],
                        help='Jira project keys to look for')
    parser.add_argument('--activity', type=str, default='Development',
                        help='Activity name')
    parser.add_argument('--include-overtime', action='store_true',
                        help='Include overtime file')
    
    args = parser.parse_args()
    
    # Find tracking files
    base_path = Path('/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/timetracking')
    safe_name = args.user.replace('@', '_').replace(' ', '_')
    
    possible_dirs = [
        base_path / safe_name,
        base_path / f'github_{safe_name}'
    ]
    
    user_dir = None
    for d in possible_dirs:
        if d.exists():
            user_dir = d
            break
    
    if not user_dir:
        print(f"Error: No tracking files found for user: {args.user}")
        return
    
    print("=" * 60)
    print("SMART TEMPO CONVERTER")
    print("=" * 60)
    print(f"\nUser: {args.user}")
    print(f"Fallback Issue: {args.fallback_issue}")
    print(f"Jira Projects: {', '.join(args.jira_projects)}")
    print()
    
    # Process normal time
    normal_file = user_dir / f'{args.user}_normal_time.csv'
    if normal_file.exists():
        print("Processing normal hours...")
        entries = read_tracking_csv(normal_file)
        issue_mapping, unmapped = map_entries_to_issues(
            entries, args.fallback_issue, args.jira_projects
        )
        
        output_csv = user_dir / f'{args.user}_smart_tempo.csv'
        count = generate_tempo_csv_by_issue(issue_mapping, output_csv, args.activity)
        
        summary_file = user_dir / f'{args.user}_smart_tempo_summary.txt'
        generate_issue_summary(issue_mapping, summary_file)
        
        print(f"✓ Generated: {output_csv}")
        print(f"  - {count} entries")
        print(f"  - {len(issue_mapping)} unique issues")
        print(f"  - {unmapped} unmapped (using fallback)")
        print(f"✓ Summary: {summary_file}")
        print()
    
    # Process overtime if requested
    if args.include_overtime:
        overtime_file = user_dir / f'{args.user}_overtime.csv'
        if overtime_file.exists():
            print("Processing overtime hours...")
            entries = read_tracking_csv(overtime_file)
            issue_mapping, unmapped = map_entries_to_issues(
                entries, args.fallback_issue, args.jira_projects
            )
            
            output_csv = user_dir / f'{args.user}_smart_tempo_overtime.csv'
            count = generate_tempo_csv_by_issue(issue_mapping, output_csv, args.activity)
            
            summary_file = user_dir / f'{args.user}_smart_tempo_overtime_summary.txt'
            generate_issue_summary(issue_mapping, summary_file)
            
            print(f"✓ Generated: {output_csv}")
            print(f"  - {count} entries")
            print(f"  - {len(issue_mapping)} unique issues")
            print(f"  - {unmapped} unmapped (using fallback)")
            print(f"✓ Summary: {summary_file}")
            print()
    
    print("=" * 60)
    print("DONE!")
    print("=" * 60)
    print("\nNext steps:")
    print("1. Review the *_summary.txt files to see issue distribution")
    print("2. Check if all issues exist in Jira")
    print("3. Create missing issues or update CSV manually")
    print("4. Import the *_smart_tempo.csv files to Tempo")
    print()

if __name__ == '__main__':
    main()








