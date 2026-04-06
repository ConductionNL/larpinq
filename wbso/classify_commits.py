#!/usr/bin/env python3
"""
WBSO commit classifier for opencatalogi and softwarecatalog repositories.
Reads raw commit files, classifies each commit, and writes CSV output.
"""

import csv
import re
import sys


# ── Skip / automated patterns ──────────────────────────────────────────────
SKIP_PATTERNS = [
    re.compile(r"^Merge ", re.IGNORECASE),
    re.compile(r"^Bump .* version", re.IGNORECASE),
    re.compile(r"\[skip ci\]", re.IGNORECASE),
    re.compile(r"^deploy:", re.IGNORECASE),
    re.compile(r"^Revert ", re.IGNORECASE),
    re.compile(r"^chore\(deps\)", re.IGNORECASE),
]

AUTO_AUTHOR_PATTERNS = [
    re.compile(r"GitHub Action", re.IGNORECASE),
    re.compile(r"github-actions\[bot\]", re.IGNORECASE),
]

AUTO_SUBJECT_PATTERNS = [
    re.compile(r"Bump.*version.*skip ci", re.IGNORECASE),
    re.compile(r"^deploy:", re.IGNORECASE),
]


def is_automated(author: str, subject: str) -> bool:
    """Check if commit is automated (CI/CD, bots, version bumps, deploys, merges, reverts)."""
    for p in SKIP_PATTERNS:
        if p.search(subject):
            return True
    for p in AUTO_AUTHOR_PATTERNS:
        if p.search(author):
            return True
    for p in AUTO_SUBJECT_PATTERNS:
        if p.search(subject):
            return True
    return False


# ── Keyword lists per project ──────────────────────────────────────────────
PROJECTS = {
    "14283/119/01": {
        "high": [
            "entity", "mapper", "doctrine", "symfony", "migration", "wrapping",
            "bridge", "repair step", "DI container", "dependency injection",
            "service container", "object store", "objectservice",
            "nextcloud app structure", "info.xml", "appinfo", "composer", "autoload",
        ],
        "medium": [
            "refactor", "restructure", "rewrite", "port", "convert", "adapt",
            "compatibility", "upgrade nextcloud", "nextcloud 3",
        ],
        "low": [
            "build", "webpack", "vue-loader", "npm", "node_modules", "lint",
            "eslint", "phpcs", "code cleanup", "code quality",
        ],
    },
    "14283/119/02": {
        "high": [
            "search", "solr", "elastic", "index", "query", "facet", "filter",
            "federation", "catalog", "ETL", "data source", "mapping", "import",
            "export", "AMEF", "AMEFF", "synchroniz", "sync", "data transform",
            "listing",
        ],
        "medium": [
            "view", "dashboard", "overview", "browse", "navigation", "sitemap",
            "robots", "SEO", "schema", "register", "data model",
        ],
        "low": [
            "API", "endpoint", "route", "CORS", "REST", "OAS", "openapi",
        ],
    },
    "14283/120/01": {
        "high": [
            "RBAC", "rights", "permission", "role", "authoriz", "autoris",
            "access control", "multi-tenant", "organisation", "organization",
            "security", "cache", "invalidat",
        ],
        "medium": [
            "user", "group", "admin", "login", "session", "token", "JWT",
            "brute force", "rate limit", "audit",
        ],
        "low": [
            "config", "settings", "middleware",
        ],
    },
    "14283/120/02": {
        "high": [
            "job", "task", "cron", "queue", "worker", "orchestrat", "fault",
            "retry", "lock", "deadlock", "idempotent", "async", "background",
            "scheduler",
        ],
        "medium": [
            "error", "exception", "recover", "rollback", "transaction",
            "timeout", "process", "handler", "event", "dispatch", "webhook",
            "notification",
        ],
        "low": [
            "log", "debug", "monitor", "status", "health",
        ],
    },
}


# ── Per-repo override rules (checked FIRST, before generic keywords) ──────
# Each override is (compiled_regex, project_code)

def _build_overrides(rules: dict) -> list:
    """Build list of (compiled_pattern, project) from override dict."""
    result = []
    for pattern_str, project in rules.items():
        result.append((re.compile(pattern_str, re.IGNORECASE), project))
    return result


REPO_OVERRIDES = {
    "opencatalogi": _build_overrides({
        r"rbac|rights|permission|authoriz|autoris|multi.?tenan|organisation": "14283/120/01",
        r"entity|mapper|object.?store|repair.?step|\bDI\b|dependency.?inject|composer|autoload|info\.xml|appinfo": "14283/119/01",
        r"federation|search|catalog|publication|listing|slug|glossary": "14283/119/02",
        r"\bjob\b|cron|background|\bsync\b|queue|\basync\b|worker|scheduler": "14283/120/02",
    }),
    "softwarecatalog": _build_overrides({
        r"rbac|rights|permission|authoriz|autoris|\bauth\b|organisation": "14283/120/01",
        r"entity|mapper|repair.?step|\bDI\b|dependency.?inject|composer|autoload|info\.xml|appinfo": "14283/119/01",
        r"search|view|module|standaard|organisatie|export|AMEF|listing|import|filter|query": "14283/119/02",
        r"\bjob\b|cron|background|queue|\basync\b|worker|scheduler": "14283/120/02",
    }),
}

REPO_DEFAULTS = {
    "opencatalogi": "14283/119/02",
    "softwarecatalog": "14283/119/02",
}


def classify_commit(subject: str, repo: str) -> tuple:
    """
    Returns (project_code, confidence).
    confidence: "high", "medium", "low"
    """
    subj_lower = subject.lower()

    # 1. Check repo-specific overrides first (these map topics to projects)
    overrides = REPO_OVERRIDES.get(repo, [])
    for pattern, project in overrides:
        if pattern.search(subject):
            return project, "high"

    # 2. Generic keyword matching across all projects
    #    Try high keywords first across ALL projects, then medium, then low
    for confidence_level in ("high", "medium", "low"):
        best_match = None
        for proj_code, kw_dict in PROJECTS.items():
            keywords = kw_dict.get(confidence_level, [])
            for kw in keywords:
                if kw.lower() in subj_lower:
                    # Return the first project that has a keyword match at this level
                    # But prioritize repo-specific defaults if there are ties
                    if best_match is None:
                        best_match = (proj_code, confidence_level)
                    # If this project matches the repo default, prefer it
                    if proj_code == REPO_DEFAULTS.get(repo):
                        return proj_code, confidence_level
        if best_match:
            return best_match

    # 3. Repo default with low confidence
    default_proj = REPO_DEFAULTS.get(repo, "14283/119/02")
    return default_proj, "low"


def process_repo(input_path: str, output_path: str, repo: str):
    """Read commits from input file, classify, write CSV."""
    rows = []

    with open(input_path, "r", encoding="utf-8") as f:
        for line_num, line in enumerate(f, 1):
            line = line.strip()
            if not line:
                continue

            parts = line.split("|", 3)
            if len(parts) < 4:
                print(f"WARNING: Skipping malformed line {line_num} in {repo}: {line[:80]}", file=sys.stderr)
                continue

            hash_val, author, date, subject = parts[0], parts[1], parts[2], parts[3]

            # Determine if automated
            automated = is_automated(author, subject)

            # Classify
            project, confidence = classify_commit(subject, repo)

            # If automated, keep the classification but mark automated=true
            automated_str = "true" if automated else "false"

            rows.append([hash_val, author, date, repo, subject, project, confidence, automated_str])

    # Write CSV
    with open(output_path, "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(["hash", "author", "date", "repo", "subject", "project", "confidence", "automated"])
        for row in rows:
            writer.writerow(row)

    # Stats
    total = len(rows)
    auto_count = sum(1 for r in rows if r[7] == "true")
    dev_count = total - auto_count

    proj_counts = {}
    conf_counts = {"high": 0, "medium": 0, "low": 0}
    for r in rows:
        proj = r[5]
        proj_counts[proj] = proj_counts.get(proj, 0) + 1
        conf_counts[r[6]] += 1

    print(f"\n{'='*60}")
    print(f"Repository: {repo}")
    print(f"{'='*60}")
    print(f"Total commits:     {total}")
    print(f"  Automated:       {auto_count}")
    print(f"  Development:     {dev_count}")
    print(f"\nBy project:")
    for proj in sorted(proj_counts.keys()):
        print(f"  {proj}: {proj_counts[proj]}")
    print(f"\nBy confidence:")
    for conf in ("high", "medium", "low"):
        print(f"  {conf}: {conf_counts[conf]}")
    print(f"\nOutput: {output_path}")


if __name__ == "__main__":
    base = "/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/wbso/raw"

    process_repo(
        f"{base}/opencatalogi-commits.txt",
        f"{base}/opencatalogi-classified.csv",
        "opencatalogi",
    )

    process_repo(
        f"{base}/softwarecatalog-commits.txt",
        f"{base}/softwarecatalog-classified.csv",
        "softwarecatalog",
    )
