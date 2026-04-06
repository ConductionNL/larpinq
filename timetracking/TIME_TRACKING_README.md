# Developer Time Tracking Tool

This tool helps developers track their working time based on git commit history across multiple repositories. It automatically generates time tracking reports by analyzing commits and categorizing them into normal working hours and overtime.

## Overview

The time tracking system analyzes git commits to:
- Track normal working hours (daytime commits on weekdays)
- Track overtime hours (evening commits and weekend work)
- Generate CSV files that can be opened in Excel
- Provide summary statistics
- Support multiple repositories and git handles

## Quick Start

### 1. Setup the Time Tracking Structure

First, run the setup script to create the folder structure:

```bash
python3 setup_time_tracking.py
```

This will:
- Create a `timetracking/` directory (excluded from git)
- Create folders for each git handle found in repositories
- Update `.gitignore` to exclude time tracking files

### 2. Generate Time Tracking Files

Generate time tracking files for specific git handles:

```bash
python3 generate_time_tracking.py --git-handles "rubenvdlinde" "Ruben van der Linde" "ruben@conduction.nl"
```

Or auto-detect all git handles from repositories:

```bash
python3 generate_time_tracking.py --auto-detect-handles
```

### 3. Customize Date Range

Specify custom date ranges:

```bash
python3 generate_time_tracking.py --start-date 2024-01-01 --end-date 2024-12-31 --git-handles "rubenvdlinde"
```

## Usage

### Basic Usage

```bash
# Track specific git handles for current year
python3 generate_time_tracking.py --git-handles "handle1" "handle2"

# Track all detected handles
python3 generate_time_tracking.py --auto-detect-handles

# Track with custom date range
python3 generate_time_tracking.py --start-date 2024-10-01 --end-date 2024-12-31 --git-handles "rubenvdlinde"
```

### Advanced Usage

```bash
# Specify custom repositories
python3 generate_time_tracking.py --repos /path/to/repo1 /path/to/repo2 --git-handles "handle"

# Custom output directory
python3 generate_time_tracking.py --output-dir /custom/path --git-handles "handle"
```

## Command Line Options

- `--start-date YYYY-MM-DD`: Start date for tracking (default: Jan 1 of current year)
- `--end-date YYYY-MM-DD`: End date for tracking (default: Dec 31 of current year)
- `--git-handles HANDLE1 HANDLE2 ...`: Git handles to track (name or email)
- `--repos PATH1 PATH2 ...`: Repository paths (default: ConductionNL repos in apps-extra)
- `--output-dir PATH`: Output directory (default: timetracking/{git_handle}/)
- `--auto-detect-handles`: Auto-detect all git handles from repositories

## Output Files

For each git handle, the tool generates three files in `timetracking/{git_handle}/`:

1. **`{git_handle}_normal_time.csv`**
   - Normal working hours (daytime commits on weekdays)
   - Columns: Date, Day, Weekend, Worked, Hours, Commits, Start Time, End Time, Repositories, Work Summary

2. **`{git_handle}_overtime.csv`**
   - Overtime hours (evening commits 17:00-02:00 and weekend work)
   - Same columns as normal time file

3. **`{git_handle}_summary.txt`**
   - Summary statistics for both normal time and overtime
   - Includes totals, averages, and breakdowns

## How It Works

### Time Classification

- **Normal Time**: Commits made during normal workday hours
  - Weekdays (Monday-Friday)
  - Between 08:00 and 18:00 (Amsterdam/CET timezone)
  
- **Overtime**: Commits made during:
  - Before 08:00 on weekdays
  - After 18:00 on weekdays
  - Weekend days (Saturday and Sunday)
  - All hours on weekends

### Hours Calculation Method

**IMPORTANT: Hours are estimated from git commit timestamps, NOT from lines of code changed.**

The calculation method uses three factors:

1. **Base Hours**: Minimum session duration
   - Normal time: 2 hours (accounts for setup, context switching)
   - Overtime: 3 hours (evening/weekend sessions are typically longer)

2. **Time Span**: Actual time between first and last commit
   - If commits span more than base hours, the actual time difference is added
   - Maximum: 8 hours for normal time, 6 hours for overtime

3. **Commit Frequency**: More commits indicate more intensive work
   - +0.5 hours per 10 commits (up to maximum)
   - +0.5 hours per 20 commits (additional bonus)

**Example:**
- Session with 15 commits spanning 4 hours
- Base: 2 hours
- Time span: 4 hours (exceeds base, so use 4 hours)
- Commit bonus: +0.5 hours (15 commits = 1 bonus)
- **Total: 4.5 hours**

### Expected Working Hours

The tool calculates expected working hours based on:
- Standard work week: 40 hours
- Holiday weeks per year: 5 weeks
- Working weeks per year: 47 weeks (52 - 5)
- Expected hours per year: 1,880 hours (47 weeks × 40 hours)

This allows comparison between:
- **Expected hours**: What should be worked (based on standard work week)
- **Tracked hours**: What was tracked via commits
- **Untracked hours**: Difference (meetings, planning, code review, etc.)

### Limitations

The calculation method has limitations:

- **Does NOT account for:**
  - Time spent without committing (meetings, planning, thinking)
  - Code review time
  - Documentation writing
  - Testing without commits
  - Other non-coding work

- **May underestimate** for developers who:
  - Commit infrequently
  - Work in long sessions before committing
  - Do significant planning/design work

- **May overestimate** for developers who:
  - Make many small commits
  - Commit frequently during debugging
  - Use automated commit tools

**Important**: This tool is meant to **help** developers track time, not replace manual time tracking. Always review and adjust hours manually for accuracy.

### Repository Detection

The tool automatically detects which repository each commit belongs to, allowing you to see:
- Which repositories you worked on each day
- Distribution of work across repositories

## Supported Repositories

By default, the tool scans these ConductionNL repositories:
- `openregister`
- `opencatalogi`
- `openconnector`
- `softwarecatalog`

You can specify custom repositories using the `--repos` option.

## Privacy and Git Exclusion

- The `timetracking/` folder is automatically excluded from git via `.gitignore`
- Time tracking files are personal and not committed to the repository
- Each developer can track their own time independently

## Examples

### Track Your Own Time for Current Year

```bash
python3 generate_time_tracking.py --git-handles "rubenvdlinde" "Ruben van der Linde" "ruben@conduction.nl"
```

### Track Specific Period

```bash
python3 generate_time_tracking.py \
  --start-date 2024-10-01 \
  --end-date 2024-12-31 \
  --git-handles "rubenvdlinde"
```

### Track All Team Members

```bash
python3 generate_time_tracking.py --auto-detect-handles
```

## Tips

1. **Regular Updates**: Run the script regularly (weekly/monthly) to keep tracking up to date
2. **Multiple Handles**: Include all variations of your git handle (name, email, username)
3. **Date Ranges**: Use specific date ranges for monthly/quarterly reports
4. **Excel Formatting**: Open CSV files in Excel and format as needed (colors, filters, etc.)

## Troubleshooting

### No Commits Found

- Verify git handles match exactly (case-sensitive)
- Check that repositories exist and are accessible
- Ensure date range includes commits

### Missing Repositories

- Use `--repos` to specify custom repository paths
- Verify repository paths are correct and accessible

### Incorrect Hours

- Hours are estimates based on commit patterns
- Adjust manually in Excel if needed for accuracy
- Review commit times to verify calculations

## Notes

- This tool is meant to **help** developers track time, not replace manual time tracking
- Hours are estimates based on git commit patterns
- Always review and adjust hours manually for accuracy
- The tool considers commits from all branches (`--all` flag)
- Merge commits are included in the tracking

## Support

For issues or questions, check:
- Git commit history to verify commits are being detected
- Repository paths are correct
- Git handles match exactly (including case)

