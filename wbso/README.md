# WBSO Uren Verantwoording

This folder contains all data and tooling for the WBSO (Wet Bevordering Speur- en Ontwikkelingswerk) S&O-uren verantwoording of Conduction B.V.

## Folder Structure

```
wbso/
├── README.md                    ← This file
├── wbso-2025.json               ← 2025 aanvragen, projecten, uren en classificatieresultaten
├── wbso-2026.json               ← 2026 aanvraag (pending toekenning)
├── classification-rules.json    ← Keyword-matching rules for commit → project mapping
├── classify_commits.py          ← Python classification script
├── wbso-2025-commits.csv        ← Final merged output: all classified commits for 2025
└── raw/                         ← Intermediate files (per-repo exports and classifications)
    ├── {repo}-commits.txt       ← Raw git log exports (pipe-delimited)
    └── {repo}-classified.csv    ← Per-repo classified CSVs
```

## Project Definitions

### 2025 Aanvraag 1 (jan–dec, 6.500 uur)

| Nummer | Titel | Uren |
|--------|-------|------|
| 14283/119/01 | Codestack Merging | 3.500 |
| 14283/119/02 | Isolated Search Engine | 3.000 |

### 2025 Aanvraag 2 — SO25022118 (mei–dec, 4.000 uur)

| Nummer | Titel | Uren |
|--------|-------|------|
| 14283/120/01 | Gedistribueerde Rechtenengine | 2.100 |
| 14283/120/02 | Fault Tolerant Task Orchestrator | 1.900 |

### 2026 Aanvraag (pending)

| Nummer | Titel | Status |
|--------|-------|--------|
| 14283/127/01 | MCP Tool (voormalig codestack) | Niet toegekend in 2025, heringediend |
| 14283/127/02 | Isolated Search Engine | Ingediend |
| 14283/127/03 | Fault Tolerant Task Orchestrator | Ingediend |
| 14283/127/04 | Gedistribueerde Rechtenengine | Ingediend |

## Methodology

### 1. Commit Export

All commits from 2025 were exported from 9 ConductionNL repositories using `git log`:

```bash
cd /path/to/repo
git log --all --after="2024-12-31" --before="2026-01-01" \
    --format="%H|%an|%aI|%s" > wbso/raw/{repo}-commits.txt
```

Format: `hash|author|date_iso|subject` (pipe-delimited to avoid issues with commas in commit messages).

**Repositories included:**

| Repository | Commits | Primary Project |
|------------|---------|-----------------|
| openregister | 5.222 | Codestack Merging (14283/119/01) |
| tilburg-woo-ui | 2.299 | Isolated Search Engine (14283/119/02) |
| openconnector | 1.718 | Fault Tolerant Task Orchestrator (14283/120/02) |
| opencatalogi | 968 | Isolated Search Engine (14283/119/02) |
| softwarecatalog | 523 | Isolated Search Engine (14283/119/02) |
| Softwarecatalogus | 410 | Isolated Search Engine (14283/119/02) |
| docudesk | 202 | Isolated Search Engine (14283/119/02) |
| zaakafhandelapp | 135 | Codestack Merging (14283/119/01) |
| larpingapp | 80 | Codestack Merging (14283/119/01) |
| **Totaal** | **11.557** | |

### 2. Classification Rules

Classification is defined in `classification-rules.json` and uses three layers:

1. **Repo-specific overrides** (`repo_overrides`) — Checked first. Certain keywords always map to a specific project for a given repo, regardless of generic rules. For example, "RBAC" in openregister always maps to Gedistribueerde Rechtenengine.

2. **Generic keyword matching** (`projects.{nr}.keywords_{high|medium|low}`) — Each project has three keyword tiers:
   - **high**: Core domain terms (e.g., "entity", "mapper", "doctrine" for Codestack Merging)
   - **medium**: Related terms (e.g., "refactor", "compatibility")
   - **low**: Peripheral terms (e.g., "build", "lint")

   The classifier tries high keywords across all projects first, then medium, then low.

3. **Repo defaults** (`repo_defaults`) — When no keyword matches, the commit falls back to the repo's primary project with `low` confidence.

### 3. Automated Commit Detection

Commits are flagged as `automated=true` when they match:
- Merge commits (`^Merge `)
- Version bumps (`^Bump .* version`)
- CI markers (`[skip ci]`)
- Deploy commits (`^deploy:`)
- Reverts (`^Revert `)
- Dependency chores (`^chore(deps)`)
- Bot authors (`github-actions[bot]`, `GitHub Action`)

Automated commits are still classified to a project but flagged separately in the CSV.

### 4. Classification Script

The `classify_commits.py` script reads raw commit exports, applies the classification logic, and writes per-repo CSVs. It was used for opencatalogi and softwarecatalog; the other 7 repos were classified using parallel subagents that applied the same rules from `classification-rules.json`.

```bash
python3 wbso/classify_commits.py
```

### 5. Merging

All per-repo classified CSVs (`raw/{repo}-classified.csv`) were merged into the final `wbso-2025-commits.csv`:

```bash
# Header from first file, then all data rows (skip headers)
head -1 wbso/raw/openregister-classified.csv > wbso/wbso-2025-commits.csv
for f in wbso/raw/*-classified.csv; do
    tail -n +2 "$f" >> wbso/wbso-2025-commits.csv
done
```

## Output Format

`wbso-2025-commits.csv` columns:

| Column | Description |
|--------|-------------|
| hash | Full commit SHA |
| author | Author name |
| date | ISO 8601 timestamp |
| repo | Repository name |
| subject | Commit message first line |
| project | WBSO project number (e.g., `14283/119/01`) |
| confidence | `high`, `medium`, or `low` |
| automated | `true` if CI/bot/merge/deploy commit |

## 2025 Results

| Project | Commits | Percentage |
|---------|---------|------------|
| 14283/119/01 — Codestack Merging | 3.600 | 31,1% |
| 14283/119/02 — Isolated Search Engine | 5.772 | 49,9% |
| 14283/120/01 — Gedistribueerde Rechtenengine | 602 | 5,2% |
| 14283/120/02 — Fault Tolerant Task Orchestrator | 1.583 | 13,7% |
| **Totaal** | **11.557** | **100%** |

Of these, 3.734 commits (32,3%) were flagged as automated and 7.823 (67,7%) as manual development work.

## Repeating for 2026

To generate the classification for 2026, follow these steps:

### Step 1: Update project definitions

Edit `wbso-2026.json` once the RVO toekenning is received:
- Fill in `totaal_uren` and per-project `uren`
- Update the `referentie` from `"pending"` to the actual S&O-verklaring nummer
- Add any new details from the beschikking

### Step 2: Update classification rules

Edit `classification-rules.json`:
- Update project numbers if they changed (2025 used `14283/119/xx` and `14283/120/xx`, 2026 uses `14283/127/xx`)
- Review and update keywords based on the actual work done in 2026
- Add any new repositories to `repo_defaults` and `repo_overrides`
- Review `skip_patterns` and `auto_commits` for any new automated patterns

### Step 3: Export commits

For each repository, export 2026 commits:

```bash
YEAR=2026
for repo in openregister openconnector opencatalogi softwarecatalog \
            Softwarecatalogus tilburg-woo-ui docudesk zaakafhandelapp larpingapp; do
    cd /path/to/$repo
    git log --all --after="$((YEAR-1))-12-31" --before="$((YEAR+1))-01-01" \
        --format="%H|%an|%aI|%s" > ../wbso/raw/${repo}-commits.txt
    cd ..
done
```

### Step 4: Update and run the classifier

Update `classify_commits.py`:
- Update the `PROJECTS` dict with 2026 project numbers and keywords
- Update `REPO_OVERRIDES` and `REPO_DEFAULTS` to use 2026 project numbers
- Add any new repos to the `process_repo()` calls at the bottom

Then run:

```bash
python3 wbso/classify_commits.py
```

Alternatively, use Claude Code with parallel subagents for the classification (as was done for 2025). Provide the `classification-rules.json` as shared context so all agents use the same rules.

### Step 5: Merge results

```bash
head -1 wbso/raw/openregister-classified.csv > wbso/wbso-2026-commits.csv
for f in wbso/raw/*-classified.csv; do
    tail -n +2 "$f" >> wbso/wbso-2026-commits.csv
done
```

### Step 6: Update summary JSON

Update `wbso-2026.json` with the classification results (commit counts per project, percentages, totals) — similar to the `commit_classificatie` section in `wbso-2025.json`.

## Notes

- The `raw/` folder is gitignored to keep the repository clean. Only the final merged CSV and configuration files are committed.
- The classification is keyword-based and not perfect. Manual review of `low` confidence commits is recommended, especially for commits that fell through to the repo default.
- Commits can be reclassified by adjusting rules in `classification-rules.json` and re-running the pipeline.
- The original analysis was performed on 2025-02-25 by Claude Code with parallel subagents for performance.
