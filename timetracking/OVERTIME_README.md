# Overtime Tracking from Git Commits

This directory contains overtime tracking files generated from your git commit history on the openregister project.

## Files Created

1. **overtime_tracking.csv** - Main tracking file with all evening work sessions
2. **overtime_summary.txt** - Summary statistics
3. **convert_to_excel.py** - Script to convert CSV to Excel (requires openpyxl)

## How to Use

### Opening in Excel

The CSV file can be opened directly in Excel:
- Double-click `overtime_tracking.csv` to open in Excel
- Excel will automatically format the columns
- You can save it as `.xlsx` format from Excel's File > Save As

### Converting to Excel Format

If you want a properly formatted Excel file with colors and formatting:

1. Install openpyxl:
   ```bash
   pip install openpyxl
   # or
   python3 -m pip install --user openpyxl
   ```

2. Run the conversion script:
   ```bash
   python3 convert_to_excel.py
   ```

This will create `overtime_tracking.xlsx` with:
- Colored headers
- Green highlighting for worked evenings
- Summary statistics
- Proper column widths

## Summary Statistics

Based on your git commits from October 1, 2024 to December 3, 2025:

- **Total hours tracked**: 242.3 hours
- **Total evenings worked**: 77 evenings
- **Weekday hours**: 160.1 hours (50 evenings)
- **Weekend hours**: 82.2 hours (27 evenings)
- **Average hours per evening**: 3.1 hours
- **Average hours per week**: 4.0 hours

## How It Works

The script analyzes your git commits and:
- Identifies evening work sessions (commits between 20:00-02:00)
- Groups commits by evening date
- Estimates hours worked based on commit timespan and frequency
- Tracks weekday vs weekend work
- Provides summaries of work done each evening

## Notes

- Evening work is defined as commits made between 20:00 and 02:00 (next day)
- Hours are estimated based on commit timespan (minimum 3 hours per evening)
- Weekends are Saturday and Sunday
- Days with no evening commits are marked as "No" for weekday evenings

## Regenerating the Files

To regenerate the tracking files with updated data:

```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/openregister
git log --author="rubenvdlinde" --since="2024-10-01" --pretty=format:"%H|%ai|%s" --date=iso > /tmp/git_commits.txt
cd ..
python3 /tmp/generate_overtime_improved.py
```


