#!/usr/bin/env python3
"""Calculate theoretical working hours in 2025."""

from datetime import datetime, timedelta

year = 2025

# Count workdays (Monday-Friday)
start_date = datetime(year, 1, 1)
end_date = datetime(year, 12, 31)

workdays = 0
current_date = start_date

while current_date <= end_date:
    # Monday = 0, Sunday = 6
    if current_date.weekday() < 5:  # Monday to Friday
        workdays += 1
    current_date += timedelta(days=1)

print("=" * 60)
print(f"THEORETICAL WORKING HOURS - {year}")
print("=" * 60)
print()

print(f"Total days in {year}: 365")
print(f"Weekdays (Mon-Fri): {workdays}")
print(f"Weekend days: {365 - workdays}")
print()

# Dutch national holidays in 2025 (example - adjust for actual holidays)
holidays = [
    "2025-01-01",  # Nieuwjaarsdag
    "2025-04-18",  # Goede Vrijdag
    "2025-04-20",  # Paaszondag
    "2025-04-21",  # Paasmaandag
    "2025-04-27",  # Koningsdag
    "2025-05-05",  # Bevrijdingsdag
    "2025-05-29",  # Hemelvaartsdag
    "2025-06-08",  # Pinksterzondag
    "2025-06-09",  # Pinkstermaandag
    "2025-12-25",  # Eerste Kerstdag
    "2025-12-26",  # Tweede Kerstdag
]

# Count holidays that fall on workdays
workday_holidays = 0
for holiday in holidays:
    date = datetime.strptime(holiday, "%Y-%m-%d")
    if date.weekday() < 5:  # Weekday
        workday_holidays += 1

print(f"National holidays: {len(holidays)}")
print(f"Holidays on workdays: {workday_holidays}")
print()

net_workdays = workdays - workday_holidays

print("CALCULATIONS:")
print("-" * 60)
print(f"Gross workdays: {workdays}")
print(f"Minus holidays: -{workday_holidays}")
print(f"Net workdays: {net_workdays}")
print()

# Different scenarios
print("SCENARIO 1: Full-time (40h/week, no vacation)")
print(f"  {net_workdays} days × 8h = {net_workdays * 8} hours")
print()

print("SCENARIO 2: Full-time (40h/week, 4 weeks vacation)")
print(f"  {net_workdays} days - 20 vacation days = {net_workdays - 20} days")
print(f"  {net_workdays - 20} days × 8h = {(net_workdays - 20) * 8} hours")
print()

print("SCENARIO 3: Full-time (40h/week, 5 weeks vacation)")
print(f"  {net_workdays} days - 25 vacation days = {net_workdays - 25} days")
print(f"  {net_workdays - 25} days × 8h = {(net_workdays - 25) * 8} hours")
print()

print("ALTERNATIVE CALCULATION (52 weeks):")
print("-" * 60)
print(f"52 weeks × 40h = 2080 hours")
print(f"Minus 4 weeks vacation = 1920 hours")
print(f"Minus 5 weeks vacation = 1880 hours")
print()

print("=" * 60)
print("YOUR LOGGED HOURS")
print("=" * 60)
print()
print("Currently in Tempo: 659.7 hours")
print("From GitHub API (only your commits): ~420.8 hours")
print()

# Calculate coverage
full_time_target = (net_workdays - 25) * 8  # 5 weeks vacation
print("COVERAGE ANALYSIS:")
print("-" * 60)
print(f"Expected (full-time, 5 weeks vacation): {full_time_target} hours")
print(f"Logged (current): 659.7 hours")
print(f"Coverage: {(659.7 / full_time_target * 100):.1f}%")
print()
print(f"Logged (GitHub API only): 420.8 hours")  
print(f"Coverage: {(420.8 / full_time_target * 100):.1f}%")
print()
print("Note: Git commits only capture coding time,")
print("      not meetings, planning, reviews, documentation, etc.")







