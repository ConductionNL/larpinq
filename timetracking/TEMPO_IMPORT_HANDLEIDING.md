# Tempo Import Handleiding

## 🎯 Overzicht

Je hebt nu **426 uren** (201 normale + 225 overuren) klaarstaan voor import in Tempo Timesheets!

### Gegenereerde Bestanden

```
timetracking/Ruben_van_der_Linde/
├── Ruben van der Linde_normal_tempo.csv          # 201 normale werkuren
├── Ruben van der Linde_overtime_tempo.csv        # 225 overuren
├── Ruben van der Linde_normal_tempo_api.json     # API format (normaal)
├── Ruben van der Linde_overtime_tempo_api.json   # API format (overuren)
├── Ruben van der Linde_normal_tempo_upload.sh    # Upload script (normaal)
└── Ruben van der Linde_overtime_tempo_upload.sh  # Upload script (overuren)
```

## 📊 Samenvatting

| Type | Entries | Totale Uren | Periode |
|------|---------|-------------|---------|
| **Normale werktijd** | 201 dagen | 854.8 uur | Jan-Dec 2025 |
| **Overuren** | 225 dagen | 932.1 uur | Jan-Dec 2025 |
| **TOTAAL** | 426 entries | **1,786.9 uur** | Heel 2025 |

---

## 🚀 Methode 1: Handmatige CSV Import (Eenvoudigst)

### Stap 1: Download de CSV bestanden

De bestanden staan klaar in:
```
/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/timetracking/Ruben_van_der_Linde/
```

Bestanden om te downloaden:
- `Ruben van der Linde_normal_tempo.csv` (normale uren)
- `Ruben van der Linde_overtime_tempo.csv` (overuren)

### Stap 2: Open Tempo in Jira

1. Ga naar je Jira instance
2. Klik op **Tempo** in het menu
3. Ga naar **Settings** → **Import** (of zoek naar "Import Worklogs")

### Stap 3: Import CSV

1. Klik op **Import from CSV** of **Upload CSV**
2. Upload `Ruben van der Linde_normal_tempo.csv`
3. Map de kolommen:
   - **Issue Key** → Jira Issue
   - **Start Date** → Date
   - **Hours** → Time Spent (Hours)
   - **Work Description** → Description
   - **Activity Name** → Activity
4. Klik op **Import**
5. Herhaal voor `overtime_tempo.csv`

### CSV Formaat

De gegenereerde CSV heeft deze kolommen:

```csv
Issue Key,Start Date,Hours,Work Description,Activity Name
COND-20250102,2025-01-02,4.2,"7 commits in openregister...",Development
COND-20250103,2025-01-03,2.0,"2 commits in openregister...",Development
```

- **Issue Key**: Automatisch gegenereerd als `COND-YYYYMMDD`
- **Start Date**: Datum in YYYY-MM-DD formaat
- **Hours**: Decimale uren (bijv. 4.2 = 4 uur 12 minuten)
- **Work Description**: Commits en repository info
- **Activity Name**: Altijd "Development"

### ⚠️ Belangrijk

- **Controleer de Issue Keys**: De automatisch gegenereerde keys (`COND-20250102`) zijn placeholders
- **Wijzig zo nodig**: Voor jouw daadwerkelijke Jira issues, pas de CSV aan of gebruik Methode 2/3

---

## 🔧 Methode 2: Handmatige CSV Aanpassing (Voor specifieke issues)

Als je de tijd wilt loggen tegen specifieke Jira issues:

### Stap 1: Open CSV in Excel/LibreOffice

```bash
# Download de CSV en open in Excel
```

### Stap 2: Pas Issue Keys aan

Wijzig de "Issue Key" kolom naar je echte Jira issues:

| Oude Key | Nieuwe Key | Datum | Uren |
|----------|------------|-------|------|
| COND-20250102 | **PROJ-123** | 2025-01-02 | 4.2 |
| COND-20250103 | **PROJ-123** | 2025-01-03 | 2.0 |
| COND-20250108 | **PROJ-456** | 2025-01-08 | 2.0 |

### Stap 3: Sla op als CSV

Let op: Bewaar als **CSV (komma gescheiden)**, niet als Excel (.xlsx)

### Stap 4: Import in Tempo

Volg de stappen van Methode 1

---

## 🤖 Methode 3: Automatische API Import (Gevorderd)

Voor grote hoeveelheden of automatisering kun je de Tempo REST API gebruiken.

### Vereisten

```bash
# Install jq voor JSON processing
sudo apt-get install jq

# Zorg dat curl beschikbaar is (meestal standaard)
```

### Stap 1: Verkrijg Tempo API Token

1. Ga naar Tempo → **Settings** → **API Integration**
2. Klik op **New Token**
3. Geef een naam (bijv. "Time Import 2025")
4. Kopieer de token (je ziet deze maar 1x!)

### Stap 2: Pas het Upload Script aan

Edit het gegenereerde upload script:

```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/timetracking/Ruben_van_der_Linde
nano "Ruben van der Linde_normal_tempo_upload.sh"
```

Wijzig deze regels:

```bash
# Vul je Tempo API token in
TEMPO_API_TOKEN="jouw-tempo-api-token-hier"

# Vul je Jira URL in
JIRA_BASE_URL="https://jouw-bedrijf.atlassian.net"
```

### Stap 3: Pas de JSON aan (optioneel)

Als je specifieke issue keys wilt:

```bash
# Open de JSON file
nano "Ruben van der Linde_normal_tempo_api.json"

# Wijzig alle "issueKey": "COND-1" naar je echte issue key
# Of gebruik een find/replace tool
```

### Stap 4: Voer Upload Script uit

```bash
# Maak executable (als nog niet gedaan)
chmod +x "Ruben van der Linde_normal_tempo_upload.sh"

# Voer uit
./"Ruben van der Linde_normal_tempo_upload.sh"
```

Het script zal:
1. Tellen hoeveel entries er zijn
2. Vragen om bevestiging
3. Elke entry uploaden naar Tempo
4. Progress tonen
5. Samenvatting geven

### Output Voorbeeld

```
==========================================
Tempo Worklogs Upload
==========================================

JSON file: Ruben van der Linde_normal_tempo_api.json
Total worklogs to upload: 201

Do you want to proceed with upload? (yes/no): yes

[1/201] Uploading worklog...
  ✓ Success: 2025-01-02 - 4.2h
[2/201] Uploading worklog...
  ✓ Success: 2025-01-03 - 2.0h
...

==========================================
Upload Complete
==========================================
Success: 201
Failed: 0
Total: 201

All worklogs uploaded successfully!
```

---

## 🔄 Methode 4: Script Aanpassen Voor Bulk Issue Assignment

Als je alle uren tegen één specifiek issue wilt loggen:

```bash
# Genereer met specifieke issue key
python3 convert_to_tempo.py \
  --user "Ruben van der Linde" \
  --format both \
  --include-overtime \
  --issue-key "COND-1234" \
  --activity "Development"
```

Dit zet alle entries tegen `COND-1234` in plaats van per-datum keys.

---

## 📋 Aanbevolen Workflow

### Voor eerste keer:

1. **Start klein**: Importeer eerst alleen januari om het proces te testen
   ```bash
   # Filter CSV in Excel op januari entries
   # Of genereer opnieuw met --start-date 2025-01-01 --end-date 2025-01-31
   ```

2. **Controleer in Tempo**: Kijk of de import correct is

3. **Schaalpas op**: Als het goed ziet, importeer de rest

### Voor regelmatige updates:

1. **Wekelijks script draaien**:
   ```bash
   # Laatste week
   python3 generate_github_user_tracking.py \
     --github-user "Ruben van der Linde" \
     --start-date $(date -d '7 days ago' +%Y-%m-%d) \
     --end-date $(date +%Y-%m-%d)
   
   # Converteer naar Tempo
   python3 convert_to_tempo.py --user "Ruben van der Linde" --format csv
   ```

2. **Import in Tempo**: Upload de CSV

3. **Automatiseer met cron** (optioneel):
   ```bash
   # Voeg toe aan crontab
   0 18 * * 5 /path/to/weekly_tempo_export.sh
   ```

---

## ⚙️ Geavanceerde Opties

### Custom Activity Types

Verschillende activity types per file:

```bash
# Normale uren als Development
python3 convert_to_tempo.py \
  --input "normal_time.csv" \
  --activity "Development"

# Overuren als "After Hours Support"
python3 convert_to_tempo.py \
  --input "overtime.csv" \
  --activity "After Hours Support"
```

### Meerdere Jira Projects

Voor verschillende projecten:

```bash
# OpenRegister commits naar OREG project
python3 convert_to_tempo.py \
  --input "normal_time.csv" \
  --jira-project "OREG"

# OpenCatalogi commits naar OCAT project
python3 convert_to_tempo.py \
  --input "normal_time.csv" \
  --jira-project "OCAT"
```

### Account ID vinden

Voor API import heb je je Jira Account ID nodig:

1. Ga naar je Jira profile
2. Kijk in de URL: `https://xxx.atlassian.net/jira/people/5b10ac8d82e05b22cc7d4ef5`
3. Het laatste deel is je account ID: `5b10ac8d82e05b22cc7d4ef5`

Of via API:
```bash
curl -u email@example.com:api-token \
  https://your-domain.atlassian.net/rest/api/3/myself
```

---

## 🐛 Troubleshooting

### CSV Import Fails

**Probleem**: "Invalid date format"
**Oplossing**: Tempo verwacht YYYY-MM-DD. Check of Excel de datums niet heeft geconverteerd.

**Probleem**: "Issue not found"
**Oplossing**: Controleer of de issue keys bestaan in Jira. Wijzig naar bestaande issues.

### API Upload Fails

**Probleem**: HTTP 401 Unauthorized
**Oplossing**: Check je API token. Vernieuw indien nodig.

**Probleem**: HTTP 400 Bad Request
**Oplossing**: 
- Check of issue key geldig is
- Check of account ID correct is
- Check of datum formaat klopt

**Probleem**: "Rate limit exceeded"
**Oplossing**: Het script heeft al een 0.5s delay. Verhoog dit in het script indien nodig.

### Script Errors

**Probleem**: "jq: command not found"
**Oplossing**: 
```bash
sudo apt-get install jq
```

**Probleem**: "Permission denied"
**Oplossing**:
```bash
chmod +x upload_script.sh
```

---

## 📞 Support

Voor vragen:
1. Check de Tempo API docs: https://tempo-io.github.io/tempo-api-docs/
2. Check je Tempo logs in Jira
3. Test met een enkele entry eerst

---

## ✅ Checklist Voor Import

- [ ] CSV bestanden gegenereerd
- [ ] Voorbeeld entries geverifieerd in Excel
- [ ] Tempo import rechten in Jira
- [ ] (Voor API) Tempo API token verkregen
- [ ] (Voor API) Account ID gevonden
- [ ] Test import met 5-10 entries
- [ ] Controleer resultaat in Tempo
- [ ] Bulk import uitvoeren
- [ ] Verifieer totale uren in Tempo
- [ ] CSV/JSON backups bewaren

---

**Succes met je Tempo import! 🎉**

Als je vragen hebt of ergens tegenaan loopt, laat het weten!








