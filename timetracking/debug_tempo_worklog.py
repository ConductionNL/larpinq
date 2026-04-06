#!/usr/bin/env python3
"""Debug script to check Tempo worklog details."""

import requests
import os
import sys

tempo_token = os.environ.get('TEMPO_API_TOKEN')
worklog_id = "38812"

if not tempo_token:
    print("Set TEMPO_API_TOKEN")
    sys.exit(1)

url = f"https://api.tempo.io/4/worklogs/{worklog_id}"
headers = {
    'Authorization': f'Bearer {tempo_token}',
    'Content-Type': 'application/json'
}

response = requests.get(url, headers=headers)
if response.status_code == 200:
    worklog = response.json()
    print("TEMPO WORKLOG DETAILS:")
    print(f"  Worklog ID: {worklog.get('tempoWorklogId')}")
    print(f"  Issue ID: {worklog.get('issue', {}).get('id')}")
    print(f"  Issue Key: {worklog.get('issue', {}).get('key')}")
    print(f"  Start Date: {worklog.get('startDate')}")
    print(f"  Start Time: {worklog.get('startTime')}")
    print(f"  Time Spent (seconds): {worklog.get('timeSpentSeconds')}")
    print(f"  Time Spent (hours): {worklog.get('timeSpentSeconds', 0) / 3600}")
    print(f"  Description: {worklog.get('description')}")
    print(f"  Author: {worklog.get('author', {}).get('displayName')}")
    print(f"\nFull JSON:")
    import json
    print(json.dumps(worklog, indent=2))
else:
    print(f"Error: {response.status_code}")
    print(response.text)







