#!/usr/bin/env python3
"""
Update WBSO field (customfield_10038) on all Tempo-tracked issues.
Uses tempo-wbso-update-plan-v2.json for the allocation.
Skips DOCD/LLM/WEBSITE (team-managed). Saves progress for resume.
Only updates issues that are new or changed vs the old plan.
"""

import json
import time
import sys
import os
from datetime import datetime
from base64 import b64encode

try:
    import requests
except ImportError:
    os.system("pip3 install requests --quiet")
    import requests

JIRA_URL = "https://conduction.atlassian.net"
JIRA_EMAIL = "ruben@conduction.nl"
JIRA_TOKEN = os.environ.get("JIRA_TOKEN", "REDACTED_JIRA_TOKEN_ROTATED_2026-05-28")

auth_str = b64encode(f"{JIRA_EMAIL}:{JIRA_TOKEN}".encode()).decode()
HEADERS = {
    "Authorization": f"Basic {auth_str}",
    "Content-Type": "application/json",
    "Accept": "application/json"
}

SKIP_FIELD_PROJECTS = {"DOCD", "LLM", "WEBSITE"}

PLAN_FILE = "wbso/raw/tempo-wbso-update-plan-v2.json"
OLD_PLAN_FILE = "wbso/raw/tempo-wbso-update-plan.json"
PROGRESS_FILE = "wbso/raw/tempo-wbso-update-progress-v2.json"


def load_progress():
    if os.path.exists(PROGRESS_FILE):
        with open(PROGRESS_FILE) as f:
            return json.load(f)
    return {"completed": {}, "errors": {}}


def save_progress(progress):
    with open(PROGRESS_FILE, "w") as f:
        json.dump(progress, f)


def main():
    with open(PLAN_FILE) as f:
        plan = json.load(f)

    # Load old plan to detect changes
    old_plan = {}
    if os.path.exists(OLD_PLAN_FILE):
        with open(OLD_PLAN_FILE) as f:
            old_plan = json.load(f)

    # Filter to only new/changed issues
    needs_update = {}
    for key, data in plan.items():
        if key in old_plan:
            old_opts = set(o['id'] for o in old_plan[key].get('wbso_options', []))
            new_opts = set(o['id'] for o in data.get('wbso_options', []))
            if old_opts != new_opts:
                needs_update[key] = data
        else:
            needs_update[key] = data

    progress = load_progress()
    total = len(needs_update)
    already_done = len(progress["completed"])

    print(f"Total plan: {len(plan)} issues")
    print(f"Need update (new/changed): {total}")
    if already_done > 0:
        print(f"Resuming: {already_done}/{total} done, {len(progress['errors'])} errors")

    ok = 0
    skip = 0
    fail = 0
    start_time = time.time()

    for i, (issue_key, data) in enumerate(needs_update.items()):
        if issue_key in progress["completed"]:
            continue

        project = issue_key.split("-")[0]

        if project in SKIP_FIELD_PROJECTS:
            progress["completed"][issue_key] = "skipped"
            skip += 1
        else:
            success = False
            for attempt in range(3):
                try:
                    url = f"{JIRA_URL}/rest/api/3/issue/{issue_key}"
                    payload = {
                        "fields": {
                            "customfield_10038": [{"id": opt["id"]} for opt in data["wbso_options"]]
                        }
                    }
                    resp = requests.put(url, headers=HEADERS, json=payload, timeout=30)
                    if resp.status_code == 204:
                        progress["completed"][issue_key] = "ok"
                        ok += 1
                        success = True
                        break
                    elif resp.status_code == 429:
                        time.sleep(10)
                        continue
                    else:
                        if attempt == 2:
                            progress["completed"][issue_key] = f"error_{resp.status_code}"
                            progress["errors"][issue_key] = {
                                "status": resp.status_code,
                                "detail": resp.text[:200]
                            }
                            fail += 1
                        else:
                            time.sleep(2)
                except Exception as e:
                    if attempt == 2:
                        progress["completed"][issue_key] = f"exception"
                        progress["errors"][issue_key] = {"error": str(e)[:200]}
                        fail += 1
                    else:
                        time.sleep(2)

        current = len(progress["completed"])
        time.sleep(0.3)

        if current % 10 == 0:
            save_progress(progress)

        if current % 50 == 0 or current == total:
            elapsed = time.time() - start_time
            rate = (current - already_done) / elapsed if elapsed > 0 else 0
            remaining = (total - current) / rate if rate > 0 else 0
            print(f"[{current}/{total}] ok: {ok}, skipped: {skip}, failed: {fail} | ~{remaining/60:.0f}min left")
            sys.stdout.flush()

    save_progress(progress)

    elapsed = time.time() - start_time
    print(f"\n{'='*60}")
    print(f"Tempo WBSO Field Update Complete")
    print(f"{'='*60}")
    print(f"Total: {len(progress['completed'])}")
    print(f"OK: {ok}, Skipped: {skip}, Failed: {fail}")
    print(f"Errors: {len(progress['errors'])}")
    print(f"Time: {elapsed/60:.1f} min")
    print(f"{'='*60}")

    if progress["errors"]:
        print("\nFirst 5 errors:")
        for k, v in list(progress["errors"].items())[:5]:
            print(f"  {k}: {v}")


if __name__ == "__main__":
    main()
