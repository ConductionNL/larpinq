# PERSOONLIJK UREN OVERZICHT 2025 - Ruben van der Linde

## Samenvatting

**Periode:** 1 januari 2025 t/m 31 december 2025 (365 dagen)  
**Totaal aantal commits:** 4,119 commits  
**Repositories:** 5 repositories

### Totaal Uren

| Categorie | Uren | Dagen |
|-----------|------|-------|
| **Normale werktijd** (08:00-18:00 weekdagen) | 854.8 | 201 |
| **Overuren** (avonden/weekenden) | 932.1 | 223 |
| **TOTAAL** | **1,786.9** | **424** |

### Breakdown Overuren

| Type | Uren | Dagen |
|------|------|-------|
| Weekdag avonden (na 18:00) | 591.1 | 150 |
| Weekend werk | 341.0 | 73 |

### Verwachte vs Getrackte Uren

- **Verwachte werkuren** (40u/week, 5 weken vakantie): 1,885.2 uur
- **Getrackte normale uren** (uit commits): 854.8 uur
- **Coverage:** 45.3% van verwachte uren
- **Niet-getrackte uren:** 1,030.4 uur

De niet-getrackte uren kunnen bestaan uit:
- Vergaderingen en meetings
- Code reviews
- Planning en design work
- Documentatie (zonder commits)
- Testing en debugging (zonder commits)
- Andere niet-codeer activiteiten

### Gemiddeldes

- **Gemiddeld per werkdag:** 4.3 uur
- **Gemiddeld per week (normaal):** 16.4 uur
- **Gemiddeld per week (overuren):** 17.9 uur
- **Gemiddeld per week (totaal):** 34.3 uur

## Repository Verdeling

### Top 5 Repositories (naar aantal commits)

1. **openregister** - 3,032 commits (73.6%)
2. **opencatalogi** - 409 commits (9.9%)
3. **softwarecatalog** - 301 commits (7.3%)
4. **openconnector** - 246 commits (6.0%)
5. **docudesk** - 131 commits (3.2%)

## Werkpatronen

### Normale Werktijd (08:00-18:00 weekdagen)
- **201 dagen** met commits tijdens normale werktijd
- **Gemiddeld 4.3 uur per dag** (gebaseerd op commit patterns)
- Alle normale uren zijn op weekdagen (geen weekend werk tijdens kantooruren)

### Overuren Patroon
- **223 dagen** met overuren
- **150 avonden** (weekdagen na 18:00)
- **73 weekenden** 
- **Gemiddeld 4.2 uur per overuren sessie**

Dit patroon laat zien:
- Veel avondwerk (591.1 uur = 63% van overuren)
- Significant weekendwerk (341.0 uur = 37% van overuren)
- Bijna elke werkdag bevat óf normale uren óf overuren (of beide)

## Totaal Overzicht

### Jaarstatistieken 2025

- **Totaal gewerkte uren:** 1,786.9 uur
- **Totaal commits:** 4,119 commits
- **Dagen met commits:** 424 dagen (verschillende sessies kunnen overlap hebben)
- **Gemiddeld commits per dag:** 9.7 commits (op dagen met commits)
- **Repositories:** 5 actieve repositories

### Verdeling Normaal/Overuren

```
Normale uren:  854.8 uur (47.8%)
Overuren:      932.1 uur (52.2%)
              ─────────────────
Totaal:      1,786.9 uur
```

### Maandelijkse Verdeling (geschat)

- **Gemiddeld per maand:** 148.9 uur
- **Weken met commits:** ~52 weken
- **Gemiddeld per week:** 34.3 uur

## Methodologie

### Hoe worden uren berekend?

Uren zijn geschat op basis van git commit timestamps, **NIET** op basis van regels code.

**Berekeningsm ethode:**

1. **Base hours**: Minimum sessieduur
   - Normale tijd: 2 uur per sessie
   - Overuren: 3 uur per sessie

2. **Time span**: Werkelijke tijd tussen commits
   - Als commits meer uren beslaan, wordt die tijd gebruikt
   - Maximum: 8 uur (normaal), 6 uur (overuren)

3. **Commit frequency**: Intensiteit indicator
   - +0.5 uur per 10 commits
   - +0.5 uur per 20 commits (extra)

### Beperkingen

Deze cijfers zijn **schattingen** en houden geen rekening met:
- ❌ Tijd zonder commits (denken, plannen)
- ❌ Meetings en vergaderingen
- ❌ Code review activiteiten
- ❌ Documentatie schrijven
- ❌ Testing zonder commits
- ❌ Andere niet-codeer werkzaamheden

**Belangrijk:** Gebruik deze cijfers als hulpmiddel, niet als exacte tijdregistratie. Voor officiële urenadministratie moeten de cijfers handmatig worden geverifieerd en aangepast.

## Bestanden

Alle gedetailleerde gegevens zijn beschikbaar in:

```
timetracking/github_Ruben_van_der_Linde/
├── Ruben van der Linde_normal_time.csv    # CSV met alle normale werkdagen
├── Ruben van der Linde_overtime.csv       # CSV met alle overuren
└── Ruben van der Linde_summary.txt        # Gedetailleerde tekstuele samenvatting
```

Deze CSV bestanden kunnen direct geopend worden in Excel voor verdere analyse, filtering en visualisatie.

## Vergelijking met Vorige Periodes

Om trends te zien, kun je het script opnieuw draaien voor verschillende periodes:

```bash
# Q1 2025
python3 generate_github_user_tracking.py --github-user "Ruben van der Linde" \
  --start-date 2025-01-01 --end-date 2025-03-31

# Q2 2025
python3 generate_github_user_tracking.py --github-user "Ruben van der Linde" \
  --start-date 2025-04-01 --end-date 2025-06-30

# Etc...
```

## Conclusie

In 2025 heb je volgens git commit analyse:
- **1,787 uur** gewerkt (geschat uit commits)
- **4,119 commits** gemaakt over **5 repositories**
- **52% overuren** (avonden en weekenden)
- **45% coverage** van verwachte werkuren

De hoge overuren percentage suggereert veel avond- en weekendwerk. De 45% coverage is normaal voor ontwikkelaars omdat veel werk (meetings, reviews, planning) geen commits oplevert.

---

*Gegenereerd op: 31 december 2025*  
*Script: generate_github_user_tracking.py*  
*Voor vragen: zie GITHUB_USER_TRACKING_README.md*

