#!/usr/bin/env python3
"""
Find workdays with 0 hours logged and generate worklogs for those.
"""

import requests
import os
import sys
import csv
from datetime import datetime, timedelta
from collections import defaultdict

def get_account_id(jira_url, target_email, auth_email, jira_token):
    """Get Jira account ID."""
    url = f"{jira_url}/rest/api/3/user/search"
    params = {'query': target_email}
    
    response = requests.get(url, auth=(auth_email, jira_token), params=params)
    if response.status_code == 200:
        users = response.json()
        for user in users:
            if user.get('emailAddress', '').lower() == target_email.lower():
                return user['accountId']
    return None

def get_all_worklogs_for_user(tempo_token, account_id, year=2025):
    """Fetch all Tempo worklogs."""
    url = f"https://api.tempo.io/4/worklogs/user/{account_id}"
    headers = {'Authorization': f'Bearer {tempo_token}'}
    params = {'from': f'{year}-01-01', 'to': f'{year}-12-31', 'limit': 1000}
    
    response = requests.get(url, headers=headers, params=params)
    if response.status_code == 200:
        return response.json().get('results', [])
    return []

def main():
    jira_token = os.environ.get('JIRA_API_TOKEN')
    tempo_token = os.environ.get('TEMPO_API_TOKEN')
    jira_url = "https://conduction.atlassian.net"
    your_email = "ruben@conduction.nl"
    colleague_email = "remko@conduction.nl"
    
    if not jira_token or not tempo_token:
        print("Error: Set JIRA_API_TOKEN and TEMPO_API_TOKEN")
        sys.exit(1)
    
    print("=" * 70)
    print("FIND WORKDAYS WITH 0 HOURS")
    print("=" * 70)
    print()
    
    # Get account IDs
    print("Getting account IDs...")
    your_id = get_account_id(jira_url, your_email, your_email, jira_token)
    colleague_id = get_account_id(jira_url, colleague_email, your_email, jira_token)
    print(f"  ✓ Your account: {your_id}")
    print(f"  ✓ Remko's account: {colleague_id}")
    print()
    
    # Get worklogs
    print("Fetching worklogs...")
    your_worklogs = get_all_worklogs_for_user(tempo_token, your_id)
    colleague_worklogs = get_all_worklogs_for_user(tempo_token, colleague_id)
    print(f"  ✓ Your worklogs: {len(your_worklogs)}")
    print(f"  ✓ Remko's worklogs: {len(colleague_worklogs)}")
    print()
    
    # Build sets of dates
    your_dates = set()
    for wl in your_worklogs:
        your_dates.add(wl['startDate'])
    
    colleague_daily = defaultdict(list)
    for wl in colleague_worklogs:
        date = wl['startDate']
        hours = wl['timeSpentSeconds'] / 3600
        issue_id = wl['issue']['id'] if 'issue' in wl else None
        colleague_daily[date].append({
            'id': issue_id,
            'hours': hours,
            'description': wl.get('description', '')
        })
    
    # Find workdays with 0 hours
    print("Analyzing workdays...")
    start_date = datetime(2025, 1, 1)
    end_date = datetime(2025, 12, 31)
    current = start_date
    
    zero_hour_days = []
    
    while current <= end_date:
        if current.weekday() < 5:  # Weekday
            date_str = current.strftime('%Y-%m-%d')
            
            if date_str not in your_dates:  # You logged 0 hours
                if date_str in colleague_daily:  # But Remko worked
                    zero_hour_days.append({
                        'date': date_str,
                        'colleague_work': colleague_daily[date_str]
                    })
        
        current += timedelta(days=1)
    
    print(f"  Found {len(zero_hour_days)} workdays with 0 hours")
    print()
    
    # Generate CSV
    print("Generating CSV for 0-hour days...")
    
    worklogs = []
    for day in zero_hour_days:
        date = day['date']
        issues = day['colleague_work']
        
        # Distribute 9 hours across colleague's issues
        total_colleague_hours = sum(i['hours'] for i in issues)
        
        for issue in issues:
            if issue['id']:
                proportion = issue['hours'] / total_colleague_hours if total_colleague_hours > 0 else 1.0/len(issues)
                hours = 9.0 * proportion
                
                if hours > 0.1:
                    worklogs.append({
                        'date': date,
                        'issue_id': issue['id'],
                        'hours': round(hours, 2),
                        'description': f"Development work - {issue['description'][:50]}" if issue['description'] else "Development work"
                    })
    
    output_file = 'zero_hour_days_2025.csv'
    with open(output_file, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=['date', 'issue_id', 'hours', 'description'])
        writer.writeheader()
        writer.writerows(worklogs)
    
    print(f"  ✓ Created: {output_file}")
    print()
    
    # Summary
    print("=" * 70)
    print("SUMMARY")
    print("=" * 70)
    print()
    print(f"Workdays with 0 hours: {len(zero_hour_days)}")
    print(f"Worklogs to create: {len(worklogs)}")
    print(f"Total hours to add: {sum(w['hours'] for w in worklogs):.1f}")
    print()
    print("Note: These are days where you logged NOTHING,")
    print("      but Remko did work (so you probably worked too)")
    print()
    
    # Total calculation
    print("=" * 70)
    print("TOTAL HOURS PROJECTION")
    print("=" * 70)
    print()
    print("Currently logged: 659.7 hours")
    print("From补充_worklogs_2025.csv: 865.4 hours")
    print(f"From {output_file}: {sum(w['hours'] for w in worklogs):.1f} hours")
    print("-" * 70)
    print(f"TOTAL: {659.7 + 865.4 + sum(w['hours'] for w in worklogs):.1f} hours")
    print()
    
    # Calculate expected
    workdays = 0
    current = start_date
    while current <= end_date:
        if current.weekday() < 5:
            workdays += 1
        current += timedelta(days=1)
    
    # Dutch holidays on workdays
    holidays = 8  # Approximate
    net_workdays = workdays - holidays
    expected = net_workdays * 9
    
    actual = 659.7 + 865.4 + sum(w['hours'] for w in worklogs)
    
    print(f"Expected (253 workdays × 9h): {expected} hours")
    print(f"Coverage: {actual/expected*100:.1f}%")
    print()

if __name__ == '__main__':
    main()







