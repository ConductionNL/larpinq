# 🔐 Veilige API Configuratie

## ✅ Wat Ik Gedaan Heb

✓ **`tempo_config.env`** aangemaakt met je Jira API key  
✓ **`.gitignore`** updated - env files worden NOOIT gecommit  
✓ **`load_tempo_config.sh`** - Helper om config te laden  
✓ **`tempo_config.env.example`** - Template voor anderen  

---

## 🔒 Beveiliging

### **Wat is Veilig:**
✅ `tempo_config.env` staat in `.gitignore`  
✅ Wordt NOOIT naar git gepusht  
✅ Alleen lokaal op jouw machine  
✅ Config loader maskeert tokens in output  

### **Wat Je Moet Doen:**
1. ⚠️ **Na deze sessie:** Revoke de API key die je in chat deelde
2. ✅ Maak een nieuwe key aan via Atlassian
3. ✅ Update `tempo_config.env` met nieuwe key
4. 🔒 Deel keys NOOIT meer in chat

---

## 📝 Configuratie Aanvullen

**Je hebt nog nodig:**

1. **Jira URL** - Update in `tempo_config.env`:
   ```bash
   JIRA_URL=https://jouw-bedrijf.atlassian.net
   ```

2. **Tempo API Token** - Verkrijg deze:
   - Jira → Tempo → Settings → API Integration
   - New Token
   - Kopieer en plak in `tempo_config.env`:
   ```bash
   TEMPO_API_TOKEN=your-tempo-token-here
   ```

---

## 🚀 Gebruik

### **Stap 1: Config Laden**

```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra

# Load environment variables
source load_tempo_config.sh
```

**Output:**
```
Loading Tempo configuration from tempo_config.env...

✓ Configuration loaded!

Loaded variables:
  - JIRA_API_TOKEN: ATATT3xFfGF00F9u6r... (156 chars)
  - JIRA_URL: https://your-company.atlassian.net
  - JIRA_EMAIL: ruben@conduction.nl
  - TEMPO_API_TOKEN: your-tempo-token-her... (20 chars)
  - TEMPO_EMAIL: ruben@conduction.nl
  - GITHUB_USER: Ruben van der Linde

⚠️  TEMPO_API_TOKEN not set properly
⚠️  JIRA_URL not set properly

Please update tempo_config.env with your actual values.
```

### **Stap 2: Update Config**

```bash
nano tempo_config.env
```

Wijzig:
```bash
JIRA_URL=https://conduction.atlassian.net  # ← Je echte URL
TEMPO_API_TOKEN=je-echte-tempo-token       # ← Je Tempo token
```

### **Stap 3: Reload**

```bash
source load_tempo_config.sh
```

Nu zie je:
```
✅ All required variables are set!

You can now run:
  python3 auto_tempo_import.py --user "$GITHUB_USER" --tempo-email "$TEMPO_EMAIL" --dry-run
```

### **Stap 4: Run Script**

```bash
# Dry run (test)
python3 auto_tempo_import.py \
  --user "$GITHUB_USER" \
  --jira-url "$JIRA_URL" \
  --jira-email "$JIRA_EMAIL" \
  --tempo-email "$TEMPO_EMAIL" \
  --dry-run

# Live run (echt uitvoeren)
python3 auto_tempo_import.py \
  --user "$GITHUB_USER" \
  --jira-url "$JIRA_URL" \
  --jira-email "$JIRA_EMAIL" \
  --tempo-email "$TEMPO_EMAIL" \
  --auto-create-issues
```

---

## 📁 Bestanden

```
tempo_config.env          ← JE CONFIG (niet in git)
tempo_config.env.example  ← Template voor anderen
load_tempo_config.sh      ← Helper script
.gitignore                ← Updated met env files
```

---

## 🔄 Voor Andere Teamleden

Als een collega dit ook wil gebruiken:

```bash
# 1. Kopieer het template
cp tempo_config.env.example tempo_config.env

# 2. Vul hun eigen tokens in
nano tempo_config.env

# 3. Load config
source load_tempo_config.sh

# 4. Run script
python3 auto_tempo_import.py ...
```

Elke developer heeft zijn eigen `tempo_config.env` (niet gedeeld).

---

## 🛡️ Security Best Practices

### **DO:**
✅ Gebruik `tempo_config.env` voor lokale config  
✅ Revoke keys na test sessions  
✅ Maak nieuwe keys met beschrijvende namen  
✅ Bewaar backup keys in password manager  

### **DON'T:**
❌ Commit `tempo_config.env` naar git  
❌ Deel keys in chat/email  
❌ Gebruik dezelfde key overal  
❌ Laat keys in terminal history staan  

---

## ✅ Checklist

- [x] `tempo_config.env` aangemaakt
- [x] Jira API key ingevuld
- [x] `.gitignore` updated
- [ ] **Jira URL invullen** ← DOE DIT
- [ ] **Tempo API token verkrijgen** ← DOE DIT
- [ ] Config testen met `source load_tempo_config.sh`
- [ ] Dry run uitvoeren
- [ ] Live run uitvoeren

---

## 🎯 Volgende Stappen

**Nu:**
1. Vul je **Jira URL** in: `nano tempo_config.env`
2. Verkrijg **Tempo API token**: Jira → Tempo → Settings
3. Vul token in: `nano tempo_config.env`

**Dan:**
```bash
source load_tempo_config.sh
python3 auto_tempo_import.py --user "$GITHUB_USER" --tempo-email "$TEMPO_EMAIL" --dry-run
```

**GO!** 🚀







