#!/usr/bin/env python3
"""Quick script to list all Jira projects."""

import requests
import os
import sys

jira_token = os.environ.get('JIRA_API_TOKEN')
jira_url = "https://conduction.atlassian.net"
jira_email = "ruben@conduction.nl"

if not jira_token:
    print("Set JIRA_API_TOKEN environment variable")
    sys.exit(1)

url = f"{jira_url}/rest/api/3/project"
response = requests.get(url, auth=(jira_email, jira_token))

if response.status_code == 200:
    projects = response.json()
    print(f"\n{'='*60}")
    print(f"AVAILABLE JIRA PROJECTS")
    print(f"{'='*60}\n")
    for proj in sorted(projects, key=lambda x: x['key']):
        print(f"  {proj['key']:<15} {proj['name']}")
    print(f"\n{'='*60}")
    print(f"Total projects: {len(projects)}")
else:
    print(f"Error: {response.status_code}")
    print(response.text)







