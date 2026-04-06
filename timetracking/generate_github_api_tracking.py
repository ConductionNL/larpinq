#!/usr/bin/env python3
"""
Generate time tracking for a GitHub user using GitHub API.
This fetches ALL commits from ALL repositories, not just local ones.

Usage:
    python3 generate_github_api_tracking.py --github-user rubenvdlinde --start-date 2025-01-01 --end-date 2025-12-31
    
    # With GitHub token for higher rate limits
    export GITHUB_TOKEN="your-token"
    python3 generate_github_api_tracking.py --github-user rubenvdlinde --start-date 2025-01-01 --end-date 2025-12-31
"""

import requests
import csv
import argparse
from pathlib import Path
from datetime import datetime, timedelta
from collections import defaultdict
import time
import os
import sys

def get_github_headers():
    """Get headers for GitHub API requests."""
    headers = {'Accept': 'application/vnd.github.v3+json'}
    token = os.environ.get('GITHUB_TOKEN')
    if token:
        headers['Authorization'] = f'token {token}'
        print("✓ Using GitHub token (higher rate limits)")
    else:
        print("⚠ No GITHUB_TOKEN set (60 requests/hour limit)")
        print("  Get token at: https://github.com/settings/tokens")
    return headers

def get_user_repos(username, headers):
    """Get all repositories for a user."""
    repos = []
    page = 1
    
    while True:
        url = f"https://api.github.com/users/{username}/repos"
        params = {'per_page': 100, 'page': page, 'type': 'all'}
        
        response = requests.get(url, headers=headers, params=params)
        if response.status_code != 200:
            print(f"Error fetching repos: {response.status_code}")
            break
        
        batch = response.json()
        if not batch:
            break
        
        repos.extend(batch)
        page += 1
    
    return repos

def get_commits_for_repo(owner, repo_name, username, start_date, end_date, headers):
    """Get all commits by a user in a repository within date range."""
    commits = []
    page = 1
    
    print(f"  Fetching commits from {repo_name}...", end='', flush=True)
    
    while True:
        url = f"https://api.github.com/repos/{owner}/{repo_name}/commits"
        params = {
            'author': username,
            'since': f"{start_date}T00:00:00Z",
            'until': f"{end_date}T23:59:59Z",
            'per_page': 100,
            'page': page
        }
        
        try:
            response = requests.get(url, headers=headers, params=params, timeout=30)
            
            # Handle rate limiting
            if response.status_code == 403 and 'rate limit' in response.text.lower():
                reset_time = int(response.headers.get('X-RateLimit-Reset', 0))
                wait_time = max(reset_time - time.time(), 0) + 10
                print(f"\n  Rate limit hit! Waiting {int(wait_time)}s...", end='', flush=True)
                time.sleep(wait_time)
                continue
            
            if response.status_code == 409:  # Empty repository
                print(" (empty repo)", flush=True)
                break
            
            if response.status_code != 200:
                print(f" (error {response.status_code})", flush=True)
                break
            
            batch = response.json()
            if not batch:
                break
            
            commits.extend(batch)
            page += 1
            
        except Exception as e:
            print(f" (error: {e})", flush=True)
            break
    
    print(f" {len(commits)} commits", flush=True)
    return commits

def parse_commit_date(commit):
    """Extract commit date from GitHub API response."""
    try:
        date_str = commit['commit']['author']['date']
        return datetime.strptime(date_str, '%Y-%m-%dT%H:%M:%SZ')
    except Exception as e:
        return None

def calculate_work_sessions(commits, timezone='Europe/Amsterdam'):
    """Calculate work sessions from commits."""
    try:
        from zoneinfo import ZoneInfo
    except ImportError:
        from datetime import timezone as tz
        # Fallback for Python < 3.9
        class ZoneInfo:
            def __init__(self, name):
                if name == 'UTC':
                    self.tz = tz.utc
                else:
                    # Assume CET/CEST (UTC+1/+2)
                    self.tz = tz(timedelta(hours=1))
    
    # Sort commits by date
    commits_with_dates = []
    for commit in commits:
        date = parse_commit_date(commit)
        if date:
            # Convert to local timezone (or keep UTC if fallback)
            try:
                local_date = date.replace(tzinfo=ZoneInfo('UTC')).astimezone(ZoneInfo(timezone))
            except:
                # Simple fallback: assume UTC+1
                local_date = date + timedelta(hours=1)
            commits_with_dates.append({
                'date': local_date,
                'repo': commit.get('repo_name', 'unknown'),
                'message': commit['commit']['message'].split('\n')[0][:100]
            })
    
    commits_with_dates.sort(key=lambda x: x['date'])
    
    # Group by day and calculate hours
    daily_work = defaultdict(lambda: {'commits': [], 'repos': set()})
    
    for commit in commits_with_dates:
        day = commit['date'].date()
        daily_work[day]['commits'].append(commit)
        daily_work[day]['repos'].add(commit['repo'])
    
    # Calculate hours for each day
    results = []
    for day, data in sorted(daily_work.items()):
        commits_list = data['commits']
        first_commit = commits_list[0]['date']
        last_commit = commits_list[-1]['date']
        
        # Calculate hours
        time_span = (last_commit - first_commit).total_seconds() / 3600
        commit_count = len(commits_list)
        
        # Estimate hours
        base_hours = 2.0  # Minimum session
        span_hours = max(time_span, 0)
        commit_bonus = (commit_count / 10) * 0.5  # +0.5h per 10 commits
        
        total_hours = max(base_hours, span_hours) + commit_bonus
        
        # Determine if overtime
        is_weekend = day.weekday() >= 5
        hour = first_commit.hour
        is_overtime = is_weekend or hour < 8 or hour >= 18
        
        # Collect commit messages
        commit_messages = [c['message'] for c in commits_list[:20]]  # Max 20 messages per day
        commit_messages_str = ' | '.join(commit_messages)
        if len(commit_messages_str) > 500:
            commit_messages_str = commit_messages_str[:497] + '...'
        
        results.append({
            'date': day,
            'hours': round(total_hours, 1),
            'commits': commit_count,
            'repos': ', '.join(sorted(data['repos'])),
            'is_overtime': is_overtime,
            'first_commit': first_commit.strftime('%H:%M'),
            'last_commit': last_commit.strftime('%H:%M'),
            'commit_messages': commit_messages_str
        })
    
    return results

def main():
    parser = argparse.ArgumentParser(description='Generate time tracking from GitHub API.')
    parser.add_argument('--github-user', required=True, help='GitHub username')
    parser.add_argument('--start-date', required=True, help='Start date (YYYY-MM-DD)')
    parser.add_argument('--end-date', required=True, help='End date (YYYY-MM-DD)')
    args = parser.parse_args()
    
    print(f"{'='*60}")
    print(f"GITHUB API TIME TRACKING")
    print(f"{'='*60}")
    print(f"User: {args.github_user}")
    print(f"Period: {args.start_date} to {args.end_date}")
    print()
    
    # Get GitHub headers
    headers = get_github_headers()
    print()
    
    # Get all repositories
    print("Fetching repositories...")
    repos = get_user_repos(args.github_user, headers)
    print(f"✓ Found {len(repos)} repositories\n")
    
    # Filter repos active in the date range
    start = datetime.strptime(args.start_date, '%Y-%m-%d')
    end = datetime.strptime(args.end_date, '%Y-%m-%d')
    
    active_repos = []
    for repo in repos:
        updated = datetime.strptime(repo['updated_at'], '%Y-%m-%dT%H:%M:%SZ')
        if start <= updated <= end + timedelta(days=365):  # Include repos updated within range
            active_repos.append(repo)
    
    print(f"Checking {len(active_repos)} potentially active repositories...")
    print()
    
    # Fetch commits from all repos
    all_commits = []
    repos_with_commits = set()
    
    for i, repo in enumerate(active_repos, 1):
        print(f"[{i}/{len(active_repos)}] {repo['name']}", end=' ')
        
        commits = get_commits_for_repo(
            repo['owner']['login'],
            repo['name'],
            args.github_user,
            args.start_date,
            args.end_date,
            headers
        )
        
        if commits:
            repos_with_commits.add(repo['name'])
            for commit in commits:
                commit['repo_name'] = repo['name']
            all_commits.extend(commits)
        
        # Be nice to GitHub API
        time.sleep(0.5)
    
    print()
    print(f"{'='*60}")
    print(f"✓ Found {len(all_commits)} commits across {len(repos_with_commits)} repositories")
    print()
    
    if not all_commits:
        print("❌ No commits found!")
        sys.exit(1)
    
    # Calculate work sessions
    print("Calculating work sessions...")
    sessions = calculate_work_sessions(all_commits)
    
    # Split into normal and overtime
    normal_sessions = [s for s in sessions if not s['is_overtime']]
    overtime_sessions = [s for s in sessions if s['is_overtime']]
    
    # Save to CSV
    output_dir = Path('timetracking') / f'github_api_{args.github_user}'
    output_dir.mkdir(parents=True, exist_ok=True)
    
    # Normal time CSV
    normal_csv = output_dir / f'{args.github_user}_normal_time.csv'
    with open(normal_csv, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=['date', 'hours', 'commits', 'repos', 'first_commit', 'last_commit', 'commit_messages'])
        writer.writeheader()
        for session in normal_sessions:
            row = {k: v for k, v in session.items() if k != 'is_overtime'}
            writer.writerow(row)
    
    # Overtime CSV
    overtime_csv = output_dir / f'{args.github_user}_overtime.csv'
    with open(overtime_csv, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=['date', 'hours', 'commits', 'repos', 'first_commit', 'last_commit', 'commit_messages'])
        writer.writeheader()
        for session in overtime_sessions:
            row = {k: v for k, v in session.items() if k != 'is_overtime'}
            writer.writerow(row)
    
    # Summary
    normal_hours = sum(s['hours'] for s in normal_sessions)
    overtime_hours = sum(s['hours'] for s in overtime_sessions)
    
    summary_file = output_dir / f'{args.github_user}_summary.txt'
    with open(summary_file, 'w') as f:
        f.write(f"GITHUB API TIME TRACKING SUMMARY\n")
        f.write(f"{'='*60}\n\n")
        f.write(f"User: {args.github_user}\n")
        f.write(f"Period: {args.start_date} to {args.end_date}\n")
        f.write(f"Total commits: {len(all_commits)}\n")
        f.write(f"Repositories with commits: {len(repos_with_commits)}\n\n")
        f.write(f"NORMAL TIME:\n")
        f.write(f"  Days: {len(normal_sessions)}\n")
        f.write(f"  Hours: {normal_hours:.1f}\n\n")
        f.write(f"OVERTIME:\n")
        f.write(f"  Days: {len(overtime_sessions)}\n")
        f.write(f"  Hours: {overtime_hours:.1f}\n\n")
        f.write(f"TOTAL: {normal_hours + overtime_hours:.1f} hours\n\n")
        f.write(f"Top repositories:\n")
        
        # Count commits per repo
        repo_commits = defaultdict(int)
        for commit in all_commits:
            repo_commits[commit['repo_name']] += 1
        
        for repo, count in sorted(repo_commits.items(), key=lambda x: x[1], reverse=True)[:10]:
            f.write(f"  {repo}: {count} commits\n")
    
    print(f"\n{'='*60}")
    print("✓ Generated files:")
    print(f"  - {normal_csv}")
    print(f"  - {overtime_csv}")
    print(f"  - {summary_file}")
    print(f"\n✓ Total: {normal_hours + overtime_hours:.1f} hours")
    print(f"  Normal: {normal_hours:.1f} hours ({len(normal_sessions)} days)")
    print(f"  Overtime: {overtime_hours:.1f} hours ({len(overtime_sessions)} days)")

if __name__ == '__main__':
    main()

