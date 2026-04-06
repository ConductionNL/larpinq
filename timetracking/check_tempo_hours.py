#!/usr/bin/env python3
"""
Quick script to check current Tempo hours for a user.
"""

import requests
import sys
import os
from datetime import datetime

def get_tempo_worklogs(base_url, tempo_token, account_id, start_date, end_date):
    """Get all worklogs for a user in a date range."""
    # Try Tempo Cloud API v4 instead
    url = "https://api.tempo.io/4/worklogs/user/{account_id}"
    
    headers = {
        'Authorization': f'Bearer {tempo_token}',
        'Content-Type': 'application/json'
    }
    
    params = {
        'from': start_date,
        'to': end_date
    }
    
    # Format the URL with account_id
    formatted_url = url.format(account_id=account_id)
    
    try:
        response = requests.get(formatted_url, headers=headers, params=params)
        response.raise_for_status()
        return response.json()
    except requests.exceptions.RequestException as e:
        print(f"❌ Error getting worklogs: {e}")
        if hasattr(e, 'response') and e.response:
            print(f"Response: {e.response.text}")
        return None

def get_jira_user(base_url, email, api_token):
    """Get Jira user account ID from email."""
    url = f"{base_url}/rest/api/3/user/search"
    params = {'query': email}
    
    try:
        response = requests.get(url, auth=(email, api_token), params=params)
        response.raise_for_status()
        users = response.json()
        if users:
            return users[0]['accountId']
        return None
    except requests.exceptions.RequestException as e:
        print(f"❌ Error getting user: {e}")
        return None

def main():
    # Get credentials from environment
    jira_token = os.environ.get('JIRA_API_TOKEN')
    tempo_token = os.environ.get('TEMPO_API_TOKEN')
    jira_url = 'https://conduction.atlassian.net'
    jira_email = 'ruben@conduction.nl'
    
    if not jira_token or not tempo_token:
        print("❌ Missing API tokens. Set JIRA_API_TOKEN and TEMPO_API_TOKEN environment variables.")
        sys.exit(1)
    
    print("🔍 Checking Tempo hours for ruben@conduction.nl...\n")
    
    # Get user account ID
    print("Getting user account ID...")
    account_id = get_jira_user(jira_url, jira_email, jira_token)
    if not account_id:
        print("❌ Could not find user account")
        sys.exit(1)
    
    print(f"✓ Account ID: {account_id}\n")
    
    # Get worklogs for 2025
    print("Fetching 2025 worklogs...")
    worklogs = get_tempo_worklogs(jira_url, tempo_token, account_id, '2025-01-01', '2025-12-31')
    
    if not worklogs:
        print("❌ Could not fetch worklogs")
        sys.exit(1)
    
    # Process results
    total_count = len(worklogs) if isinstance(worklogs, list) else 0
    total_seconds = 0
    
    if isinstance(worklogs, list):
        for log in worklogs:
            total_seconds += log.get('timeSpentSeconds', 0)
    elif isinstance(worklogs, dict):
        results = worklogs.get('results', [])
        total_count = len(results)
        for log in results:
            total_seconds += log.get('timeSpentSeconds', 0)
    
    total_hours = total_seconds / 3600
    
    print(f"\n{'='*60}")
    print(f"TEMPO HOURS SUMMARY - 2025")
    print(f"{'='*60}")
    print(f"User: {jira_email}")
    print(f"Total worklogs: {total_count}")
    print(f"Total hours: {total_hours:.1f} hours")
    print(f"{'='*60}\n")
    
    if total_count == 0:
        print("✓ No hours logged yet in Tempo for 2025")
        print("✓ Ready to import your git-based time tracking!")
    else:
        print(f"⚠️  You already have {total_count} worklogs in Tempo")
        print("   The import script will ADD new entries (never delete)")

if __name__ == '__main__':
    main()

