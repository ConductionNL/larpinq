#!/usr/bin/env python3
"""
Generate company-wide time tracking overview.
Aggregates data from all employee time tracking files.
"""

import csv
import re
from pathlib import Path
from collections import defaultdict
from datetime import datetime, timedelta

def calculate_expected_working_hours(start_date, end_date, hours_per_week=40, weeks_holiday=5):
    """
    Calculate expected working hours for a period.
    
    Assumptions:
    - Standard work week: 40 hours (default)
    - Holiday weeks per year: 5 weeks (default)
    - Working weeks per year: 52 - 5 = 47 weeks
    """
    total_days = (end_date - start_date).days + 1
    total_weeks = total_days / 7.0
    working_weeks_per_year = 52 - weeks_holiday
    working_weeks = total_weeks * (working_weeks_per_year / 52.0)
    expected_hours = working_weeks * hours_per_week
    return expected_hours

def get_employee_mapping():
    """Return mapping of git handle variations to canonical employee names."""
    return {
        'rubenvdlinde': 'Ruben van der Linde',
        'ruben@conduction.nl': 'Ruben van der Linde',
        'Ruben van der Linde': 'Ruben van der Linde',
        'rubenvdlinde@gmail.com': 'Ruben van der Linde',
        'rubenvdlinde@users.noreply.github.com': 'Ruben van der Linde',
        'ruben': 'Ruben van der Linde',
        '43807324+remko48': 'Remko Huisman',
        '43807324+remko48@users.noreply.github.com': 'Remko Huisman',
        'remko48': 'Remko Huisman',
        'remko@conduction.nl': 'Remko Huisman',
        'Remko Huisman': 'Remko Huisman',
        'Remko': 'Remko Huisman',
        '57346398+bbrands02': 'Barry Brands',
        '57346398+bbrands02@users.noreply.github.com': 'Barry Brands',
        'bbrands02': 'Barry Brands',
        'barrybrands02': 'Barry Brands',
        'barrybrands02@hotmail.com': 'Barry Brands',
        'Barry Brands': 'Barry Brands',
        '66728126+MWest2020': 'Mark West',
        '66728126+MWest2020@users.noreply.github.com': 'Mark West',
        'MWest2020': 'Mark West',
        'markwesterweel': 'Mark West',
        'markwesterweel@conduction.nl': 'Mark West',
        'Mark West': 'Mark West',
        'Mark westerweel': 'Mark West',
        'Mwest2020': 'Mark West',
        'mwesterweel': 'Mark West',
        'mwesterweel@hotmail.com': 'Mark West',
        '87539434+CBibop12': 'Alex Rekachynskyi',
        '87539434+CBibop12@users.noreply.github.com': 'Alex Rekachynskyi',
        'Alex': 'Alex Rekachynskyi',
        'Alex Rekachynskyi': 'Alex Rekachynskyi',
        'phone.070718@gmail.com': 'Alex Rekachynskyi',
        'phone.070718': 'Alex Rekachynskyi',
        'Robert Zondervan': 'Robert Zondervan',
        'robert@conduction.nl': 'Robert Zondervan',
        'rjzondervan': 'Robert Zondervan',
        'rjzondervan@gmail.com': 'Robert Zondervan',
        'rjzondervan@users.noreply.github.com': 'Robert Zondervan',
        'robert': 'Robert Zondervan',
        'Thijn': 'Thijn Douwma',
        'Thijn Douwma': 'Thijn Douwma',
        'thijn@conduction.nl': 'Thijn Douwma',
        'Ralkey': 'Thijn Douwma',
        'Ralkey@outlook.com': 'Thijn Douwma',
        'SudoThijn': 'Thijn Douwma',
        'Wilco Louwerse': 'Wilco Louwerse',
        'wilco@conduction.nl': 'Wilco Louwerse',
        'wilco@louwerse.com': 'Wilco Louwerse',
        'wilco': 'Wilco Louwerse',
        'Matthias Oliveiro': 'Matthias Oliveiro',
        'matthias@conduction.nl': 'Matthias Oliveiro',
        'matthiasoliveiro@gmail.com': 'Matthias Oliveiro',
        'matthiasoliveiro': 'Matthias Oliveiro',
        'matthias': 'Matthias Oliveiro',
    }

def parse_csv_file(csv_file):
    """Parse a CSV file and return rows with data."""
    rows = []
    try:
        with open(csv_file, 'r', encoding='utf-8') as f:
            reader = csv.DictReader(f)
            for row in reader:
                rows.append(row)
    except Exception as e:
        print(f"Warning: Could not parse {csv_file}: {e}")
    return rows

def get_month_from_date(date_str):
    """Extract month from date string (YYYY-MM-DD)."""
    try:
        return date_str[:7]  # YYYY-MM
    except:
        return None

def get_week_from_date(date_str):
    """Extract week from date string."""
    try:
        dt = datetime.strptime(date_str, '%Y-%m-%d')
        year, week, _ = dt.isocalendar()
        return f"{year}-W{week:02d}"
    except:
        return None

def generate_company_overview():
    """Generate comprehensive company-wide overview."""
    base = Path('timetracking')
    
    # Determine date range from files
    start_date = None
    end_date = None
    for csv_file in base.rglob('*_normal_time.csv'):
        rows = parse_csv_file(csv_file)
        for row in rows:
            date_str = row.get('Date', '')
            if date_str:
                try:
                    date = datetime.strptime(date_str, '%Y-%m-%d').date()
                    if start_date is None or date < start_date:
                        start_date = date
                    if end_date is None or date > end_date:
                        end_date = date
                except:
                    pass
    
    if not start_date or not end_date:
        # Default to current year if can't determine
        start_date = datetime(datetime.now().year, 1, 1).date()
        end_date = datetime(datetime.now().year, 12, 31).date()
    
    # Data structures
    employee_totals = defaultdict(lambda: {'normal': 0.0, 'overtime': 0.0, 'total': 0.0, 'days': 0})
    monthly_totals = defaultdict(lambda: {'normal': 0.0, 'overtime': 0.0, 'total': 0.0})
    weekly_totals = defaultdict(lambda: {'normal': 0.0, 'overtime': 0.0, 'total': 0.0})
    repository_totals = defaultdict(lambda: {'normal': 0.0, 'overtime': 0.0, 'total': 0.0})
    employee_mapping = get_employee_mapping()
    
    # Process all CSV files
    for csv_file in base.rglob('*_normal_time.csv'):
        # Extract employee name from path
        folder_name = csv_file.parent.name
        employee_name = employee_mapping.get(folder_name, folder_name)
        
        # Skip bots and empty
        if any(bot in employee_name.lower() for bot in ['bot', 'github action', 'cursor agent', 'action@github']):
            continue
        if not employee_name or employee_name.strip() == '':
            continue
        
        rows = parse_csv_file(csv_file)
        for row in rows:
            if row.get('Worked') == 'Yes':
                hours = float(row.get('Hours', 0))
                employee_totals[employee_name]['normal'] += hours
                employee_totals[employee_name]['total'] += hours
                
                # Monthly breakdown
                month = get_month_from_date(row.get('Date', ''))
                if month:
                    monthly_totals[month]['normal'] += hours
                    monthly_totals[month]['total'] += hours
                
                # Weekly breakdown
                week = get_week_from_date(row.get('Date', ''))
                if week:
                    weekly_totals[week]['normal'] += hours
                    weekly_totals[week]['total'] += hours
                
                # Repository breakdown
                repos = row.get('Repositories', '')
                if repos:
                    for repo in repos.split(','):
                        repo = repo.strip()
                        if repo:
                            repository_totals[repo]['normal'] += hours
                            repository_totals[repo]['total'] += hours
                
                employee_totals[employee_name]['days'] += 1
    
    for csv_file in base.rglob('*_overtime.csv'):
        folder_name = csv_file.parent.name
        employee_name = employee_mapping.get(folder_name, folder_name)
        
        if any(bot in employee_name.lower() for bot in ['bot', 'github action', 'cursor agent', 'action@github']):
            continue
        if not employee_name or employee_name.strip() == '':
            continue
        
        rows = parse_csv_file(csv_file)
        for row in rows:
            if row.get('Worked') == 'Yes':
                hours = float(row.get('Hours', 0))
                employee_totals[employee_name]['overtime'] += hours
                employee_totals[employee_name]['total'] += hours
                
                # Monthly breakdown
                month = get_month_from_date(row.get('Date', ''))
                if month:
                    monthly_totals[month]['overtime'] += hours
                    monthly_totals[month]['total'] += hours
                
                # Weekly breakdown
                week = get_week_from_date(row.get('Date', ''))
                if week:
                    weekly_totals[week]['overtime'] += hours
                    weekly_totals[week]['total'] += hours
                
                # Repository breakdown
                repos = row.get('Repositories', '')
                if repos:
                    for repo in repos.split(','):
                        repo = repo.strip()
                        if repo:
                            repository_totals[repo]['overtime'] += hours
                            repository_totals[repo]['total'] += hours
    
    # Generate report
    output_file = base / 'COMPANY_OVERVIEW.txt'
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write("=" * 80 + "\n")
        f.write("COMPANY-WIDE TIME TRACKING OVERVIEW\n")
        f.write("=" * 80 + "\n\n")
        
        # Overall totals
        total_normal = sum(e['normal'] for e in employee_totals.values())
        total_overtime = sum(e['overtime'] for e in employee_totals.values())
        total_all = sum(e['total'] for e in employee_totals.values())
        total_days = sum(e['days'] for e in employee_totals.values())
        num_employees = len([e for e in employee_totals.values() if e['total'] > 0])
        
        # Calculate expected hours
        expected_hours_per_employee = calculate_expected_working_hours(start_date, end_date)
        total_expected_hours = expected_hours_per_employee * num_employees
        untracked_hours = max(0, total_expected_hours - total_normal)
        coverage_percentage = (total_normal / total_expected_hours * 100) if total_expected_hours > 0 else 0
        
        f.write("OVERALL TOTALS\n")
        f.write("-" * 80 + "\n")
        f.write(f"Total Normal Hours:     {total_normal:>10.1f}\n")
        f.write(f"Total Overtime Hours:  {total_overtime:>10.1f}\n")
        f.write(f"Total Hours:            {total_all:>10.1f}\n")
        f.write(f"Total Days Worked:     {total_days:>10}\n")
        f.write(f"Average Hours/Day:     {total_all/total_days if total_days > 0 else 0:>10.1f}\n")
        f.write(f"Average Hours/Week:     {total_all/52:>10.1f}\n")
        f.write("\n")
        f.write("EXPECTED VS TRACKED (Normal Hours Only)\n")
        f.write("-" * 80 + "\n")
        f.write(f"Number of Employees:    {num_employees:>10}\n")
        f.write(f"Expected Hours/Employee (40h/week, 5 weeks holiday): {expected_hours_per_employee:>10.1f}\n")
        f.write(f"Total Expected Hours:   {total_expected_hours:>10.1f}\n")
        f.write(f"Tracked Hours (from commits): {total_normal:>10.1f}\n")
        f.write(f"Hours Not Accounted For: {untracked_hours:>10.1f}\n")
        f.write(f"Coverage:               {coverage_percentage:>10.1f}%\n")
        f.write("\n")
        f.write("HOW HOURS ARE CALCULATED:\n")
        f.write("-" * 80 + "\n")
        f.write("Hours are estimated from git commit timestamps, NOT from lines of code.\n\n")
        f.write("Timezone: Amsterdam/CET (UTC+1/+2)\n")
        f.write("Normal workday: 08:00-18:00 on weekdays\n")
        f.write("Overtime: Before 08:00, after 18:00, or weekends\n\n")
        f.write("Method:\n")
        f.write("1. Base hours: Minimum session duration (2h normal, 3h overtime)\n")
        f.write("2. Time span: Actual time between first and last commit\n")
        f.write("3. Commit frequency: +0.5h per 10 commits (indicates intensive work)\n\n")
        f.write("Limitations:\n")
        f.write("- Does not account for time without commits (meetings, planning, etc.)\n")
        f.write("- Does not account for code review, documentation, or testing\n")
        f.write("- May underestimate for developers who commit infrequently\n")
        f.write("- May overestimate for developers who make many small commits\n")
        f.write("\n")
        
        # Employee breakdown
        f.write("EMPLOYEE BREAKDOWN\n")
        f.write("-" * 80 + "\n")
        f.write(f"{'Employee':<30} {'Normal':>12} {'Overtime':>12} {'Total':>12} {'Days':>8}\n")
        f.write("-" * 80 + "\n")
        
        sorted_employees = sorted(employee_totals.items(), key=lambda x: x[1]['total'], reverse=True)
        for employee, data in sorted_employees:
            if data['total'] > 0:
                f.write(f"{employee:<30} {data['normal']:>12.1f} {data['overtime']:>12.1f} {data['total']:>12.1f} {data['days']:>8}\n")
        
        f.write("-" * 80 + "\n")
        f.write(f"{'TOTAL':<30} {total_normal:>12.1f} {total_overtime:>12.1f} {total_all:>12.1f} {total_days:>8}\n")
        f.write("\n")
        
        # Monthly breakdown
        f.write("MONTHLY BREAKDOWN\n")
        f.write("-" * 80 + "\n")
        f.write(f"{'Month':<15} {'Normal':>12} {'Overtime':>12} {'Total':>12}\n")
        f.write("-" * 80 + "\n")
        
        for month in sorted(monthly_totals.keys()):
            data = monthly_totals[month]
            f.write(f"{month:<15} {data['normal']:>12.1f} {data['overtime']:>12.1f} {data['total']:>12.1f}\n")
        
        f.write("-" * 80 + "\n")
        f.write(f"{'TOTAL':<15} {total_normal:>12.1f} {total_overtime:>12.1f} {total_all:>12.1f}\n")
        f.write("\n")
        
        # Repository breakdown
        f.write("REPOSITORY BREAKDOWN\n")
        f.write("-" * 80 + "\n")
        f.write(f"{'Repository':<30} {'Normal':>12} {'Overtime':>12} {'Total':>12}\n")
        f.write("-" * 80 + "\n")
        
        sorted_repos = sorted(repository_totals.items(), key=lambda x: x[1]['total'], reverse=True)
        for repo, data in sorted_repos:
            if data['total'] > 0:
                f.write(f"{repo:<30} {data['normal']:>12.1f} {data['overtime']:>12.1f} {data['total']:>12.1f}\n")
        
        repo_total_normal = sum(r['normal'] for r in repository_totals.values())
        repo_total_overtime = sum(r['overtime'] for r in repository_totals.values())
        repo_total_all = sum(r['total'] for r in repository_totals.values())
        f.write("-" * 80 + "\n")
        f.write(f"{'TOTAL':<30} {repo_total_normal:>12.1f} {repo_total_overtime:>12.1f} {repo_total_all:>12.1f}\n")
        f.write("\n")
        
        # Weekly breakdown (last 12 weeks)
        f.write("WEEKLY BREAKDOWN (Last 12 Weeks)\n")
        f.write("-" * 80 + "\n")
        f.write(f"{'Week':<15} {'Normal':>12} {'Overtime':>12} {'Total':>12}\n")
        f.write("-" * 80 + "\n")
        
        sorted_weeks = sorted(weekly_totals.keys(), reverse=True)[:12]
        for week in sorted_weeks:
            data = weekly_totals[week]
            f.write(f"{week:<15} {data['normal']:>12.1f} {data['overtime']:>12.1f} {data['total']:>12.1f}\n")
        
        f.write("\n")
        
        # Statistics
        f.write("STATISTICS\n")
        f.write("-" * 80 + "\n")
        if len(employee_totals) > 0:
            avg_hours_per_employee = total_all / len([e for e in employee_totals.values() if e['total'] > 0])
            f.write(f"Average hours per employee: {avg_hours_per_employee:.1f}\n")
        
        if len(monthly_totals) > 0:
            avg_monthly = total_all / len(monthly_totals)
            f.write(f"Average hours per month: {avg_monthly:.1f}\n")
        
        overtime_percentage = (total_overtime / total_all * 100) if total_all > 0 else 0
        f.write(f"Overtime percentage: {overtime_percentage:.1f}%\n")
        
        f.write("\n")
        f.write("=" * 80 + "\n")
        f.write(f"Report generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
        f.write("=" * 80 + "\n")
    
    # Also generate CSV for easy import into Excel
    csv_output = base / 'COMPANY_OVERVIEW.csv'
    with open(csv_output, 'w', newline='', encoding='utf-8') as f:
        writer = csv.writer(f)
        writer.writerow(['Category', 'Type', 'Value'])
        
        # Overall totals
        writer.writerow(['Overall', 'Normal Hours', total_normal])
        writer.writerow(['Overall', 'Overtime Hours', total_overtime])
        writer.writerow(['Overall', 'Total Hours', total_all])
        writer.writerow(['Overall', 'Total Days', total_days])
        
        # Employee breakdown
        writer.writerow([])
        writer.writerow(['Employee', 'Normal Hours', 'Overtime Hours', 'Total Hours', 'Days'])
        for employee, data in sorted_employees:
            if data['total'] > 0:
                writer.writerow([employee, data['normal'], data['overtime'], data['total'], data['days']])
        
        # Monthly breakdown
        writer.writerow([])
        writer.writerow(['Month', 'Normal Hours', 'Overtime Hours', 'Total Hours'])
        for month in sorted(monthly_totals.keys()):
            data = monthly_totals[month]
            writer.writerow([month, data['normal'], data['overtime'], data['total']])
        
        # Repository breakdown
        writer.writerow([])
        writer.writerow(['Repository', 'Normal Hours', 'Overtime Hours', 'Total Hours'])
        for repo, data in sorted_repos:
            if data['total'] > 0:
                writer.writerow([repo, data['normal'], data['overtime'], data['total']])
    
    print(f"Company overview generated:")
    print(f"  - Text report: {output_file}")
    print(f"  - CSV file: {csv_output}")
    
    # Print summary to console
    print("\n" + "=" * 80)
    print("COMPANY-WIDE SUMMARY")
    print("=" * 80)
    print(f"Total Hours: {total_all:.1f} (Normal: {total_normal:.1f}, Overtime: {total_overtime:.1f})")
    print(f"Total Days Worked: {total_days}")
    print(f"Employees: {len([e for e in employee_totals.values() if e['total'] > 0])}")
    print(f"Overtime Percentage: {overtime_percentage:.1f}%")
    print("=" * 80)

if __name__ == '__main__':
    generate_company_overview()

