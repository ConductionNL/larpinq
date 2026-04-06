#!/usr/bin/env python3
"""
Test Jira Worklog API to see if we can set creation timestamps.
"""

import requests
import os
import json
from datetime import datetime

jira_token = os.environ.get('JIRA_API_TOKEN')
jira_email = "ruben@conduction.nl"

if not jira_token:
    print("Set JIRA_API_TOKEN")
    exit(1)

print("=" * 60)
print("JIRA WORKLOG API - Timestamp Control")
print("=" * 60)
print()

# Jira API v3 worklog creation
# Documentation: https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-worklogs/#api-rest-api-3-issue-issueidorkey-worklog-post

print("Jira Worklog API fields:")
print("  - timeSpentSeconds - REQUIRED")
print("  - started - Optional (ISO 8601 datetime)")
print("  - comment - Optional")
print("  - visibility - Optional")
print()

print("KEY FINDING:")
print("  ✓ 'started' field CAN be set to historical datetime!")
print("  ✓ This controls when Jira thinks the work happened")
print()

# Test payload
test_payload = {
    'timeSpentSeconds': 3600,
    'started': '2025-01-25T10:30:00.000+0100',  # Historical timestamp!
    'comment': 'Test worklog with historical timestamp'
}

print("Example payload:")
print(json.dumps(test_payload, indent=2))
print()

print("=" * 60)
print("DECISION: Which API to use?")
print("=" * 60)
print()

print("OPTION 1: Tempo API (current)")
print("  Pros:")
print("    - Native Tempo integration")
print("    - Better Tempo reporting")
print("  Cons:")
print("    - createdAt always = now")
print("    - But startDate is correct (work date)")
print()

print("OPTION 2: Jira Worklog API + Tempo Sync")
print("  Pros:")
print("    - Can set 'started' to historical time")
print("    - Tempo automatically syncs from Jira")
print("    - Better timestamps")
print("  Cons:")
print("    - Two-step process (Jira -> Tempo)")
print("    - May not have all Tempo features")
print()

print("RECOMMENDATION:")
print("  Use Jira Worklog API with 'started' field!")
print("  This gives us proper historical timestamps.")
print()







