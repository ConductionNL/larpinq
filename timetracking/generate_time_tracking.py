#!/usr/bin/env python3
"""
Time tracking tool for developers based on git commit history.
Generates normal time and overtime tracking files from git commits across multiple repositories.
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


# ── Session-based tracking ──────────────────────────────────────────────────

def build_session(commits):
    """
    Construct a session dict from a list of commits.

    A session represents a continuous block of work with no large gaps
    between commits.
    """
    sorted_commits = sorted(commits, key=lambda x: x['date'])
    start = sorted_commits[0]['date']
    end = sorted_commits[-1]['date']
    duration = (end - start).total_seconds() / 3600
    repos = sorted(set(c['repository'] for c in sorted_commits))
    return {
        'start': start,
        'end': end,
        'duration_hours': duration,
        'commit_count': len(sorted_commits),
        'repositories': repos,
        'commits': sorted_commits,
    }


def detect_sessions(commits, gap_threshold_hours=3.0):
    """
    Group commits into work sessions based on temporal gaps.

    A new session starts when the gap between consecutive commits
    exceeds gap_threshold_hours. This replaces date-based grouping
    and correctly handles all-nighters as single sessions.
    """
    if not commits:
        return []

    sorted_commits = sorted(commits, key=lambda x: x['date'])
    sessions = []
    current_session_commits = [sorted_commits[0]]

    for i in range(1, len(sorted_commits)):
        gap = (sorted_commits[i]['date'] - sorted_commits[i - 1]['date']).total_seconds() / 3600
        if gap > gap_threshold_hours:
            sessions.append(build_session(current_session_commits))
            current_session_commits = [sorted_commits[i]]
        else:
            current_session_commits.append(sorted_commits[i])

    if current_session_commits:
        sessions.append(build_session(current_session_commits))

    return sessions


def classify_session(session):
    """
    Classify a session by time-of-day pattern.

    Returns one of:
    - 'all_nighter': commits span from evening (>=21:00) through early morning (<06:00)
    - 'weekend': majority of commits fall on Saturday/Sunday
    - 'evening': session extends past 18:00 on a weekday
    - 'early_morning': session starts before 07:00 on a weekday
    - 'normal': fits within 08:00-18:00 on a weekday
    """
    commits = session['commits']
    has_late_night = any(0 <= c['date'].hour < 6 for c in commits)
    has_evening = any(c['date'].hour >= 21 for c in commits)
    spans_midnight = session['start'].date() != session['end'].date()

    weekend_commits = sum(1 for c in commits if c['date'].weekday() >= 5)
    is_mostly_weekend = weekend_commits > len(commits) / 2

    if is_mostly_weekend:
        return 'weekend'
    if (spans_midnight and has_late_night) or (has_evening and has_late_night):
        return 'all_nighter'
    if has_late_night and session['start'].hour < 7:
        return 'early_morning'
    if has_evening or session['start'].hour >= 18:
        return 'evening'
    return 'normal'


def analyze_sleep_patterns(sessions):
    """
    Examine gaps between consecutive sessions to detect sleep patterns.

    For each overnight gap, classifies it as:
    - 'all_nighter': no gap (the session itself spans the night)
    - 'short_sleep': gap of 1-4 hours between sessions
    - 'moderate_rest': gap of 4-6 hours
    - 'normal_rest': gap > 6 hours

    Also includes sessions already classified as all_nighter (they have
    zero gap by definition — it's one continuous session).
    """
    patterns = []

    # First: record all-nighter sessions themselves
    for i, session in enumerate(sessions):
        if session.get('type') == 'all_nighter':
            patterns.append({
                'kind': 'all_nighter_session',
                'session_idx': i,
                'session': session,
                'night_of': session['start'].date(),
                'gap_hours': 0.0,
            })

    # Second: analyze inter-session gaps for short-sleep nights
    for i in range(len(sessions) - 1):
        current = sessions[i]
        next_session = sessions[i + 1]

        gap_hours = (next_session['start'] - current['end']).total_seconds() / 3600

        current_end_hour = current['end'].hour
        next_start_hour = next_session['start'].hour

        # Only consider overnight gaps: session ends late or next starts early
        is_overnight = (
            (current_end_hour >= 20 or current_end_hour < 4)
            and (next_start_hour < 10)
        )

        if not is_overnight:
            continue

        if gap_hours <= 4.0:
            pattern_type = 'short_sleep'
        elif gap_hours <= 6.0:
            pattern_type = 'moderate_rest'
        else:
            pattern_type = 'normal_rest'

        patterns.append({
            'kind': pattern_type,
            'session_before_idx': i,
            'session_after_idx': i + 1,
            'gap_hours': gap_hours,
            'night_of': current['end'].date(),
        })

    return patterns


def calculate_session_hours(session):
    """
    Calculate hours for a session based on actual commit span.

    Unlike the old date-based method:
    - Uses actual duration (no 2AM cutoff splitting)
    - No artificial 6h cap for overtime
    - Single buffer instead of doubled base hours from normal/overtime split

    Method:
    - Duration = last commit - first commit
    - Add 1h buffer (pre-first-commit setup + post-last-commit wrap-up)
    - Minimum 1.5h (even single-commit sessions)
    - Commit frequency bonus: +0.5h if >5 commits, +1.0h if >20
    - No maximum cap
    """
    hours = session['duration_hours'] + 1.0
    hours = max(hours, 1.5)

    if session['commit_count'] > 20:
        hours += 1.0
    elif session['commit_count'] > 5:
        hours += 0.5

    return round(hours, 1)


def get_session_date_label(session):
    """
    Assign a session to a calendar date for CSV output.

    Overnight sessions are assigned to the date they started on,
    so a session from Jan 18 21:00 to Jan 19 10:00 belongs to Jan 18.
    """
    return session['start'].date()


def aggregate_sessions_by_date(sessions, start_date, end_date):
    """
    Convert session-based data into per-date rows matching the existing CSV format.

    When multiple sessions fall on the same date, hours are summed.
    When a session spans midnight, all hours go to the start date.
    This keeps backward compatibility with convert_to_tempo.py and auto_tempo_import.py.
    """
    date_data = defaultdict(lambda: {
        'hours': 0.0, 'commits': [], 'repos': set(),
        'earliest': None, 'latest': None,
    })

    for session in sessions:
        d = session['date_label']
        date_data[d]['hours'] += session['hours']
        date_data[d]['commits'].extend(session['commits'])
        date_data[d]['repos'].update(session['repositories'])

        if date_data[d]['earliest'] is None or session['start'] < date_data[d]['earliest']:
            date_data[d]['earliest'] = session['start']
        if date_data[d]['latest'] is None or session['end'] > date_data[d]['latest']:
            date_data[d]['latest'] = session['end']

    rows = []
    current_date = start_date
    while current_date <= end_date:
        if current_date in date_data:
            dd = date_data[current_date]
            commits = sorted(dd['commits'], key=lambda c: c['date'])
            messages = '; '.join(c['message'][:50].replace('\n', ' ') for c in commits[:3])
            if len(commits) > 3:
                messages += f' ... (+{len(commits) - 3} more)'

            rows.append({
                'Date': current_date.strftime('%Y-%m-%d'),
                'Day': current_date.strftime('%A'),
                'Weekend': 'Yes' if current_date.weekday() >= 5 else 'No',
                'Worked': 'Yes',
                'Hours': round(dd['hours'], 1),
                'Commits': len(commits),
                'Start Time': dd['earliest'].strftime('%H:%M'),
                'End Time': dd['latest'].strftime('%H:%M'),
                'Repositories': ', '.join(sorted(dd['repos'])),
                'Work Summary': messages,
            })
        else:
            rows.append({
                'Date': current_date.strftime('%Y-%m-%d'),
                'Day': current_date.strftime('%A'),
                'Weekend': 'Yes' if current_date.weekday() >= 5 else 'No',
                'Worked': 'No',
                'Hours': 0.0,
                'Commits': 0,
                'Start Time': '',
                'End Time': '',
                'Repositories': '',
                'Work Summary': 'No commits',
            })
        current_date += timedelta(days=1)

    return rows


def write_sessions_detail_csv(sessions, file_path):
    """Write a per-session CSV with classification and timing details."""
    fieldnames = [
        'Session', 'Date Label', 'Type', 'Start', 'End',
        'Duration (h)', 'Calculated Hours', 'Commits',
        'Repositories', 'Summary',
    ]
    with open(file_path, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        for i, session in enumerate(sessions, 1):
            commits = session['commits']
            messages = '; '.join(c['message'][:50].replace('\n', ' ') for c in commits[:3])
            if len(commits) > 3:
                messages += f' ... (+{len(commits) - 3} more)'
            writer.writerow({
                'Session': i,
                'Date Label': session['date_label'].strftime('%Y-%m-%d'),
                'Type': session['type'],
                'Start': session['start'].strftime('%Y-%m-%d %H:%M'),
                'End': session['end'].strftime('%Y-%m-%d %H:%M'),
                'Duration (h)': round(session['duration_hours'], 1),
                'Calculated Hours': session['hours'],
                'Commits': session['commit_count'],
                'Repositories': ', '.join(session['repositories']),
                'Summary': messages,
            })


def write_overnight_report(sessions, sleep_patterns, file_path, git_handle, start_date, end_date):
    """Write a human-readable overnight analysis report."""
    all_nighters = [p for p in sleep_patterns if p['kind'] == 'all_nighter_session']
    short_sleeps = [p for p in sleep_patterns if p['kind'] == 'short_sleep']

    total_session_hours = sum(s['hours'] for s in sessions)
    total_sessions = len(sessions)

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(f"OVERNIGHT ANALYSIS - {git_handle}\n")
        f.write(f"Period: {start_date} to {end_date}\n")
        f.write("=" * 60 + "\n\n")

        f.write(f"TOTAL: {total_sessions} work sessions, {total_session_hours:.1f} hours\n\n")

        # All-nighters
        f.write(f"ALL-NIGHTERS DETECTED: {len(all_nighters)}\n")
        f.write("-" * 60 + "\n")
        if all_nighters:
            for i, an in enumerate(all_nighters, 1):
                s = an['session']
                f.write(f"  {i}. {s['start'].strftime('%a %b %d %H:%M')} -> "
                        f"{s['end'].strftime('%a %b %d %H:%M')} "
                        f"({s['duration_hours']:.1f}h span, {s['hours']}h calculated, "
                        f"{s['commit_count']} commits)\n")
                f.write(f"     Repos: {', '.join(s['repositories'])}\n")
        else:
            f.write("  None detected.\n")
        f.write("\n")

        # Short-sleep nights
        f.write(f"SHORT-SLEEP NIGHTS (gap <= 4h): {len(short_sleeps)}\n")
        f.write("-" * 60 + "\n")
        if short_sleeps:
            for i, ss in enumerate(short_sleeps, 1):
                before = sessions[ss['session_before_idx']]
                after = sessions[ss['session_after_idx']]
                f.write(f"  {i}. Night of {ss['night_of'].strftime('%a %b %d')}: "
                        f"{ss['gap_hours']:.1f}h gap\n")
                f.write(f"     Stopped: {before['end'].strftime('%H:%M')} | "
                        f"Resumed: {after['start'].strftime('%H:%M')}\n")
        else:
            f.write("  None detected.\n")
        f.write("\n")

        # Full session timeline
        f.write("SESSION TIMELINE\n")
        f.write("-" * 60 + "\n")
        for i, s in enumerate(sessions, 1):
            marker = ""
            if s['type'] == 'all_nighter':
                marker = " ** ALL-NIGHTER **"
            elif s['type'] == 'weekend':
                marker = " [weekend]"
            elif s['type'] == 'evening':
                marker = " [evening]"
            elif s['type'] == 'early_morning':
                marker = " [early morning]"

            f.write(f"  {i:3d}. {s['start'].strftime('%a %b %d %H:%M')} - "
                    f"{s['end'].strftime('%H:%M')}  "
                    f"{s['hours']:5.1f}h  {s['commit_count']:3d} commits  "
                    f"{', '.join(s['repositories'])}{marker}\n")

            # Show gap to next session
            if i < len(sessions):
                gap = (sessions[i]['start'] - s['end']).total_seconds() / 3600
                if gap < 6:
                    f.write(f"        ~~~ {gap:.1f}h gap ~~~\n")
        f.write("\n")

        # Hours summary
        f.write("HOURS BY SESSION TYPE\n")
        f.write("-" * 60 + "\n")
        type_hours = defaultdict(lambda: {'hours': 0.0, 'count': 0})
        for s in sessions:
            type_hours[s['type']]['hours'] += s['hours']
            type_hours[s['type']]['count'] += 1
        for stype in ['normal', 'evening', 'early_morning', 'all_nighter', 'weekend']:
            if stype in type_hours:
                th = type_hours[stype]
                f.write(f"  {stype:15s}: {th['hours']:6.1f}h across {th['count']} sessions\n")
        f.write(f"  {'TOTAL':15s}: {total_session_hours:6.1f}h across {total_sessions} sessions\n")


def generate_session_tracking_files(all_commits, start_date, end_date, output_dir, git_handle):
    """
    Generate session-based tracking files alongside the existing date-based ones.

    Produces:
    - Backward-compatible date CSV (session-based hours aggregated per date)
    - Detailed per-session CSV
    - Overnight analysis report (all-nighters, short-sleep nights)
    """
    # Detect sessions
    sessions = detect_sessions(all_commits, gap_threshold_hours=3.0)

    # Classify and calculate hours for each session
    for session in sessions:
        session['type'] = classify_session(session)
        session['hours'] = calculate_session_hours(session)
        session['date_label'] = get_session_date_label(session)

    # Analyze sleep patterns
    sleep_patterns = analyze_sleep_patterns(sessions)

    # Write backward-compatible date CSV
    date_rows = aggregate_sessions_by_date(sessions, start_date, end_date)
    session_csv = output_dir / f'{git_handle}_session_time.csv'
    write_csv(date_rows, session_csv, 'Session-Based Time')

    # Write detailed sessions CSV
    sessions_detail = output_dir / f'{git_handle}_sessions_detail.csv'
    write_sessions_detail_csv(sessions, sessions_detail)

    # Write overnight analysis report
    overnight_report = output_dir / f'{git_handle}_overnight_analysis.txt'
    write_overnight_report(sessions, sleep_patterns, overnight_report, git_handle, start_date, end_date)

    return session_csv, sessions_detail, overnight_report


def calculate_hours(commits, is_overtime_session):
    """
    Estimate hours worked based on commit timestamps and frequency.
    
    Calculation method:
    - Hours are NOT based on lines of code changed
    - Hours are estimated from commit timestamps and patterns:
      1. Base hours: Minimum hours for a work session (2h normal, 3h overtime)
      2. Time span: If commits span multiple hours, add the actual time difference
      3. Commit frequency: More commits indicate more intensive work (+0.5h per 10 commits)
    
    This method assumes:
    - Commits represent active work periods
    - Time between first and last commit represents continuous work
    - Higher commit frequency indicates more intensive work
    - Minimum session duration reflects setup/context switching time
    
    Limitations:
    - Does not account for time spent without committing (thinking, planning, meetings)
    - Does not account for code review, documentation, or other non-coding work
    - May underestimate hours for developers who commit infrequently
    - May overestimate for developers who make many small commits
    """
    if not commits:
        return 0.0
    
    commits = sorted(commits, key=lambda x: x['date'])
    first_commit = commits[0]['date']
    last_commit = commits[-1]['date']
    
    time_diff = last_commit - first_commit
    
    if is_overtime_session:
        # Base hours: minimum 3 hours for evening/overtime work
        # Assumes evening work sessions are typically longer
        base_hours = 3.0
        max_hours = 6.0
    else:
        # Base hours: minimum 2 hours for normal workday
        # Accounts for setup time, context switching, etc.
        base_hours = 2.0
        max_hours = 8.0
    
    # If commits span more than base hours, add the actual time difference
    # This captures the actual duration of the work session
    if time_diff.total_seconds() > base_hours * 3600:
        extra_hours = (time_diff.total_seconds() - base_hours * 3600) / 3600
        base_hours = min(base_hours + extra_hours, max_hours)
    
    # If there are many commits, might indicate longer or more intensive work session
    # More commits = more changes = potentially more time spent
    if len(commits) > 10:
        base_hours = min(base_hours + 0.5, max_hours)
    if len(commits) > 20:
        base_hours = min(base_hours + 0.5, max_hours)
    
    return round(base_hours, 1)

def get_employee_mapping():
    """Return mapping of git handle variations to canonical employee names."""
    return {
        # Ruben van der Linde
        'rubenvdlinde': 'Ruben van der Linde',
        'ruben@conduction.nl': 'Ruben van der Linde',
        'Ruben van der Linde': 'Ruben van der Linde',
        'rubenvdlinde@gmail.com': 'Ruben van der Linde',
        'rubenvdlinde@users.noreply.github.com': 'Ruben van der Linde',
        'ruben': 'Ruben van der Linde',
        
        # Remko Huisman
        '43807324+remko48': 'Remko Huisman',
        '43807324+remko48@users.noreply.github.com': 'Remko Huisman',
        'remko48': 'Remko Huisman',
        'remko@conduction.nl': 'Remko Huisman',
        'Remko Huisman': 'Remko Huisman',
        'Remko': 'Remko Huisman',
        
        # Barry Brands
        '57346398+bbrands02': 'Barry Brands',
        '57346398+bbrands02@users.noreply.github.com': 'Barry Brands',
        'bbrands02': 'Barry Brands',
        'barrybrands02': 'Barry Brands',
        'barrybrands02@hotmail.com': 'Barry Brands',
        'Barry Brands': 'Barry Brands',
        
        # Mark West
        '66728126+MWest2020': 'Mark West',
        '66728126+MWest2020@users.noreply.github.com': 'Mark West',
        'MWest2020': 'Mark West',
        'markwesterweel': 'Mark West',
        'markwesterweel@conduction.nl': 'Mark West',
        'Mark West': 'Mark West',
        'Mark westerweel': 'Mark West',
        'Mwest2020': 'Mark West',
        'mwesterweel': 'Mark West',
        'mwesterweel@hotmail.com': 'Mark West',
        
        # Alex Rekachynskyi
        '87539434+CBibop12': 'Alex Rekachynskyi',
        '87539434+CBibop12@users.noreply.github.com': 'Alex Rekachynskyi',
        'Alex': 'Alex Rekachynskyi',
        'Alex Rekachynskyi': 'Alex Rekachynskyi',
        'phone.070718@gmail.com': 'Alex Rekachynskyi',
        'phone.070718': 'Alex Rekachynskyi',
        
        # Robert Zondervan
        'Robert Zondervan': 'Robert Zondervan',
        'robert@conduction.nl': 'Robert Zondervan',
        'rjzondervan': 'Robert Zondervan',
        'rjzondervan@gmail.com': 'Robert Zondervan',
        'rjzondervan@users.noreply.github.com': 'Robert Zondervan',
        'robert': 'Robert Zondervan',
        
        # Thijn Douwma
        'Thijn': 'Thijn Douwma',
        'Thijn Douwma': 'Thijn Douwma',
        'thijn@conduction.nl': 'Thijn Douwma',
        'Ralkey': 'Thijn Douwma',
        'Ralkey@outlook.com': 'Thijn Douwma',
        'SudoThijn': 'Thijn Douwma',
        
        # Wilco Louwerse
        'Wilco Louwerse': 'Wilco Louwerse',
        'wilco@conduction.nl': 'Wilco Louwerse',
        'wilco@louwerse.com': 'Wilco Louwerse',
        'wilco': 'Wilco Louwerse',
        
        # Matthias Oliveiro
        'Matthias Oliveiro': 'Matthias Oliveiro',
        'matthias@conduction.nl': 'Matthias Oliveiro',
        'matthiasoliveiro@gmail.com': 'Matthias Oliveiro',
        'matthiasoliveiro': 'Matthias Oliveiro',
        'matthias': 'Matthias Oliveiro',
    }

def get_git_handles_from_repos(repo_paths):
    """Extract all unique git handles from commits in repositories and map to canonical names."""
    handles = set()
    employee_mapping = get_employee_mapping()
    
    for repo_path in repo_paths:
        if not os.path.exists(repo_path):
            continue
        try:
            result = subprocess.run(
                ['git', 'log', '--all', '--since=2020-01-01', '--pretty=format:%an|%ae', '--date=iso'],
                cwd=repo_path,
                capture_output=True,
                text=True,
                check=True
            )
            for line in result.stdout.strip().split('\n'):
                if '|' in line:
                    name, email = line.split('|', 1)
                    handles.add(name)
                    handles.add(email)
                    # Also add username part of email
                    if '@' in email:
                        handles.add(email.split('@')[0])
        except subprocess.CalledProcessError:
            continue
    
    # Filter out bots and map to canonical names
    filtered_handles = set()
    for handle in handles:
        # Skip bots
        if any(bot in handle.lower() for bot in ['bot', 'github action', 'cursor agent', 'action@github', 'actions@github']):
            continue
        if not handle or handle.strip() == '':
            continue
        
        # Map to canonical name - check exact match first, then lowercase match
        canonical = employee_mapping.get(handle)
        if not canonical:
            # Try lowercase version
            canonical = employee_mapping.get(handle.lower())
        if not canonical:
            # Try matching by email username
            if '@' in handle:
                email_user = handle.split('@')[0]
                canonical = employee_mapping.get(email_user)
                if not canonical:
                    canonical = employee_mapping.get(email_user.lower())
        
        # If still no match, use handle as-is (but normalize case for known employees)
        if not canonical:
            # Check if it's a known employee name (case-insensitive)
            handle_lower = handle.lower()
            for mapped_handle, mapped_name in employee_mapping.items():
                if mapped_handle.lower() == handle_lower:
                    canonical = mapped_name
                    break
            
            if not canonical:
                canonical = handle
        
        filtered_handles.add(canonical)
    
    return sorted(filtered_handles)

def get_all_handle_variations(canonical_name):
    """Get all git handle variations for a canonical employee name."""
    employee_mapping = get_employee_mapping()
    variations = [canonical_name]  # Include canonical name itself
    
    # Find all handles that map to this canonical name
    for handle, mapped_name in employee_mapping.items():
        if mapped_name == canonical_name:
            variations.append(handle)
    
    return variations

def get_commits_for_handle(repo_paths, canonical_name, start_date, end_date):
    """Get all commits for a canonical employee name across all their git handle variations."""
    all_commits = []
    
    # Get all variations of this employee's git handles
    handle_variations = get_all_handle_variations(canonical_name)
    
    for repo_path in repo_paths:
        repo_name = os.path.basename(repo_path.rstrip('/'))
        if not os.path.exists(repo_path):
            print(f"Warning: Repository not found: {repo_path}")
            continue
        
        # Build git command with multiple --author flags for all variations
        git_cmd = ['git', 'log', '--all', f'--since={start_date}', f'--until={end_date}', '--pretty=format:%H|%ai|%s', '--date=iso']
        
        # Add author filters for all handle variations
        for handle in handle_variations:
            git_cmd.extend(['--author', handle])
        
        try:
            result = subprocess.run(
                git_cmd,
                cwd=repo_path,
                capture_output=True,
                text=True,
                check=True
            )
            
            for line in result.stdout.strip().split('\n'):
                if not line.strip():
                    continue
                commit = parse_commit_line(line, repo_name)
                if commit:
                    all_commits.append(commit)
        except subprocess.CalledProcessError as e:
            print(f"Warning: Error fetching commits from {repo_path}: {e}")
            continue
    
    return all_commits

def generate_tracking_files(all_commits, start_date, end_date, output_dir, git_handle):
    """Generate normal time and overtime tracking files."""
    # Separate commits into normal time and overtime
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
    
    # Generate normal time tracking
    normal_rows = generate_rows(normal_commits_by_date, start_date, end_date, False)
    normal_file = output_dir / f'{git_handle}_normal_time.csv'
    write_csv(normal_rows, normal_file, 'Normal Time')
    
    # Generate overtime tracking
    overtime_rows = generate_rows(overtime_commits_by_date, start_date, end_date, True)
    overtime_file = output_dir / f'{git_handle}_overtime.csv'
    write_csv(overtime_rows, overtime_file, 'Overtime')
    
    # Generate summaries
    normal_summary = generate_summary(normal_rows, start_date, end_date, 'Normal Time', is_overtime=False)
    overtime_summary = generate_summary(overtime_rows, start_date, end_date, 'Overtime', is_overtime=True)
    
    summary_file = output_dir / f'{git_handle}_summary.txt'
    with open(summary_file, 'w', encoding='utf-8') as f:
        f.write("TIME TRACKING SUMMARY\n")
        f.write("=" * 50 + "\n\n")
        f.write(f"Git Handle: {git_handle}\n")
        f.write(f"Period: {start_date} to {end_date}\n")
        f.write(f"Total days tracked: {(end_date - start_date).days + 1}\n\n")
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
            
            # Get unique repositories
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
    
    Formula:
    - Total weeks in period = (end_date - start_date).days / 7
    - Working weeks = Total weeks * (47/52)  # Account for holidays
    - Expected hours = Working weeks * hours_per_week
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
    
    # Add expected vs tracked comparison for normal time only
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
    parser = argparse.ArgumentParser(description='Generate time tracking files from git commit history')
    parser.add_argument('--start-date', type=str, default=None,
                        help='Start date (YYYY-MM-DD), defaults to Jan 1 of current year')
    parser.add_argument('--end-date', type=str, default=None,
                        help='End date (YYYY-MM-DD), defaults to Dec 31 of current year')
    parser.add_argument('--git-handles', type=str, nargs='+', default=None,
                        help='Git handles to track (name or email). If not provided, auto-detects from repos')
    parser.add_argument('--repos', type=str, nargs='+', default=None,
                        help='Repository paths. Defaults to ConductionNL repos in apps-extra')
    parser.add_argument('--output-dir', type=str, default=None,
                        help='Output directory. Defaults to timetracking/{git_handle}/')
    parser.add_argument('--auto-detect-handles', action='store_true',
                        help='Auto-detect all git handles from repositories')
    
    args = parser.parse_args()
    
    # Set default dates
    current_year = datetime.now().year
    if args.start_date:
        start_date = datetime.strptime(args.start_date, '%Y-%m-%d').date()
    else:
        start_date = datetime(current_year, 1, 1).date()
    
    if args.end_date:
        end_date = datetime.strptime(args.end_date, '%Y-%m-%d').date()
    else:
        end_date = datetime(current_year, 12, 31).date()
    
    # Set default repositories
    base_path = Path('/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra')
    if args.repos:
        repo_paths = [os.path.abspath(r) for r in args.repos]
    else:
        # Load repository names from config file if it exists
        config_file = base_path / 'conduction_repos_config.txt'
        default_repo_names = []
        
        if config_file.exists():
            try:
                with open(config_file, 'r') as f:
                    for line in f:
                        line = line.strip()
                        if line and not line.startswith('#'):
                            default_repo_names.append(line)
            except:
                pass
        
        # Fallback to default list if config file doesn't exist or is empty
        if not default_repo_names:
            default_repo_names = [
                'openregister',
                'opencatalogi',
                'openconnector',
                'softwarecatalog',
                'tilburg-woo-ui',
                'docudesk',
                'larpingapp',
                'commonground-gateway',
                'woo-website-template-apiv2',
            ]
        
        repo_paths = []
        # Search in multiple locations
        search_locations = [
            base_path,  # apps-extra
            base_path.parent.parent,  # workspace/server
            base_path.parent.parent.parent,  # workspace
            Path('/home/rubenlinde'),
            Path('/home/rubenlinde/workspace'),
            Path('/home/rubenlinde/projects'),
            Path('/home/rubenlinde/git'),
            Path('/home/rubenlinde/repos'),
        ]
        
        # Search for each repository name in all locations
        found_repos = set()
        for search_dir in search_locations:
            if not os.path.exists(search_dir):
                continue
            
            for repo_name in default_repo_names:
                # Try direct path
                repo_path = search_dir / repo_name
                if os.path.exists(repo_path) and os.path.exists(repo_path / '.git'):
                    repo_path_str = str(repo_path)
                    if repo_path_str not in found_repos:
                        repo_paths.append(repo_path_str)
                        found_repos.add(repo_path_str)
                
                # Also search recursively (but limit depth to avoid long searches)
                try:
                    for root, dirs, files in os.walk(search_dir, followlinks=False):
                        # Limit depth to 3 levels
                        depth = root[len(str(search_dir)):].count(os.sep)
                        if depth > 3:
                            dirs[:] = []  # Don't recurse deeper
                            continue
                        
                        if repo_name in dirs:
                            repo_path = Path(root) / repo_name
                            if os.path.exists(repo_path / '.git'):
                                repo_path_str = str(repo_path)
                                if repo_path_str not in found_repos:
                                    repo_paths.append(repo_path_str)
                                    found_repos.add(repo_path_str)
                                    dirs.remove(repo_name)  # Don't search inside found repo
                except:
                    continue
        
        # If no repos found, at least try the apps-extra ones
        if not repo_paths:
            repo_paths = [
                str(base_path / 'openregister'),
                str(base_path / 'opencatalogi'),
                str(base_path / 'openconnector'),
                str(base_path / 'softwarecatalog'),
            ]
    
    # Filter existing repositories and verify they're git repos
    valid_repos = []
    for repo_path in repo_paths:
        if os.path.exists(repo_path) and os.path.exists(os.path.join(repo_path, '.git')):
            valid_repos.append(repo_path)
    
    repo_paths = valid_repos
    
    if not repo_paths:
        print("Error: No valid repositories found")
        return
    
    print(f"Scanning {len(repo_paths)} repositories:")
    for repo in repo_paths:
        print(f"  - {os.path.basename(repo)}")
    
    # Get canonical employee names (consolidated)
    if args.auto_detect_handles or args.git_handles is None:
        print("\nAuto-detecting employees from repositories...")
        canonical_names = get_git_handles_from_repos(repo_paths)
        print(f"Found {len(canonical_names)} unique employees")
        if args.git_handles:
            # Map provided handles to canonical names
            employee_mapping = get_employee_mapping()
            provided_canonical = set()
            for handle in args.git_handles:
                canonical = employee_mapping.get(handle, handle)
                provided_canonical.add(canonical)
            canonical_names = list(set(canonical_names) | provided_canonical)
    else:
        # Map provided handles to canonical names
        employee_mapping = get_employee_mapping()
        canonical_names = set()
        for handle in args.git_handles:
            canonical = employee_mapping.get(handle, handle)
            canonical_names.add(canonical)
        canonical_names = sorted(canonical_names)
    
    if not canonical_names:
        print("Error: No employees found or specified")
        return
    
    # Process each canonical employee name
    for canonical_name in canonical_names:
        print(f"\nProcessing employee: {canonical_name}")
        
        # Set output directory using canonical name
        if args.output_dir:
            output_dir = Path(args.output_dir)
        else:
            safe_name = canonical_name.replace('@', '_').replace(' ', '_')
            output_dir = base_path / 'timetracking' / safe_name
        
        output_dir.mkdir(parents=True, exist_ok=True)
        
        # Get commits for this employee (all handle variations)
        print(f"Fetching commits from {start_date} to {end_date}...")
        all_commits = get_commits_for_handle(repo_paths, canonical_name, start_date, end_date)
        print(f"Found {len(all_commits)} total commits")
        
        if not all_commits:
            print(f"No commits found for {canonical_name}")
            continue
        
        # Generate tracking files using canonical name
        normal_file, overtime_file, summary_file = generate_tracking_files(
            all_commits, start_date, end_date, output_dir, canonical_name
        )
        
        print(f"Generated files (date-based):")
        print(f"  - Normal time: {normal_file}")
        print(f"  - Overtime: {overtime_file}")
        print(f"  - Summary: {summary_file}")

        # Generate session-based tracking (fixes all-nighter splitting)
        session_csv, sessions_detail, overnight_report = generate_session_tracking_files(
            all_commits, start_date, end_date, output_dir, canonical_name
        )

        print(f"Generated files (session-based):")
        print(f"  - Session time: {session_csv}")
        print(f"  - Sessions detail: {sessions_detail}")
        print(f"  - Overnight analysis: {overnight_report}")

if __name__ == '__main__':
    main()

