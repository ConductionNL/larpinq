# 🤖 Volledig Geautomatiseerde Tempo Import

## 🎯 Wat Doet Dit?

Dit script doet **ALLES automatisch**:

1. ✅ Analyseert je git commits
2. ✅ Groepeert in logische werkblokken (per dag of week)
3. ✅ Maakt automatisch Jira issues aan
4. ✅ Boekt op het juiste project (OpenRegister/OpenCatalogi/etc)
5. ✅ Logt tijd via Tempo API
6. ✅ **Klaar in enkele minuten!**

## 🚀 Setup (Eenmalig)

### Stap 1: Verkrijg API Tokens

#### Jira API Token
1. Ga naar https://id.atlassian.com/manage-profile/security/api-tokens
2. Klik **Create API token**
3. Geef een naam (bijv. "Tempo Import")
4. **Kopieer en bewaar de token!**

#### Tempo API Token
1. Ga naar Jira → **Tempo** → **Settings**
2. Klik **API Integration**
3. Klik **New Token**
4. Geef een naam (bijv. "Auto Import")
5. **Kopieer en bewaar de token!**

### Stap 2: Install Requirements

```bash
pip3 install requests
# Of als je geen admin rechten hebt:
pip3 install --user requests
```

### Stap 3: Configureer Tokens

**Optie A: Environment Variables (Veilig)**
```bash
export JIRA_API_TOKEN="jouw-jira-token-hier"
export TEMPO_API_TOKEN="jouw-tempo-token-hier"
```

**Optie B: In het script zelf (minder veilig)**
```bash
python3 auto_tempo_import.py \
  --jira-token "jouw-token" \
  --tempo-token "jouw-token"
```

---

## 🎬 Gebruik

### Dry Run (Test - Aanbevolen!)

**Eerst een dry run doen om te zien wat er gebeurt:**

```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra

python3 auto_tempo_import.py \
  --user "Ruben van der Linde" \
  --dry-run
```

Dit toont:
- Hoeveel issues er aangemaakt zouden worden
- Welke projecten
- Hoeveel uren per issue
- **Maar maakt NIETS aan!**

### Live Execution (Echt Uitvoeren)

**Als de dry run goed ziet, voer het echt uit:**

```bash
python3 auto_tempo_import.py \
  --user "Ruben van der Linde" \
  --jira-url "https://jouw-bedrijf.atlassian.net" \
  --jira-email "jouw-email@bedrijf.nl" \
  --auto-create-issues
```

**Met overtime:**
```bash
python3 auto_tempo_import.py \
  --user "Ruben van der Linde" \
  --jira-url "https://jouw-bedrijf.atlassian.net" \
  --jira-email "jouw-email@bedrijf.nl" \
  --include-overtime \
  --auto-create-issues
```

**Met environment variables:**
```bash
export JIRA_API_TOKEN="your-token"
export TEMPO_API_TOKEN="your-token"

python3 auto_tempo_import.py \
  --user "Ruben van der Linde" \
  --jira-url "https://jouw-bedrijf.atlassian.net" \
  --jira-email "jouw-email@bedrijf.nl" \
  --auto-create-issues
```

---

## 🎛️ Opties

### Grouping Modes

**Daily (Default)** - 1 issue per werkdag
```bash
--grouping daily
```
- ✅ Meest gedetailleerd
- ✅ Duidelijk per dag
- ⚠️ Veel issues (201 voor normale uren)

**Weekly** - 1 issue per week
```bash
--grouping weekly
```
- ✅ Minder issues (~52)
- ✅ Betere week-overzichten
- ⚠️ Minder detail per dag

### Include Overtime

```bash
--include-overtime
```
- Verwerkt ook overuren bestand
- Maakt aparte issues aan

---

## 📊 Wat Gebeurt Er?

### Intelligente Project Mapping

Het script bepaalt automatisch het juiste project:

```
Repositories → Jira Project
─────────────────────────────
openregister    → REGISTER
opencatalogi    → CATALOGI
openconnector   → CONNECTOR
softwarecatalog → CATALOG
docudesk        → DOCUDESK
anders          → COND (fallback)
```

### Issue Creation

**Per werkdag wordt een issue aangemaakt:**

```
Summary: "Development Work - OpenRegister - January 15, 2025"

Description:
Development work on OpenRegister

Date: 2025-01-15
Hours: 4.2
Commits: 7
Repositories: openregister, openconnector

Work Summary:
Merge pull request #114 from ConductionNL/feature/...

---
Auto-generated from git commit analysis.
```

### Time Logging

Direct na issue creation wordt de tijd gelogd via Tempo API:
- Issue Key: `REGISTER-1234`
- Date: 2025-01-15
- Hours: 4.2
- Description: "7 commits in openregister, openconnector"

---

## 📈 Voorbeeld Output

```
====================================================================
FULLY AUTOMATED TEMPO IMPORT
====================================================================

User: Ruben van der Linde
Grouping: daily
Mode: LIVE EXECUTION

Initializing API clients...
Getting account ID...
✓ Account ID: 5b10ac8d82e05b22cc7d4ef5

Processing: Ruben van der Linde_normal_time.csv
Found 201 work entries

Grouping into work blocks (daily)...
Created 201 work blocks

Processing work blocks...

[1/201] Processing work block...
  Date: 2025-01-02
  Project: OpenConnector (CONNECTOR)
  Hours: 4.2
  Commits: 7
  Creating Jira issue...
  ✓ Created issue: CONNECTOR-5678
  Logging time to Tempo...
  ✓ Logged 4.2 hours (Worklog ID: 12345)

[2/201] Processing work block...
  Date: 2025-01-03
  Project: OpenRegister (REGISTER)
  Hours: 2.0
  Commits: 2
  Creating Jira issue...
  ✓ Created issue: REGISTER-1234
  Logging time to Tempo...
  ✓ Logged 2.0 hours (Worklog ID: 12346)

...

====================================================================
NORMAL HOURS - RESULTS
====================================================================
Total work blocks: 201
Issues created: 201
Worklogs created: 201
Total hours logged: 857.0
Errors: 0

====================================================================
IMPORT COMPLETE!

Check your Jira/Tempo to verify the results
====================================================================
```

---

## ⚙️ Geavanceerd

### Custom Project Mapping

Edit de `determine_project_key()` functie in het script:

```python
def determine_project_key(repositories):
    repos_lower = repositories.lower()
    
    if 'openregister' in repos_lower:
        return 'REGISTER', 'OpenRegister'
    elif 'mijn-app' in repos_lower:
        return 'MIJN', 'Mijn App'  # Je eigen mapping
    # ... etc
```

### Rate Limiting

Het script heeft ingebouwde delays:
- 0.5s tussen issue creation en worklog
- 0.5s tussen work blocks
- Past zich aan bij rate limit errors

### Error Handling

Bij fouten:
- Logt de error
- Gaat door met volgende block
- Toont totaal aantal errors aan het eind

---

## 🔒 Security Best Practices

### ✅ DO:
- Gebruik environment variables voor tokens
- Bewaar tokens in een password manager
- Revoke tokens als je ze niet meer nodig hebt

### ❌ DON'T:
- Commit tokens naar git
- Deel tokens in chat/email
- Gebruik dezelfde token voor meerdere doel

einden

### Token Permissions

Zorg dat je tokens deze rechten hebben:
- **Jira API**: Write issues, Read projects
- **Tempo API**: Write worklogs

---

## 🐛 Troubleshooting

### "HTTP 401 Unauthorized"

**Probleem:** API token is ongeldig

**Oplossing:**
1. Verifieer token in Jira/Tempo settings
2. Genereer nieuwe token
3. Check of email correct is (voor Jira)

### "HTTP 403 Forbidden"

**Probleem:** Geen rechten om issues aan te maken

**Oplossing:**
1. Check Jira project permissions
2. Vraag admin om rechten
3. Verifieer dat project key bestaat

### "HTTP 400 Bad Request"

**Probleem:** Issue format is incorrect

**Oplossing:**
1. Check of project key correct is
2. Verifieer issue type 'Task' bestaat
3. Check Jira logs voor details

### "Project key not found"

**Probleem:** Jira project bestaat niet

**Oplossing:**
1. Check project keys in Jira
2. Update `determine_project_key()` functie
3. Of gebruik fallback project (COND)

### Rate Limiting

Als je "429 Too Many Requests" krijgt:
- Verhoog de delays in het script
- Verwerk minder entries per keer
- Wacht even en probeer opnieuw

---

## 📊 Statistieken (Voor Jouw 2025 Data)

### Met Daily Grouping:
```
Normale Uren:
- 201 issues aangemaakt
- 201 worklogs
- 857.0 uur gelogd
- ~5 minuten execution tijd

Overuren (optioneel):
- 225 issues aangemaakt
- 225 worklogs
- 932.1 uur gelogd
- ~6 minuten execution tijd

TOTAAL:
- 426 issues
- 426 worklogs
- 1,786.9 uur
- ~11 minuten totaal
```

### Met Weekly Grouping:
```
Normale Uren:
- ~30 issues (weken met commits)
- ~30 worklogs
- 857.0 uur gelogd
- ~1 minuut execution tijd
```

---

## ✅ Checklist

Voor je start:

- [ ] Jira API token verkregen
- [ ] Tempo API token verkregen
- [ ] `requests` library geïnstalleerd
- [ ] Tokens geconfigureerd (env vars of script)
- [ ] Jira URL en email correct
- [ ] Dry run uitgevoerd
- [ ] Dry run resultaten geverifieerd
- [ ] Jira project keys bestaan
- [ ] Rechten om issues aan te maken
- [ ] **Ready to go!**

---

## 🎉 Voordelen

✅ **Volledig Geautomatiseerd** - Geen handmatig werk  
✅ **Intelligente Grouping** - Logische werkblokken  
✅ **Juiste Projecten** - Automatische mapping  
✅ **Snel** - 426 entries in ~11 minuten  
✅ **Veilig** - API tokens, error handling  
✅ **Flexibel** - Daily/weekly grouping  
✅ **Betrouwbaar** - Dry run optie eerst  

---

**Dit is de ultieme oplossing!** 🚀

Geen handmatig issues aanmaken, geen CSV uploaden, alles 100% geautomatiseerd!








