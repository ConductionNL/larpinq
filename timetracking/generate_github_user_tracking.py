#!/usr/bin/env python3
"""
Time tracking tool for a specific GitHub user across ALL repositories on the system.
This script scans the entire filesystem for git repositories and tracks commits from a specific GitHub user.
"""

import csv
import subprocess
import argparse
import os
from datetime import datetime, timedelta
from collections import defaultdict
from pathlib import Path

def parse_commit_line(line, repo_name):
    """
    Parse a commit line into hash, date, message, and repository.
    
    Git timestamps are in the author's local timezone (Amsterdam/CET for ConductionNL).
    The timezone offset (+0200 for CEST, +0100 for CET) is preserved in the datetime object.
    """
    parts = line.strip().split('|', 2)
    if len(parts) < 3:
        return None
    commit_hash, date_str, message = parts
    try:
        # Parse ISO date: 2024-10-01 23:11:45 +0200
        # Timezone is preserved: +0200 = CEST (summer), +0100 = CET (winter)
        date = datetime.strptime(date_str, '%Y-%m-%d %H:%M:%S %z')
        return {
            'hash': commit_hash,
            'date': date,
            'message': message,
            'repository': repo_name
        }
    except ValueError:
        return None

def is_normal_workday_hours(commit_date):
    """
    Check if commit is during normal workday hours (08:00-18:00).
    Timezone-aware: commits are already in their local timezone (Amsterdam/CET).
    """
    hour = commit_date.hour
    return 8 <= hour < 18

def is_weekend(date):
    """Check if date is weekend (Saturday=5, Sunday=6)."""
    return date.weekday() >= 5

def is_overtime(commit_date, is_weekend_day):
    """
    Determine if commit is overtime.
    Overtime includes:
    - Weekend commits (Saturday/Sunday)
    - Commits before 08:00 or after 18:00 on weekdays
    """
    if is_weekend_day:
        return True
    # Overtime if outside normal workday hours (08:00-18:00)
    return not is_normal_workday_hours(commit_date)

def get_session_date(commit_date):
    """Get the date for the work session."""
    if commit_date.hour < 2:
        return (commit_date - timedelta(days=1)).date()
    return commit_date.date()

def calculate_hours(commits, is_overtime_session):
    """
    Estimate hours worked based on commit timestamps and frequency.
    
    Calculation method:
    - Hours are NOT based on lines of code changed
    - Hours are estimated from commit timestamps and patterns:
      1. Base hours: Minimum hours for a work session (2h normal, 3h overtime)
      2. Time span: If commits span multiple hours, add the actual time difference
      3. Commit frequency: More commits indicate more intensive work (+0.5h per 10 commits)
    """
    if not commits:
        return 0.0
    
    commits = sorted(commits, key=lambda x: x['date'])
    first_commit = commits[0]['date']
    last_commit = commits[-1]['date']
    
    time_diff = last_commit - first_commit
    
    if is_overtime_session:
        base_hours = 3.0
        max_hours = 6.0
    else:
        base_hours = 2.0
        max_hours = 8.0
    
    # If commits span more than base hours, add the actual time difference.
    if time_diff.total_seconds() > base_hours * 3600:
        extra_hours = (time_diff.total_seconds() - base_hours * 3600) / 3600
        base_hours = min(base_hours + extra_hours, max_hours)
    
    # If there are many commits, might indicate longer or more intensive work session.
    if len(commits) > 10:
        base_hours = min(base_hours + 0.5, max_hours)
    if len(commits) > 20:
        base_hours = min(base_hours + 0.5, max_hours)
    
    return round(base_hours, 1)

def find_all_git_repos(search_paths, exclude_patterns=None):
    """
    Find all git repositories under the given search paths.
    
    Args:
        search_paths: List of directories to search
        exclude_patterns: List of directory patterns to exclude (e.g., 'node_modules', '.cache')
    
    Returns:
        List of paths to git repositories
    """
    if exclude_patterns is None:
        exclude_patterns = [
            'node_modules', '.npm', '.cache', '.local', '.venv', 'venv',
            '__pycache__', '.git/modules', '.git/worktrees', 'build', 'dist', 'target'
        ]
    
    git_repos = []
    
    for search_path in search_paths:
        search_path = Path(search_path).resolve()
        
        if not search_path.exists():
            print(f"Warning: Search path does not exist: {search_path}")
            continue
        
        print(f"Scanning for git repositories in: {search_path}")
        
        # Walk through directory tree.
        for root, dirs, files in os.walk(search_path, followlinks=False):
            # Skip excluded directories.
            original_dirs = dirs[:]
            dirs[:] = [d for d in dirs if not any(pattern in d for pattern in exclude_patterns)]
            
            # Check if this directory is a git repository.
            if '.git' in original_dirs:
                git_path = Path(root)
                
                # Verify it's actually a valid git repository.
                try:
                    result = subprocess.run(
                        ['git', 'rev-parse', '--is-inside-work-tree'],
                        cwd=git_path,
                        capture_output=True,
                        text=True,
                        check=True,
                        timeout=5
                    )
                    if result.returncode == 0 and result.stdout.strip() == 'true':
                        git_repos.append(str(git_path))
                        print(f"  Found: {git_path}")
                        # Don't search inside this git repo (but .git itself was already removed).
                        dirs[:] = [d for d in dirs if d != '.git']
                except (subprocess.CalledProcessError, subprocess.TimeoutExpired):
                    continue
    
    return sorted(set(git_repos))

def get_commits_for_github_user(repo_paths, github_username, start_date, end_date):
    """
    Get all commits for a GitHub username across all repositories.
    
    Args:
        repo_paths: List of repository paths to scan
        github_username: GitHub username (e.g., 'rubenvdlinde')
        start_date: Start date for commit range
        end_date: End date for commit range
    
    Returns:
        List of commit dictionaries
    """
    all_commits = []
    repos_with_commits = []
    
    for repo_path in repo_paths:
        repo_name = os.path.basename(repo_path.rstrip('/'))
        
        if not os.path.exists(repo_path):
            continue
        
        # Build git command - search for commits by author name or email containing the GitHub username.
        git_cmd = [
            'git', 'log', '--all',
            f'--since={start_date}',
            f'--until={end_date}',
            '--pretty=format:%H|%ai|%s',
            '--date=iso',
            '--author', github_username
        ]
        
        try:
            result = subprocess.run(
                git_cmd,
                cwd=repo_path,
                capture_output=True,
                text=True,
                check=True,
                timeout=30
            )
            
            if result.stdout.strip():
                commit_count = 0
                for line in result.stdout.strip().split('\n'):
                    if not line.strip():
                        continue
                    commit = parse_commit_line(line, repo_name)
                    if commit:
                        all_commits.append(commit)
                        commit_count += 1
                
                if commit_count > 0:
                    repos_with_commits.append((repo_name, commit_count))
        except (subprocess.CalledProcessError, subprocess.TimeoutExpired) as e:
            continue
    
    return all_commits, repos_with_commits

def generate_tracking_files(all_commits, start_date, end_date, output_dir, username, repos_with_commits):
    """Generate normal time and overtime tracking files."""
    # Separate commits into normal time and overtime.
    normal_commits_by_date = defaultdict(list)
    overtime_commits_by_date = defaultdict(list)
    
    for commit in all_commits:
        commit_date = commit['date']
        session_date = get_session_date(commit_date)
        is_weekend_day = is_weekend(session_date)
        is_overtime_commit = is_overtime(commit_date, is_weekend_day)
        
        if is_overtime_commit:
            overtime_commits_by_date[session_date].append(commit)
        else:
            normal_commits_by_date[session_date].append(commit)
    
    # Generate normal time tracking.
    normal_rows = generate_rows(normal_commits_by_date, start_date, end_date, False)
    normal_file = output_dir / f'{username}_normal_time.csv'
    write_csv(normal_rows, normal_file, 'Normal Time')
    
    # Generate overtime tracking.
    overtime_rows = generate_rows(overtime_commits_by_date, start_date, end_date, True)
    overtime_file = output_dir / f'{username}_overtime.csv'
    write_csv(overtime_rows, overtime_file, 'Overtime')
    
    # Generate summaries.
    normal_summary = generate_summary(normal_rows, start_date, end_date, 'Normal Time', is_overtime=False)
    overtime_summary = generate_summary(overtime_rows, start_date, end_date, 'Overtime', is_overtime=True)
    
    summary_file = output_dir / f'{username}_summary.txt'
    with open(summary_file, 'w', encoding='utf-8') as f:
        f.write("TIME TRACKING SUMMARY - GITHUB USER\n")
        f.write("=" * 50 + "\n\n")
        f.write(f"GitHub User: {username}\n")
        f.write(f"Period: {start_date} to {end_date}\n")
        f.write(f"Total days tracked: {(end_date - start_date).days + 1}\n")
        f.write(f"Total commits: {len(all_commits)}\n")
        f.write(f"Repositories with commits: {len(repos_with_commits)}\n\n")
        
        f.write("TOP REPOSITORIES BY COMMIT COUNT:\n")
        f.write("-" * 50 + "\n")
        sorted_repos = sorted(repos_with_commits, key=lambda x: x[1], reverse=True)
        for repo_name, commit_count in sorted_repos[:20]:
            f.write(f"  {repo_name}: {commit_count} commits\n")
        if len(sorted_repos) > 20:
            f.write(f"  ... and {len(sorted_repos) - 20} more repositories\n")
        f.write("\n")
        
        f.write("HOW HOURS ARE CALCULATED:\n")
        f.write("-" * 50 + "\n")
        f.write("Hours are estimated from git commit timestamps, NOT from lines of code.\n\n")
        f.write("Timezone: Amsterdam/CET (UTC+1/+2)\n")
        f.write("Normal workday: 08:00-18:00 on weekdays\n")
        f.write("Overtime: Before 08:00, after 18:00, or weekends\n\n")
        f.write("Method:\n")
        f.write("1. Base hours: Minimum session duration (2h normal, 3h overtime)\n")
        f.write("2. Time span: Actual time between first and last commit\n")
        f.write("3. Commit frequency: +0.5h per 10 commits (indicates intensive work)\n\n")
        f.write("Limitations:\n")
        f.write("- Does not account for time without commits (meetings, planning, etc.)\n")
        f.write("- Does not account for code review, documentation, or testing\n")
        f.write("- May underestimate for developers who commit infrequently\n")
        f.write("- May overestimate for developers who make many small commits\n\n")
        f.write("=" * 50 + "\n\n")
        f.write(normal_summary)
        f.write("\n" + "=" * 50 + "\n\n")
        f.write(overtime_summary)
    
    return normal_file, overtime_file, summary_file

def generate_rows(commits_by_date, start_date, end_date, is_overtime_session):
    """Generate rows for CSV file."""
    rows = []
    current_date = start_date
    
    while current_date <= end_date:
        is_weekend_day = is_weekend(current_date)
        
        if current_date in commits_by_date:
            commits = commits_by_date[current_date]
            hours = calculate_hours(commits, is_overtime_session)
            commit_count = len(commits)
            first_commit_time = min(c['date'] for c in commits).strftime('%H:%M')
            last_commit_time = max(c['date'] for c in commits).strftime('%H:%M')
            
            # Get unique repositories.
            repositories = sorted(set(c['repository'] for c in commits))
            repo_list = ', '.join(repositories)
            
            commit_messages = '; '.join([c['message'][:50].replace('\n', ' ') for c in commits[:3]])
            if len(commits) > 3:
                commit_messages += f' ... (+{len(commits)-3} more commits)'
            
            worked = 'Yes'
        else:
            hours = 0.0
            commit_count = 0
            commit_messages = 'No commits'
            worked = 'No'
            first_commit_time = ''
            last_commit_time = ''
            repo_list = ''
        
        rows.append({
            'Date': current_date.strftime('%Y-%m-%d'),
            'Day': current_date.strftime('%A'),
            'Weekend': 'Yes' if is_weekend_day else 'No',
            'Worked': worked,
            'Hours': hours,
            'Commits': commit_count,
            'Start Time': first_commit_time,
            'End Time': last_commit_time,
            'Repositories': repo_list,
            'Work Summary': commit_messages
        })
        
        current_date += timedelta(days=1)
    
    return rows

def write_csv(rows, file_path, title):
    """Write rows to CSV file."""
    fieldnames = ['Date', 'Day', 'Weekend', 'Worked', 'Hours', 'Commits', 'Start Time', 'End Time', 'Repositories', 'Work Summary']
    with open(file_path, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(rows)

def calculate_expected_working_hours(start_date, end_date, hours_per_week=40, weeks_holiday=5):
    """
    Calculate expected working hours for a period.
    
    Assumptions:
    - Standard work week: 40 hours (default, can be adjusted)
    - Holiday weeks per year: 5 weeks (default)
    - Working weeks per year: 52 - 5 = 47 weeks
    - Hours per working week: 40 hours
    """
    total_days = (end_date - start_date).days + 1
    total_weeks = total_days / 7.0
    working_weeks_per_year = 52 - weeks_holiday
    working_weeks = total_weeks * (working_weeks_per_year / 52.0)
    expected_hours = working_weeks * hours_per_week
    return expected_hours

def generate_summary(rows, start_date, end_date, title, is_overtime=False):
    """Generate summary statistics."""
    total_hours = sum(float(r['Hours']) for r in rows)
    days_worked = sum(1 for r in rows if r['Worked'] == 'Yes')
    weekday_hours = sum(float(r['Hours']) for r in rows if r['Weekend'] == 'No')
    weekend_hours = sum(float(r['Hours']) for r in rows if r['Weekend'] == 'Yes')
    weekday_days = sum(1 for r in rows if r['Worked'] == 'Yes' and r['Weekend'] == 'No')
    weekend_days = sum(1 for r in rows if r['Worked'] == 'Yes' and r['Weekend'] == 'Yes')
    
    summary = f"{title.upper()} BREAKDOWN:\n"
    summary += f"  Total hours: {total_hours:.1f}\n"
    summary += f"  Weekday hours: {weekday_hours:.1f}\n"
    summary += f"  Weekend hours: {weekend_hours:.1f}\n\n"
    summary += f"DAYS WORKED:\n"
    summary += f"  Total days: {days_worked}\n"
    summary += f"  Weekday days: {weekday_days}\n"
    summary += f"  Weekend days: {weekend_days}\n\n"
    
    days_in_period = (end_date - start_date).days + 1
    if days_worked > 0:
        summary += f"AVERAGES:\n"
        summary += f"  Average hours per day worked: {total_hours/days_worked:.1f}\n"
    if days_in_period > 0:
        summary += f"  Average hours per week: {total_hours / (days_in_period / 7):.1f}\n"
    
    # Add expected vs tracked comparison for normal time only.
    if not is_overtime:
        expected_hours = calculate_expected_working_hours(start_date, end_date)
        tracked_hours = total_hours
        untracked_hours = max(0, expected_hours - tracked_hours)
        coverage_percentage = (tracked_hours / expected_hours * 100) if expected_hours > 0 else 0
        
        summary += f"\nEXPECTED VS TRACKED:\n"
        summary += f"  Expected working hours (40h/week, 5 weeks holiday): {expected_hours:.1f}\n"
        summary += f"  Tracked hours (from commits): {tracked_hours:.1f}\n"
        summary += f"  Hours not accounted for: {untracked_hours:.1f}\n"
        summary += f"  Coverage: {coverage_percentage:.1f}%\n"
        summary += f"\n  Note: Hours are estimated from commit timestamps, not lines of code.\n"
        summary += f"        Untracked hours may include: meetings, code review, planning,\n"
        summary += f"        documentation, testing without commits, or other non-coding work.\n"
    
    return summary

def main():
    parser = argparse.ArgumentParser(
        description='Generate time tracking for a specific GitHub user across ALL repositories on the system'
    )
    parser.add_argument('--github-user', type=str, required=True,
                        help='GitHub username (e.g., rubenvdlinde)')
    parser.add_argument('--start-date', type=str, default=None,
                        help='Start date (YYYY-MM-DD), defaults to Jan 1 of current year')
    parser.add_argument('--end-date', type=str, default=None,
                        help='End date (YYYY-MM-DD), defaults to Dec 31 of current year')
    parser.add_argument('--search-paths', type=str, nargs='+', default=None,
                        help='Paths to search for git repositories (defaults to common locations)')
    parser.add_argument('--output-dir', type=str, default=None,
                        help='Output directory. Defaults to timetracking/{github_user}/')
    
    args = parser.parse_args()
    
    # Set default dates.
    current_year = datetime.now().year
    if args.start_date:
        start_date = datetime.strptime(args.start_date, '%Y-%m-%d').date()
    else:
        start_date = datetime(current_year, 1, 1).date()
    
    if args.end_date:
        end_date = datetime.strptime(args.end_date, '%Y-%m-%d').date()
    else:
        end_date = datetime(current_year, 12, 31).date()
    
    # Set default search paths.
    if args.search_paths:
        search_paths = [Path(p).resolve() for p in args.search_paths]
    else:
        # Default: search common development directories.
        search_paths = [
            Path('/home/rubenlinde/nextcloud-docker-dev'),
            Path('/home/rubenlinde/workspace'),
            Path('/home/rubenlinde/projects'),
            Path('/home/rubenlinde/git'),
            Path('/home/rubenlinde/repos'),
            Path('/home/rubenlinde'),
        ]
        # Filter to existing paths.
        search_paths = [p for p in search_paths if p.exists()]
    
    print(f"Searching for git repositories in {len(search_paths)} locations...")
    print(f"This may take a few minutes...\n")
    
    # Find all git repositories.
    repo_paths = find_all_git_repos(search_paths)
    
    if not repo_paths:
        print("Error: No git repositories found in search paths")
        return
    
    print(f"\nFound {len(repo_paths)} git repositories")
    
    # Set output directory.
    base_path = Path('/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra')
    if args.output_dir:
        output_dir = Path(args.output_dir)
    else:
        safe_name = args.github_user.replace('@', '_').replace(' ', '_')
        output_dir = base_path / 'timetracking' / f'github_{safe_name}'
    
    output_dir.mkdir(parents=True, exist_ok=True)
    
    # Get commits for this GitHub user.
    print(f"\nFetching commits for GitHub user: {args.github_user}")
    print(f"Period: {start_date} to {end_date}")
    print(f"This may take a while...\n")
    
    all_commits, repos_with_commits = get_commits_for_github_user(
        repo_paths, args.github_user, start_date, end_date
    )
    
    print(f"\nFound {len(all_commits)} total commits")
    print(f"Commits found in {len(repos_with_commits)} repositories")
    
    if not all_commits:
        print(f"\nNo commits found for GitHub user: {args.github_user}")
        print(f"Make sure the username is correct and matches your git commit author name/email")
        return
    
    # Generate tracking files.
    normal_file, overtime_file, summary_file = generate_tracking_files(
        all_commits, start_date, end_date, output_dir, args.github_user, repos_with_commits
    )
    
    print(f"\nGenerated files:")
    print(f"  - Normal time: {normal_file}")
    print(f"  - Overtime: {overtime_file}")
    print(f"  - Summary: {summary_file}")

if __name__ == '__main__':
    main()

