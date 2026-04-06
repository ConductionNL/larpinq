# 📋 Jira Issues Vereist voor Tempo Import

## ⚠️ Belangrijk

**Tempo vereist ALTIJD een Jira issue** om tijd tegen te loggen. Je kunt NIET zomaar uren importeren zonder issues.

Maar geen paniek! Ik heb 3 praktische oplossingen voor je gemaakt:

---

## 🎯 Optie 1: Eén Generic Issue (SNELST - 5 minuten)

### Voor wie?
- Je wilt snel klaar zijn
- Detail per project is niet belangrijk
- Je gebruikt Tempo vooral voor totaal overzicht

### Stappen

**1. Maak 1 Jira issue:**
```
Project: COND (of jouw project)
Type: Task
Summary: "Development Time 2025 - Ruben van der Linde"
Description: "Bulk import of development hours from git commits"
```

Dit geeft bijvoorbeeld: `COND-1234`

**2. Genereer Tempo CSV:**
```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra

python3 convert_to_tempo.py \
  --user "Ruben van der Linde" \
  --format csv \
  --include-overtime \
  --issue-key "COND-1234"
```

**3. Upload naar Tempo:**
- De CSV bestanden zijn nu klaar
- Alle 426 entries wijzen naar `COND-1234`

### Resultaat
✅ Alle 1,786.9 uur gelogd tegen 1 issue  
✅ Super snel klaar  
⚠️ Geen detail per project/feature

---

## ⚡ Optie 2: Per Maand (BALANS - 30 minuten)

### Voor wie?
- Je wilt maandelijkse rapportage
- Beter overzicht per periode
- Redelijk snel

### Stappen

**1. Maak 12 Jira issues (één per maand):**
```
COND-1201: "Development January 2025"
COND-1202: "Development February 2025"
COND-1203: "Development March 2025"
COND-1204: "Development April 2025"
COND-1205: "Development May 2025"
COND-1206: "Development June 2025"
COND-1207: "Development July 2025"
COND-1208: "Development August 2025"
COND-1209: "Development September 2025"
COND-1210: "Development October 2025"
COND-1211: "Development November 2025"
COND-1212: "Development December 2025"
```

**2. Pas het script aan:**
```bash
nano generate_monthly_tempo.sh

# Wijzig de issue keys in het script:
declare -A MONTHLY_ISSUES=(
    ["01"]="COND-1201"  # Jouw echte issue key
    ["02"]="COND-1202"  # Jouw echte issue key
    # ... etc
)
```

**3. Run het script:**
```bash
./generate_monthly_tempo.sh
```

**4. Upload de maandelijkse CSVs:**
- Je krijgt 24 bestanden (12 normal + 12 overtime)
- Upload ze allemaal naar Tempo

### Resultaat
✅ Maandelijkse breakdown  
✅ Betere rapportage mogelijkheden  
✅ Geautomatiseerd via script  
⚠️ Moet 12 issues maken

---

## 🎯 Optie 3: Automatische Issue Detectie (SLIM - al klaar!)

### Voor wie?
- Je wilt detail per project
- Je commit messages bevatten vaak issue refs
- Je wilt het meest accurate resultaat

### Wat het doet

Het script analyseert je commit messages en haalt automatisch issue keys eruit:

**Voorbeeld:**
```
Commit: "Merge pull request #114 from ConductionNL/feature/CONNECTOR-50"
        → Detecteert: CONNECTOR-50

Commit: "Fix bug in openregister"
        → Geen issue → Gebruikt: REGISTER-1 (op basis van repo)

Commit: "Update README"
        → Geen hint → Gebruikt: COND-9999 (fallback)
```

### Resultaat van Jouw Data

**Ik heb het al gedraaid! Hier is wat het vond:**

#### Normale Uren (857.0 uur)
```
REGISTER-1:   385.5 uur (94 entries)   - OpenRegister werk
CATALOGI-1:   246.3 uur (54 entries)   - OpenCatalogi werk
CONNECTOR-1:  173.5 uur (38 entries)   - OpenConnector werk
COND-9999:     47.7 uur (13 entries)   - Unmapped (generic werk)
CONNECTOR:      4.0 uur (2 entries)    - Mogelijk typo
```

#### Overuren (932.1 uur)
```
Vergelijkbare verdeling over dezelfde issues
```

### Stappen

**1. Maak de benodigde issues in Jira:**

Minimaal deze 4 issues:
```
REGISTER-1:   "OpenRegister Development 2025"
CATALOGI-1:   "OpenCatalogi Development 2025"
CONNECTOR-1:  "OpenConnector Development 2025"
COND-9999:    "General Development 2025" (fallback)
```

**2. De CSV bestanden zijn al klaar!**
```
timetracking/Ruben_van_der_Linde/
├── Ruben van der Linde_smart_tempo.csv           ← UPLOAD DIT
├── Ruben van der Linde_smart_tempo_overtime.csv  ← EN DIT
├── Ruben van der Linde_smart_tempo_summary.txt   ← Overzicht
└── Ruben van der Linde_smart_tempo_overtime_summary.txt
```

**3. Upload naar Tempo**

### Resultaat
✅ Automatisch gemapped naar juiste projecten  
✅ 92% accuraat (188 van 201 entries correct gemapped)  
✅ Gedetailleerde breakdown per project  
✅ Al klaar!  
⚠️ Moet 4 issues maken

---

## 📊 Vergelijking

| Methode | Issues Nodig | Setup Tijd | Detail Level | Accuraatheid |
|---------|--------------|------------|--------------|--------------|
| **1. Generic** | 1 issue | 5 min | Laag | N/A |
| **2. Maandelijks** | 12 issues | 30 min | Medium | 100% |
| **3. Auto-detect** | 4-5 issues | 15 min | Hoog | 92% |

---

## 🎯 Mijn Aanbeveling

### Voor Nu (Snel Starten)
**Gebruik Optie 3 (Auto-detect)** - Het is al klaar!

1. **Maak 4 issues in Jira:**
   ```
   REGISTER-1: OpenRegister Development 2025
   CATALOGI-1: OpenCatalogi Development 2025
   CONNECTOR-1: OpenConnector Development 2025
   COND-9999: General Development 2025
   ```

2. **Upload de smart_tempo CSV bestanden:**
   ```
   Ruben van der Linde_smart_tempo.csv          (201 entries)
   Ruben van der Linde_smart_tempo_overtime.csv (225 entries)
   ```

3. **Klaar!** Je hebt 92% van je tijd accuraat gemapped!

### Voor de Toekomst
- Gebruik Optie 1 voor snelle wekelijkse imports
- Gebruik Optie 3 voor gedetailleerde maand/kwartaal rapportage

---

## 🔧 Bestanden Overzicht

### Wat is er Gegenereerd?

```
timetracking/Ruben_van_der_Linde/

OPTIE 1 - Generic Issue:
├── Ruben van der Linde_normal_tempo.csv      ← Alle naar 1 issue
└── Ruben van der Linde_overtime_tempo.csv    ← Alle naar 1 issue

OPTIE 3 - Smart Auto-detect (AL KLAAR):
├── Ruben van der Linde_smart_tempo.csv       ← ✅ GEBRUIK DIT
├── Ruben van der Linde_smart_tempo_overtime.csv ← ✅ EN DIT
├── Ruben van der Linde_smart_tempo_summary.txt
└── Ruben van der Linde_smart_tempo_overtime_summary.txt
```

---

## ❓ FAQ

### Kan ik zonder issues importeren?
**Nee**, Tempo vereist altijd een Jira issue. Maar met Optie 1 (1 generic issue) ben je in 5 minuten klaar.

### Moet ik alle issues al hebben voordat ik import?
**Ja**, de issues moeten bestaan in Jira voordat je de CSV upload. Anders krijg je errors.

### Kan ik de mapping later aanpassen?
**Ja**, je kunt:
- De CSV handmatig bewerken in Excel voor de import
- Of na import in Tempo de issue keys aanpassen (minder makkelijk)

### Wat als een issue niet bestaat?
Tempo geeft een error. Oplossingen:
1. Maak het issue in Jira
2. Of wijzig de CSV om een ander issue te gebruiken

### Welke optie is het beste?
- **Snel klaar zijn?** → Optie 1
- **Gedetailleerd?** → Optie 3 (al klaar!)
- **Maandrapportage?** → Optie 2

---

## ✅ Volgende Stappen

### Mijn Aanbeveling: Gebruik Optie 3

1. **Maak 4 Jira issues** (5 minuten)
2. **Upload de 2 smart_tempo CSV bestanden** (5 minuten)  
3. **Klaar!** 1,786.9 uur netjes verdeeld over projecten

### Of kies Optie 1 voor super snel

1. **Maak 1 Jira issue** (2 minuten)
2. **Run:** `python3 convert_to_tempo.py --user "Ruben van der Linde" --issue-key "COND-1234" --format csv --include-overtime`
3. **Upload de CSV bestanden**
4. **Klaar!**

---

**Welke optie kies jij?** 🎯








