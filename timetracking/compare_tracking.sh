#!/bin/bash

echo "=========================================="
echo "UREN VERGELIJKING: CONDUCTION vs TOTAAL"
echo "=========================================="
echo ""

# Check if both summaries exist
CONDUCTION_SUMMARY="timetracking/Ruben_van_der_Linde/Ruben van der Linde_summary.txt"
GITHUB_SUMMARY="timetracking/github_Ruben_van_der_Linde/Ruben van der Linde_summary.txt"

if [ ! -f "$CONDUCTION_SUMMARY" ]; then
    echo "Error: Conduction summary niet gevonden: $CONDUCTION_SUMMARY"
    exit 1
fi

if [ ! -f "$GITHUB_SUMMARY" ]; then
    echo "Error: GitHub summary niet gevonden: $GITHUB_SUMMARY"
    exit 1
fi

echo "CONDUCTION REPOSITORIES (Team tracking):"
echo "------------------------------------------"
grep "Total hours:" "$CONDUCTION_SUMMARY" | head -1
grep "Weekday hours:" "$CONDUCTION_SUMMARY" | head -1
grep "Weekend hours:" "$CONDUCTION_SUMMARY" | head -1
echo ""
grep "Total hours:" "$CONDUCTION_SUMMARY" | tail -1
grep "Weekday hours:" "$CONDUCTION_SUMMARY" | tail -1
grep "Weekend hours:" "$CONDUCTION_SUMMARY" | tail -1
echo ""

echo "ALLE REPOSITORIES (GitHub user tracking):"
echo "------------------------------------------"
grep "Total hours:" "$GITHUB_SUMMARY" | head -1
grep "Weekday hours:" "$GITHUB_SUMMARY" | head -1
grep "Weekend hours:" "$GITHUB_SUMMARY" | head -1
echo ""
grep "Total hours:" "$GITHUB_SUMMARY" | tail -1
grep "Weekday hours:" "$GITHUB_SUMMARY" | tail -1
grep "Weekend hours:" "$GITHUB_SUMMARY" | tail -1
echo ""

echo "REPOSITORIES:"
echo "------------------------------------------"
echo "Conduction tracking scant alleen:"
echo "  - openregister, opencatalogi, openconnector, softwarecatalog, docudesk"
echo ""
echo "GitHub user tracking scant ALLE repositories:"
grep -A 20 "TOP REPOSITORIES" "$GITHUB_SUMMARY" | tail -n +2
echo ""

echo "=========================================="
