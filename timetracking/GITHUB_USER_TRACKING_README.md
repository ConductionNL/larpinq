# GitHub User Time Tracking

Dit script genereert een volledig uren overzicht voor een specifieke GitHub gebruiker door ALLE git repositories op het systeem te scannen, niet alleen de Conduction repositories.

## Verschil met het standaard script

- **`generate_time_tracking.py`**: Scant alleen voorgedefinieerde Conduction repositories en groepeert per medewerker
- **`generate_github_user_tracking.py`**: Scant ALLE repositories op het systeem voor één specifieke GitHub gebruiker

## Gebruik

### Basis Gebruik

Genereer een uren overzicht voor een specifieke GitHub gebruiker:

```bash
python3 generate_github_user_tracking.py --github-user "Ruben van der Linde" --start-date 2025-01-01 --end-date 2025-12-31
```

### Opties

- `--github-user NAME` (verplicht): De naam of email zoals deze in git commits voorkomt
- `--start-date YYYY-MM-DD`: Startdatum (default: 1 januari van het huidige jaar)
- `--end-date YYYY-MM-DD`: Einddatum (default: 31 december van het huidige jaar)
- `--search-paths PATH1 PATH2 ...`: Paden om te doorzoeken (default: veel voorkomende development directories)
- `--output-dir PATH`: Output directory (default: timetracking/github_{gebruiker}/)

### Voorbeelden

#### Heel 2025 voor Ruben van der Linde

```bash
python3 generate_github_user_tracking.py \
  --github-user "Ruben van der Linde" \
  --start-date 2025-01-01 \
  --end-date 2025-12-31
```

#### Specifieke periode met custom search paths

```bash
python3 generate_github_user_tracking.py \
  --github-user "Ruben van der Linde" \
  --start-date 2025-10-01 \
  --end-date 2025-12-31 \
  --search-paths /home/rubenlinde /var/www
```

#### Alleen een specifieke directory

```bash
python3 generate_github_user_tracking.py \
  --github-user "Ruben van der Linde" \
  --search-paths /home/rubenlinde/nextcloud-docker-dev
```

## Hoe werkt het?

1. **Repository Discovery**: Het script scant recursief alle opgegeven directories naar git repositories
2. **Commit Extraction**: Voor elk gevonden repository worden alle commits van de opgegeven gebruiker opgehaald
3. **Time Calculation**: Commits worden geanalyseerd en uren worden geschat op basis van timestamps en frequentie
4. **Output Generation**: CSV files en summaries worden gegenereerd

### Repository Scanning

Het script scant de volgende default locaties (als ze bestaan):
- `/home/rubenlinde/nextcloud-docker-dev`
- `/home/rubenlinde/workspace`
- `/home/rubenlinde/projects`
- `/home/rubenlinde/git`
- `/home/rubenlinde/repos`
- `/home/rubenlinde`

Uitgesloten directories (worden overgeslagen):
- `node_modules`, `.npm`, `.cache`, `.local`
- `.venv`, `venv`, `__pycache__`
- `.git/modules`, `.git/worktrees`
- `build`, `dist`, `target`, `vendor`

### Gebruikersnaam Matching

Het script zoekt naar commits waar de author name of email de opgegeven gebruikersnaam bevat. Bijvoorbeeld:

- `--github-user "Ruben van der Linde"` vindt:
  - Ruben van der Linde <ruben@conduction.nl>
  - Ruben van der Linde <rubenvdlinde@gmail.com>
  
- `--github-user "rubenvdlinde"` vindt:
  - rubenvdlinde@gmail.com
  - rubenvdlinde@users.noreply.github.com

**Tip**: Check eerst welke naam je gebruikt in git commits:

```bash
git log --all --pretty=format:"%an|%ae" | sort -u | grep -i ruben
```

## Output Files

Voor elke GitHub gebruiker worden 3 bestanden gegenereerd in `timetracking/github_{gebruiker}/`:

### 1. `{gebruiker}_normal_time.csv`
Normale werkuren (08:00-18:00 op weekdagen)

Kolommen:
- Date: Datum
- Day: Dag van de week
- Weekend: Ja/Nee
- Worked: Ja/Nee
- Hours: Aantal uren
- Commits: Aantal commits
- Start Time: Eerste commit tijd
- End Time: Laatste commit tijd
- Repositories: Repository namen
- Work Summary: Samenvatting van commits

### 2. `{gebruiker}_overtime.csv`
Overuren (voor 08:00, na 18:00, of in het weekend)

Zelfde kolommen als normal_time.csv

### 3. `{gebruiker}_summary.txt`
Gedetailleerde samenvatting met:
- Totaal aantal commits
- Top repositories by commit count
- Normale uren breakdown
- Overuren breakdown
- Verwachte vs getrackte uren
- Statistieken en gemiddeldes

## Voorbeeld Output

Voor Ruben van der Linde over 2025:

```
Total commits: 4119
Repositories with commits: 5

TOP REPOSITORIES BY COMMIT COUNT:
  openregister: 3032 commits
  opencatalogi: 409 commits
  softwarecatalog: 301 commits
  openconnector: 246 commits
  docudesk: 131 commits

NORMAL TIME BREAKDOWN:
  Total hours: 854.8
  Weekday hours: 854.8
  Weekend hours: 0.0

OVERTIME BREAKDOWN:
  Total hours: 932.1
  Weekday hours: 591.1
  Weekend hours: 341.0

TOTAL HOURS: 1,786.9
```

## Uren Berekening

De uren worden geschat op basis van git commit timestamps, NIET op basis van regels code:

1. **Base hours**: Minimale sessieduur
   - Normale tijd: 2 uur
   - Overuren: 3 uur

2. **Time span**: Werkelijke tijd tussen eerste en laatste commit
   - Als commits meer dan de base hours beslaan, wordt de werkelijke tijd gebruikt
   - Maximum: 8 uur (normaal), 6 uur (overuren)

3. **Commit frequency**: Meer commits = intensiever werk
   - +0.5 uur per 10 commits
   - +0.5 uur per 20 commits (extra bonus)

### Beperkingen

De berekening houdt GEEN rekening met:
- Tijd zonder commits (vergaderingen, planning, nadenken)
- Code review tijd
- Documentatie schrijven
- Testen zonder commits
- Ander niet-codeerwerk

**Belangrijk**: Dit script is bedoeld om te **helpen** bij het tracken van tijd, niet om handmatige tijdregistratie te vervangen. Controleer en pas de uren handmatig aan waar nodig.

## Vergelijking: Conduction vs Volledige GitHub Historie

Je kunt beide scripts gebruiken om verschillende perspectieven te krijgen:

### Voor team overzicht (alle Conduction medewerkers):
```bash
python3 generate_time_tracking.py --auto-detect-handles
```

### Voor persoonlijke volledige historie (alle repositories):
```bash
python3 generate_github_user_tracking.py --github-user "Ruben van der Linde"
```

## Performance

- Het scannen van repositories kan enkele minuten duren, afhankelijk van:
  - Aantal directories om te doorzoeken
  - Aantal repositories gevonden
  - Grootte van repositories
  
- Progress wordt getoond tijdens het scannen
- Timeout per repository: 30 seconden

## Tips

1. **Snellere scanning**: Specificeer exacte paden met `--search-paths` in plaats van hele home directory
2. **Meerdere namen**: Als je verschillende namen gebruikt in commits, draai het script meerdere keren en combineer de resultaten
3. **Excel formatting**: Open de CSV files in Excel en pas formatting toe (kleuren, filters, grafieken)
4. **Automatiseren**: Voeg het script toe aan een cron job voor wekelijkse/maandelijkse updates

## Troubleshooting

### Geen commits gevonden

Controleer de exacte naam in je git commits:

```bash
git log --all --pretty=format:"%an|%ae" | sort -u
```

En gebruik die exacte naam met `--github-user`.

### Te weinig repositories gevonden

Voeg specifieke paden toe met `--search-paths`:

```bash
python3 generate_github_user_tracking.py \
  --github-user "Your Name" \
  --search-paths /home /var/www /opt/projects
```

### Script duurt te lang

Beperk de search paden tot relevante directories:

```bash
python3 generate_github_user_tracking.py \
  --github-user "Your Name" \
  --search-paths /home/username/work
```

## Support

Voor vragen of problemen:
1. Check de generated summary.txt voor details
2. Verifieer git commit history met `git log`
3. Test met een kleinere date range eerst

