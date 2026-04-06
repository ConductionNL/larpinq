#!/usr/bin/env python3
"""
Generate a summary of time tracking for all employees.
"""

import re
from pathlib import Path
from collections import defaultdict

def extract_summary_data(summary_file):
    """Extract time tracking data from summary file."""
    try:
        with open(summary_file, 'r') as f:
            content = f.read()
            
        # Extract git handle
        handle_match = re.search(r'Git Handle: (.+)', content)
        if not handle_match:
            return None
        
        handle = handle_match.group(1).strip()
        
        # Extract normal time hours
        normal_match = re.search(r'NORMAL TIME BREAKDOWN:\s+Total hours: ([\d.]+)', content)
        normal_hours = float(normal_match.group(1)) if normal_match else 0.0
        
        # Extract overtime hours
        overtime_match = re.search(r'OVERTIME BREAKDOWN:\s+Total hours: ([\d.]+)', content)
        overtime_hours = float(overtime_match.group(1)) if overtime_match else 0.0
        
        total_hours = normal_hours + overtime_hours
        
        return {
            'handle': handle,
            'normal': normal_hours,
            'overtime': overtime_hours,
            'total': total_hours
        }
    except Exception as e:
        return None

def main():
    base = Path('timetracking')
    all_data = []
    
    # Collect all summary data
    for summary_file in sorted(base.rglob('*_summary.txt')):
        data = extract_summary_data(summary_file)
        if data and data['total'] > 0:
            all_data.append(data)
    
    # Group by employee (merge duplicates)
    employee_totals = defaultdict(lambda: {'normal': 0.0, 'overtime': 0.0, 'total': 0.0})
    
    # Employee name mapping (to consolidate duplicates)
    employee_map = {
        'rubenvdlinde': 'Ruben van der Linde',
        'ruben@conduction.nl': 'Ruben van der Linde',
        'Ruben van der Linde': 'Ruben van der Linde',
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
        '87539434+CBibop12': 'Alex Rekachynskyi',
        '87539434+CBibop12@users.noreply.github.com': 'Alex Rekachynskyi',
        'Alex': 'Alex Rekachynskyi',
        'Alex Rekachynskyi': 'Alex Rekachynskyi',
        'phone.070718@gmail.com': 'Alex Rekachynskyi',
        'Robert Zondervan': 'Robert Zondervan',
        'robert@conduction.nl': 'Robert Zondervan',
        'rjzondervan': 'Robert Zondervan',
        'rjzondervan@gmail.com': 'Robert Zondervan',
        'Thijn': 'Thijn Douwma',
        'Thijn Douwma': 'Thijn Douwma',
        'thijn@conduction.nl': 'Thijn Douwma',
        'Ralkey': 'Thijn Douwma',
        'Wilco Louwerse': 'Wilco Louwerse',
        'wilco@conduction.nl': 'Wilco Louwerse',
        'Matthias Oliveiro': 'Matthias Oliveiro',
        'matthias@conduction.nl': 'Matthias Oliveiro',
    }
    
    # Aggregate data
    for data in all_data:
        handle = data['handle']
        employee_name = employee_map.get(handle, handle)
        
        # Skip bots and empty handles
        if any(bot in handle.lower() for bot in ['bot', 'github action', 'cursor agent', 'action@github']):
            continue
        if not handle or handle.strip() == '':
            continue
        
        employee_totals[employee_name]['normal'] += data['normal']
        employee_totals[employee_name]['overtime'] += data['overtime']
        employee_totals[employee_name]['total'] += data['total']
    
    # Print summary
    print('TIME TRACKING SUMMARY FOR ALL EMPLOYEES')
    print('=' * 70)
    print(f'{"Employee":<30} {"Normal Hours":>15} {"Overtime Hours":>15} {"Total Hours":>15}')
    print('-' * 70)
    
    # Sort by total hours descending
    sorted_employees = sorted(employee_totals.items(), key=lambda x: x[1]['total'], reverse=True)
    
    for employee_name, hours in sorted_employees:
        if hours['total'] > 0:
            print(f'{employee_name:<30} {hours["normal"]:>15.1f} {hours["overtime"]:>15.1f} {hours["total"]:>15.1f}')
    
    print('-' * 70)
    total_normal = sum(h['normal'] for h in employee_totals.values())
    total_overtime = sum(h['overtime'] for h in employee_totals.values())
    total_all = sum(h['total'] for h in employee_totals.values())
    print(f'{"TOTAL":<30} {total_normal:>15.1f} {total_overtime:>15.1f} {total_all:>15.1f}')
    print('=' * 70)
    
    # Write to file
    output_file = Path('timetracking/EMPLOYEE_SUMMARY.txt')
    with open(output_file, 'w') as f:
        f.write('TIME TRACKING SUMMARY FOR ALL EMPLOYEES\n')
        f.write('=' * 70 + '\n')
        f.write(f'{"Employee":<30} {"Normal Hours":>15} {"Overtime Hours":>15} {"Total Hours":>15}\n')
        f.write('-' * 70 + '\n')
        
        for employee_name, hours in sorted_employees:
            if hours['total'] > 0:
                f.write(f'{employee_name:<30} {hours["normal"]:>15.1f} {hours["overtime"]:>15.1f} {hours["total"]:>15.1f}\n')
        
        f.write('-' * 70 + '\n')
        f.write(f'{"TOTAL":<30} {total_normal:>15.1f} {total_overtime:>15.1f} {total_all:>15.1f}\n')
        f.write('=' * 70 + '\n')
    
    print(f'\nSummary written to: {output_file}')

if __name__ == '__main__':
    main()


