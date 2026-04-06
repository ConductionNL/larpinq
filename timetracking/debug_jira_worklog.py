#!/usr/bin/env python3
"""Check Jira worklog for the issue."""

import requests
import os
import sys

jira_token = os.environ.get('JIRA_API_TOKEN')
jira_email = "ruben@conduction.nl"
issue_key = "OP-522"

if not jira_token:
    print("Set JIRA_API_TOKEN")
    sys.exit(1)

url = f"https://conduction.atlassian.net/rest/api/3/issue/{issue_key}/worklog"

response = requests.get(url, auth=(jira_email, jira_token))
if response.status_code == 200:
    result = response.json()
    print(f"JIRA WORKLOGS FOR {issue_key}:")
    print(f"Total worklogs: {result.get('total', 0)}\n")
    
    for wl in result.get('worklogs', []):
        print(f"  Worklog ID: {wl.get('id')}")
        print(f"  Author: {wl.get('author', {}).get('displayName')}")
        print(f"  Time Spent: {wl.get('timeSpent')}")
        print(f"  Time Spent (seconds): {wl.get('timeSpentSeconds')}")
        print(f"  Started: {wl.get('started')}")
        print(f"  Created: {wl.get('created')}")
        print(f"  Updated: {wl.get('updated')}")
        print()
else:
    print(f"Error: {response.status_code}")
    print(response.text)







