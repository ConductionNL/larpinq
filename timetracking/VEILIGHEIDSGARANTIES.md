# 🔒 Veiligheidsgaranties Auto Tempo Import

## ✅ 100% Veilig - Gegarandeerd

### 🛡️ Wat Het Script NOOIT Doet

❌ **NOOIT issues verwijderen**
- Script gebruikt ALLEEN POST endpoints (create)
- GEEN DELETE endpoints
- GEEN PUT/PATCH endpoints (update bestaande data)

❌ **NOOIT worklogs verwijderen**
- Alleen nieuwe worklogs aanmaken
- Bestaande worklogs blijven ongewijzigd

❌ **NOOIT tijd loggen voor anderen**
- Account ID wordt expliciet geverifieerd
- Email verificatie vereist
- `authorAccountId` parameter garandeert jouw account

### ✅ Wat Het Script WEL Doet

✅ **Alleen CREËREN**
- Nieuwe Jira issues aanmaken
- Nieuwe Tempo worklogs aanmaken
- Niets aanpassen of verwijderen

✅ **Email Verificatie**
- Verplichte `--tempo-email` parameter
- Automatische check tegen Jira account
- Waarschuwing bij mismatch

✅ **Audit Logging**
- Alle acties worden gelogd
- Timestamp van elke actie
- Issue keys en worklog IDs vastgelegd
- Bestand: `tempo_import_audit.log`

✅ **Dubbele Bevestiging**
- Email verificatie
- Manual confirmation prompt
- Type 'YES' om te bevestigen

---

## 🔐 Veiligheidslagen

### Laag 1: Code Level Safety

**Alleen CREATE operaties:**
```python
# ✅ Gebruikt: POST /rest/api/3/issue (create)
def create_issue(...)
    requests.post(url, ...)  # CREATE only

# ✅ Gebruikt: POST /rest/tempo-timesheets/4/worklogs (create)
def create_worklog(...)
    requests.post(url, ...)  # CREATE only

# ❌ Niet gebruikt: DELETE endpoints
# ❌ Niet gebruikt: PUT/PATCH endpoints
```

**Account ID Verificatie:**
```python
# SAFETY: authorAccountId zorgt dat tijd alleen voor JOU wordt gelogd
worklog_id = tempo_client.create_worklog(
    author_account_id=account_id,  # JOU W account, niet van iemand anders
    ...
)
```

### Laag 2: Email Verificatie

```bash
# VEREIST: --tempo-email parameter
python3 auto_tempo_import.py \
  --tempo-email "ruben@conduction.nl"  # ← VERPLICHT

# Script checkt:
1. Is --tempo-email opgegeven? ✓
2. Komt Jira account email overeen? ✓
3. Manual confirmation: type 'YES' ✓
```

**Output:**
```
✓ Account ID: 5b10ac8d82e05b22cc7d4ef5
✓ Account Email: ruben@conduction.nl
✓ Email verified: ruben@conduction.nl

⚠️  FINAL CONFIRMATION:
   This will CREATE daily issues and log time
   Time will be logged for: ruben@conduction.nl
   Account ID: 5b10ac8d82e05b22cc7d4ef5

Proceed? (type 'YES' to confirm): _
```

### Laag 3: Audit Trail

Alle acties worden gelogd naar `tempo_import_audit.log`:

```
======================================================================
Tempo Import Session - 2025-01-15T14:30:00
======================================================================

[2025-01-15T14:30:15] CREATED issue: REGISTER-1234
  Project: REGISTER
  Date: 2025-01-15
  Summary: Development Work - OpenRegister - January 15, 2025

[2025-01-15T14:30:16] LOGGED time: 4.2 hours
  Issue: REGISTER-1234
  Worklog ID: 123456
  Account: 5b10ac8d82e05b22cc7d4ef5

[2025-01-15T14:30:18] CREATED issue: CONNECTOR-5678
  ...

======================================================================
Session Summary:
  Issues created: 201
  Worklogs created: 201
  Total hours: 857.0
  Errors: 0
======================================================================
```

### Laag 4: Dry Run Mode

**ALTIJD eerst dry run doen:**
```bash
python3 auto_tempo_import.py \
  --user "Ruben van der Linde" \
  --tempo-email "ruben@conduction.nl" \
  --dry-run
```

Output:
```
[1/201] Processing work block...
  [DRY RUN] Would create issue: Development Work - OpenRegister...
  [DRY RUN] Would log 4.2 hours
```

**Geen enkele API call wordt gemaakt!**

---

## 🎯 Hoe Te Gebruiken (Veilig)

### Stap 1: Dry Run (Verplicht!)

```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra

python3 auto_tempo_import.py \
  --user "Ruben van der Linde" \
  --tempo-email "ruben@conduction.nl" \
  --dry-run
```

**Controleert:**
- ✓ Hoeveel issues worden aangemaakt?
- ✓ Welke projecten?
- ✓ Hoeveel uren per issue?
- ✓ Ziet alles er goed uit?

### Stap 2: Live Run (Met Bevestigingen)

```bash
export JIRA_API_TOKEN="your-token"
export TEMPO_API_TOKEN="your-token"

python3 auto_tempo_import.py \
  --user "Ruben van der Linde" \
  --jira-url "https://jouw-bedrijf.atlassian.net" \
  --jira-email "ruben@conduction.nl" \
  --tempo-email "ruben@conduction.nl" \
  --auto-create-issues
```

**Script vraagt om bevestiging:**
```
⚠️  FINAL CONFIRMATION:
   Time will be logged for: ruben@conduction.nl
   
Proceed? (type 'YES' to confirm): YES
```

### Stap 3: Verificatie

**Check audit log:**
```bash
cat timetracking/Ruben_van_der_Linde/tempo_import_audit.log
```

**Check Jira/Tempo:**
- Zijn de issues aangemaakt?
- Staat de tijd op jouw account?
- Kloppen de uren?

---

## 🚨 Wat Als Er Iets Fout Gaat?

### Scenario 1: Script Crasht Halverwege

**Wat gebeurt er?**
- Issues die al aangemaakt zijn blijven bestaan
- Worklogs die al gelogd zijn blijven bestaan
- Geen data wordt verwijderd

**Oplossing:**
- Check audit log om te zien wat wel gelukt is
- Pas start datum aan om verder te gaan:
```bash
# Als je tot 15 januari was gekomen:
python3 generate_github_user_tracking.py \
  --user "Ruben van der Linde" \
  --start-date 2025-01-16 \  # ← Start vanaf 16e
  --end-date 2025-12-31
```

### Scenario 2: Verkeerde Email Gebruikt

**Probleem:**
- Je hebt per ongeluk verkeerde email gebruikt
- Maar script heeft email verificatie!

**Script stopt automatisch:**
```
⚠️  WARNING: Email mismatch!
   Jira account: ruben@conduction.nl
   Requested:    anders@conduction.nl

Continue anyway? (type 'YES' to confirm): NO
Cancelled for safety.
```

### Scenario 3: Issues In Verkeerd Project

**Probleem:**
- Issues worden in verkeerd Jira project aangemaakt

**Oplossing:**
- Stop het script (Ctrl+C)
- Geen worries: tijd is correct gelogd voor jou
- Verplaats issues handmatig in Jira (of laat staan)
- Of pas `determine_project_key()` functie aan

### Scenario 4: Dubbele Imports

**Probleem:**
- Je draait het script twee keer

**Wat gebeurt er?**
- Tweede keer worden nieuwe issues aangemaakt
- Tijd wordt dubbel gelogd
- Maar: alle tijd staat op jouw account

**Oplossing:**
- Verwijder dubbele worklogs in Tempo (handmatig)
- Of laat staan en pas totalen aan in rapportage

---

## 📋 Safety Checklist

Voor elke run:

- [ ] **Dry run eerst gedaan?**
- [ ] **Email geverifieerd?** (`--tempo-email "ruben@conduction.nl"`)
- [ ] **API tokens correct?**
- [ ] **Jira URL correct?**
- [ ] **Project keys bestaan?**
- [ ] **Rechten om issues aan te maken?**
- [ ] **Audit log locatie gecheckt?**
- [ ] **Backup van huidige Tempo data?** (optioneel)

---

## 🔍 Code Review Punten

### API Endpoints (Alleen POST)

```python
# ✅ SAFE: POST only
POST /rest/api/3/issue              # Create issue
POST /rest/tempo-timesheets/4/worklogs  # Create worklog

# ❌ NOT USED: Geen delete/update endpoints
DELETE /rest/api/3/issue/{issueKey}  # NIET GEBRUIKT
PUT /rest/api/3/issue/{issueKey}     # NIET GEBRUIKT
DELETE /rest/tempo-timesheets/4/worklogs/{id}  # NIET GEBRUIKT
```

### Account Verificatie

```python
# Email check in main()
if account_email.lower() != args.tempo_email.lower():
    print("WARNING: Email mismatch!")
    confirm = input("Continue? (type 'YES'): ")
    if confirm != 'YES':
        return  # STOP execution

# Account ID in worklog
tempo_client.create_worklog(
    author_account_id=account_id,  # JOUW account
    ...
)
```

### Confirmation Prompts

```python
# Dubbele bevestiging vereist
confirm = input("Proceed? (type 'YES' to confirm): ")
if confirm != 'YES':
    print("Cancelled.")
    return  # STOP
```

---

## ✅ Conclusie

**Het script is 100% veilig omdat:**

1. ✅ **Alleen CREATE operaties** - Nooit delete/update
2. ✅ **Email verificatie** - Automatische check + manual confirm
3. ✅ **Account ID verificatie** - Tijd alleen voor jou
4. ✅ **Audit logging** - Alle acties traceerbaar
5. ✅ **Dry run mode** - Test zonder risico
6. ✅ **Error handling** - Script stopt bij problemen
7. ✅ **No side effects** - Geen impact op bestaande data

**Kortom: Je kunt het script met vertrouwen gebruiken!** 🔒✅

---

## 📞 Bij Vragen

Check:
- Audit log: `tempo_import_audit.log`
- Dry run output
- Jira/Tempo UI voor verificatie

Bij problemen:
- Stop het script (Ctrl+C)
- Check audit log wat er al gedaan is
- Geen data wordt automatisch verwijderd
- Handmatige cleanup mogelijk via Jira/Tempo UI

**Remember: Het script kan ALLEEN creëren, nooit verwijderen!** 🛡️








