#!/usr/bin/env python3
"""Detailed analysis of Tempo worklogs for 2025."""

import requests
import os
from datetime import datetime
from collections import defaultdict

tempo_token = os.environ.get('TEMPO_API_TOKEN')
jira_token = os.environ.get('JIRA_API_TOKEN')
jira_email = "ruben@conduction.nl"

if not tempo_token or not jira_token:
    print("Set TEMPO_API_TOKEN and JIRA_API_TOKEN")
    exit(1)

# Get account ID
url = "https://conduction.atlassian.net/rest/api/3/myself"
response = requests.get(url, auth=(jira_email, jira_token))
account_id = response.json()['accountId']

print("=" * 60)
print("DETAILED TEMPO ANALYSIS - 2025")
print("=" * 60)
print()

# Get all worklogs for 2025
url = f"https://api.tempo.io/4/worklogs/user/{account_id}"
headers = {'Authorization': f'Bearer {tempo_token}'}
params = {'from': '2025-01-01', 'to': '2025-12-31', 'limit': 1000}

response = requests.get(url, headers=headers, params=params)
worklogs = response.json().get('results', [])

print(f"Total worklogs: {len(worklogs)}")
print()

# Analyze by month
monthly = defaultdict(lambda: {'count': 0, 'hours': 0})
by_source = defaultdict(lambda: {'count': 0, 'hours': 0})

for wl in worklogs:
    date = wl['startDate']
    month = date[:7]  # YYYY-MM
    hours = wl['timeSpentSeconds'] / 3600
    
    monthly[month]['count'] += 1
    monthly[month]['hours'] += hours
    
    # Check description for source
    desc = wl.get('description', '')
    if 'commits' in desc.lower():
        by_source['Auto Import']['count'] += 1
        by_source['Auto Import']['hours'] += hours
    else:
        by_source['Manual']['count'] += 1
        by_source['Manual']['hours'] += hours

print("BREAKDOWN BY MONTH:")
print("-" * 60)
total_hours = 0
for month in sorted(monthly.keys()):
    data = monthly[month]
    print(f"  {month}: {data['count']:3d} worklogs, {data['hours']:6.1f} hours")
    total_hours += data['hours']

print("-" * 60)
print(f"  TOTAL:   {len(worklogs):3d} worklogs, {total_hours:6.1f} hours")
print()

print("BREAKDOWN BY SOURCE:")
print("-" * 60)
for source, data in sorted(by_source.items()):
    print(f"  {source:15s}: {data['count']:3d} worklogs, {data['hours']:6.1f} hours")
print()

print("=" * 60)
print("IMPORT STATUS CHECK")
print("=" * 60)
print()

# Check what we expected to import
print("Expected from import script:")
print("  - 170 issues created")
print("  - 657.7 hours")
print()

print("Actually in Tempo:")
print(f"  - {len(worklogs)} worklogs")
print(f"  - {total_hours:.1f} hours")
print()

if len(worklogs) < 170:
    print("⚠️  WARNING: Fewer worklogs than expected!")
    print(f"   Missing: {170 - len(worklogs)} worklogs")
    print()
    print("Possible reasons:")
    print("  1. Import was interrupted")
    print("  2. Some worklogs failed to create")
    print("  3. Tempo sync delay from Jira")
    print()
    print("Recommendation: Wait 5 minutes and check again")
    print("                Tempo syncs from Jira every few minutes")







