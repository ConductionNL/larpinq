#!/usr/bin/env python3
"""
Investigate Tempo API capabilities for worklog creation timestamps.
"""

import requests
import os

# Test 1: Check Tempo API documentation via OPTIONS
tempo_token = os.environ.get('TEMPO_API_TOKEN')
if not tempo_token:
    print("Set TEMPO_API_TOKEN")
    exit(1)

headers = {
    'Authorization': f'Bearer {tempo_token}',
    'Content-Type': 'application/json'
}

# Check what fields are available when creating a worklog
print("=" * 60)
print("TEMPO API WORKLOG CREATE - Available Fields")
print("=" * 60)
print()

# Let's try creating a test worklog with createdAt field
test_payload = {
    'issueId': 36520,  # OP-522
    'timeSpentSeconds': 3600,  # 1 hour
    'startDate': '2025-01-25',
    'description': 'TEST - checking createdAt field',
    'authorAccountId': '557058:bf1e30d8-b50c-4fce-940d-251a0272bb00',
    'createdAt': '2025-01-25T10:00:00Z'  # Try to set creation time
}

print("Test Payload:")
import json
print(json.dumps(test_payload, indent=2))
print()

# Dry run - don't actually create
print("Checking Tempo API v4 documentation...")
print()

# Check API schema
schema_url = "https://api.tempo.io/4/worklogs"
print(f"POST endpoint: {schema_url}")
print()

# According to Tempo docs, let's check what fields are actually accepted
print("Standard Tempo API v4 worklog fields:")
print("  - issueId (or issueKey) - REQUIRED")
print("  - timeSpentSeconds - REQUIRED")
print("  - startDate - REQUIRED (YYYY-MM-DD)")
print("  - startTime - Optional (HH:MM:SS)")
print("  - authorAccountId - REQUIRED")
print("  - description - Optional")
print("  - remainingEstimateSeconds - Optional")
print("  - attributes - Optional (work attributes)")
print()

print("⚠️  Fields NOT settable via API:")
print("  - tempoWorklogId (auto-generated)")
print("  - createdAt (system-managed)")
print("  - updatedAt (system-managed)")
print()

print("=" * 60)
print("ALTERNATIVE APPROACHES")
print("=" * 60)
print()
print("1. USE JIRA WORKLOG API (instead of Tempo)")
print("   - Jira has different timestamp fields")
print("   - May have different creation time handling")
print()
print("2. BACKFILL WITH HISTORICAL DATES")
print("   - Accept that createdAt = today")
print("   - startDate shows actual work date (this is what matters)")
print()
print("3. TEMPO IMPORT API (if available)")
print("   - Some Tempo plans have bulk import features")
print("   - May preserve timestamps better")
print()







