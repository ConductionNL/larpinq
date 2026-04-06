# 🚀 Complete Tempo Export Overzicht

## ✅ Wat is er klaar?

Je hebt nu een **volledig geautomatiseerd systeem** om git commits naar Tempo Timesheets te exporteren!

### 📊 Je Data (2025)

```
Totaal: 1,786.9 uur
├── Normale uren: 854.8 uur (201 dagen)
└── Overuren: 932.1 uur (225 dagen)

Commits: 4,119 commits
└── Verdeeld over 5 repositories
```

---

## 📁 Bestanden Overzicht

### 🎯 Klaar voor Tempo Import

```
timetracking/Ruben_van_der_Linde/
│
├── 📄 Ruben van der Linde_normal_tempo.csv       ← UPLOAD NAAR TEMPO
├── 📄 Ruben van der Linde_overtime_tempo.csv     ← UPLOAD NAAR TEMPO
│
├── 🤖 Ruben van der Linde_normal_tempo_api.json
├── 🤖 Ruben van der Linde_overtime_tempo_api.json
│
├── 🚀 Ruben van der Linde_normal_tempo_upload.sh
└── 🚀 Ruben van der Linde_overtime_tempo_upload.sh
```

### 📚 Scripts & Tools

```
apps-extra/
│
├── 🔧 generate_github_user_tracking.py     ← Scan ALLE repositories
├── 🔄 convert_to_tempo.py                  ← Converteer naar Tempo
├── ⚡ tempo_quickstart.sh                  ← Alles in 1 commando
│
├── 📖 TEMPO_IMPORT_HANDLEIDING.md          ← Volledige handleiding
├── 📖 GITHUB_USER_TRACKING_README.md       ← Git tracking uitleg
└── 📖 TEMPO_EXPORT_OVERZICHT.md            ← Dit bestand
```

---

## 🎯 Quick Start: 3 Manieren

### ⚡ Methode 1: Super Snel (1 commando)

```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra
./tempo_quickstart.sh
```

Dit doet alles automatisch:
1. ✅ Scant alle git repositories
2. ✅ Genereert tijd tracking (426 entries)
3. ✅ Converteert naar Tempo CSV & JSON
4. ✅ Toont overzicht en volgende stappen

**Output:** Klaar-voor-gebruik Tempo CSV bestanden!

---

### 📤 Methode 2: Handmatig CSV Upload (Makkelijkst)

#### Stap 1: Download de bestanden

```bash
# De bestanden staan hier:
cd timetracking/Ruben_van_der_Linde
ls -lh *_tempo.csv
```

Je vindt:
- `Ruben van der Linde_normal_tempo.csv` (201 entries)
- `Ruben van der Linde_overtime_tempo.csv` (225 entries)

#### Stap 2: Upload naar Tempo

1. Open **Jira** → **Tempo** → **Settings**
2. Klik op **Import** (of zoek "Import Worklogs")
3. Upload `Ruben van der Linde_normal_tempo.csv`
4. Map de kolommen:
   ```
   Issue Key     → Jira Issue
   Start Date    → Date
   Hours         → Time Spent
   Description   → Work Description
   Activity Name → Activity
   ```
5. Klik **Import**
6. Herhaal voor overtime CSV

✅ **Klaar!** Je hebt nu 426 uren in Tempo!

---

### 🤖 Methode 3: Automatische API Upload (Gevorderd)

Voor bulk upload of automatisering.

#### Stap 1: Verkrijg Tempo API Token

```
Jira → Tempo → Settings → API Integration → New Token
```

#### Stap 2: Configureer Upload Script

```bash
cd timetracking/Ruben_van_der_Linde
nano "Ruben van der Linde_normal_tempo_upload.sh"
```

Wijzig:
```bash
TEMPO_API_TOKEN="your-tempo-token-here"
JIRA_BASE_URL="https://your-company.atlassian.net"
```

#### Stap 3: Upload!

```bash
# Test met normale uren
./"Ruben van der Linde_normal_tempo_upload.sh"

# Als dat werkt, ook overuren
./"Ruben van der Linde_overtime_tempo_upload.sh"
```

Het script toont progress:
```
[1/201] Uploading worklog...
  ✓ Success: 2025-01-02 - 4.2h
[2/201] Uploading worklog...
  ✓ Success: 2025-01-03 - 2.0h
...
```

✅ **Klaar!** Alles automatisch geüpload!

---

## 🔄 Regelmatig Updaten

### Wekelijkse Update

```bash
# Laatste week
python3 generate_github_user_tracking.py \
  --github-user "Ruben van der Linde" \
  --start-date $(date -d '7 days ago' +%Y-%m-%d) \
  --end-date $(date +%Y-%m-%d)

python3 convert_to_tempo.py --user "Ruben van der Linde" --format csv
```

### Maandelijkse Update

```bash
# Vorige maand
python3 generate_github_user_tracking.py \
  --github-user "Ruben van der Linde" \
  --start-date 2025-11-01 \
  --end-date 2025-11-30

python3 convert_to_tempo.py --user "Ruben van der Linde" --format csv
```

### Automatiseren met Cron

```bash
# Elke vrijdag om 18:00
0 18 * * 5 cd /path/to/apps-extra && ./tempo_quickstart.sh
```

---

## 📊 CSV Formaat Details

### Voorbeeld Entry

```csv
Issue Key,Start Date,Hours,Work Description,Activity Name
COND-20250116,2025-01-16,6.9,"6 commits in opencatalogi, openregister: ...",Development
```

### Kolommen

| Kolom | Beschrijving | Voorbeeld |
|-------|--------------|-----------|
| **Issue Key** | Jira issue of auto-generated | `COND-20250116` |
| **Start Date** | Datum (YYYY-MM-DD) | `2025-01-16` |
| **Hours** | Decimale uren | `6.9` (= 6u 54m) |
| **Work Description** | Wat je deed | "6 commits in openregister..." |
| **Activity Name** | Type werk | `Development` |

### ⚙️ Aanpassen voor Specifieke Issues

Als je alle tijd tegen één issue wilt loggen:

```bash
python3 convert_to_tempo.py \
  --user "Ruben van der Linde" \
  --format csv \
  --issue-key "COND-1234"
```

Of pas de CSV handmatig aan in Excel.

---

## 🎨 Geavanceerde Opties

### Verschillende Periodes

```bash
# Q1 2025
python3 generate_github_user_tracking.py \
  --github-user "Ruben van der Linde" \
  --start-date 2025-01-01 \
  --end-date 2025-03-31

# Specifieke maand
python3 generate_github_user_tracking.py \
  --github-user "Ruben van der Linde" \
  --start-date 2025-06-01 \
  --end-date 2025-06-30
```

### Verschillende Activity Types

```bash
# Normale uren als "Development"
python3 convert_to_tempo.py \
  --input "normal_time.csv" \
  --activity "Development"

# Overuren als "After Hours"
python3 convert_to_tempo.py \
  --input "overtime.csv" \
  --activity "After Hours Support"
```

### Specifieke Repositories

```bash
# Alleen nextcloud-docker-dev
python3 generate_github_user_tracking.py \
  --github-user "Ruben van der Linde" \
  --search-paths /home/rubenlinde/nextcloud-docker-dev
```

---

## 🐛 Troubleshooting

### "No commits found"

**Probleem:** Script vindt geen commits

**Oplossing:**
```bash
# Check welke naam je gebruikt
cd openregister
git log --all --pretty=format:"%an|%ae" | sort -u | grep -i ruben

# Gebruik die exacte naam
python3 generate_github_user_tracking.py --github-user "exacte-naam-hier"
```

### CSV Import Faalt

**Probleem:** Tempo accepteert de CSV niet

**Oplossing:**
1. Open CSV in Excel
2. Controleer datumformaat (moet YYYY-MM-DD zijn)
3. Controleer Issue Keys (moeten bestaan in Jira)
4. Sla op als CSV (niet Excel)

### API Upload Faalt

**Probleem:** HTTP 401 of 403 errors

**Oplossing:**
1. Verifieer API token is geldig
2. Check API token rechten in Tempo
3. Controleer JIRA_BASE_URL is correct

### "jq: command not found"

```bash
sudo apt-get install jq
```

---

## 📈 Data Overzicht

### 2025 Statistieken

```
Totale Commits: 4,119
Totale Uren: 1,786.9

Repository Verdeling:
├── openregister: 3,032 commits (73.6%)
├── opencatalogi: 409 commits (9.9%)
├── softwarecatalog: 301 commits (7.3%)
├── openconnector: 246 commits (6.0%)
└── docudesk: 131 commits (3.2%)

Tijd Verdeling:
├── Normale uren: 854.8 (47.8%)
└── Overuren: 932.1 (52.2%)
    ├── Weekdag avonden: 591.1
    └── Weekenden: 341.0
```

---

## ✅ Checklist Import

Voordat je importeert:

- [ ] CSV bestanden gegenereerd
- [ ] Entries geverifieerd (open in Excel)
- [ ] Issue Keys gecheckt (bestaan ze in Jira?)
- [ ] Tempo rechten gecontroleerd
- [ ] **Test met 5-10 entries eerst!**
- [ ] Controleer resultaat in Tempo
- [ ] Pas aan indien nodig
- [ ] Bulk import uitvoeren
- [ ] Verifieer totale uren
- [ ] Backup CSV bestanden bewaren

---

## 🎯 Best Practices

### Voor Eerste Import

1. **Start Klein**: Importeer eerst alleen januari
2. **Verifieer**: Check of alles klopt in Tempo
3. **Schaal Op**: Import de rest als het goed is

### Voor Regelmatige Updates

1. **Wekelijks**: Update je tracking elke vrijdag
2. **Controleer**: Verifieer entries voor je importeert
3. **Documenteer**: Houd bij welke periodes je geïmporteerd hebt

### Voor Nauwkeurigheid

1. **Handmatige Aanpassingen**: Pas uren aan waar nodig
2. **Issue Toewijzing**: Koppel commits aan correcte issues
3. **Activity Types**: Gebruik passende activity types
4. **Notities**: Voeg extra context toe in descriptions

---

## 📞 Hulp Nodig?

### Documentatie

- **TEMPO_IMPORT_HANDLEIDING.md** - Gedetailleerde stap-voor-stap
- **GITHUB_USER_TRACKING_README.md** - Git tracking uitleg
- **Tempo API Docs** - https://tempo-io.github.io/tempo-api-docs/

### Debug Commands

```bash
# Verifieer tracking data
head -20 "timetracking/Ruben_van_der_Linde/Ruben van der Linde_summary.txt"

# Check CSV format
head -5 "timetracking/Ruben_van_der_Linde/Ruben van der Linde_normal_tempo.csv"

# Count entries
wc -l timetracking/Ruben_van_der_Linde/*_tempo.csv
```

---

## 🎉 Conclusie

Je hebt nu:

✅ **1,786.9 uur** klaar voor Tempo import  
✅ **426 entries** perfect geformatteerd  
✅ **3 import methodes** (handmatig, CSV, API)  
✅ **Volledige automatisering** voor toekomstige updates  
✅ **Complete documentatie** voor alle scenario's  

**Volgende stap:** Kies één van de 3 methodes en import je uren! 🚀

---

*Gegenereerd: 2025*  
*Voor: Ruben van der Linde*  
*Periode: Heel 2025 (jan-dec)*
