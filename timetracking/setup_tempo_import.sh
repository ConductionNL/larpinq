#!/bin/bash
#
# Tempo Import Setup Helper
# 
# Dit script helpt je door het setup proces
#

echo "======================================================================"
echo "TEMPO IMPORT - SETUP HELPER"
echo "======================================================================"
echo ""
echo "Dit script helpt je om de Tempo import te configureren."
echo "Je hebt nodig:"
echo "  1. Jira API token"
echo "  2. Tempo API token"
echo "  3. Je Jira URL"
echo "  4. Je email"
echo ""

# Check if requests is installed
echo "Checking Python dependencies..."
if python3 -c "import requests" 2>/dev/null; then
    echo "✓ requests library is installed"
else
    echo "✗ requests library NOT installed"
    echo ""
    read -p "Install now? (y/n): " INSTALL
    if [ "$INSTALL" = "y" ]; then
        echo "Installing requests..."
        pip3 install --user requests
        if [ $? -eq 0 ]; then
            echo "✓ requests installed successfully"
        else
            echo "✗ Failed to install requests"
            echo "Try manually: pip3 install --user requests"
            exit 1
        fi
    else
        echo "Please install manually: pip3 install --user requests"
        exit 1
    fi
fi
echo ""

# Check if tokens are set
echo "Checking API tokens..."
if [ -z "$JIRA_API_TOKEN" ]; then
    echo "✗ JIRA_API_TOKEN not set"
    NEED_JIRA=1
else
    echo "✓ JIRA_API_TOKEN is set"
    NEED_JIRA=0
fi

if [ -z "$TEMPO_API_TOKEN" ]; then
    echo "✗ TEMPO_API_TOKEN not set"
    NEED_TEMPO=1
else
    echo "✓ TEMPO_API_TOKEN is set"
    NEED_TEMPO=0
fi
echo ""

# Provide instructions if tokens not set
if [ $NEED_JIRA -eq 1 ] || [ $NEED_TEMPO -eq 1 ]; then
    echo "======================================================================"
    echo "API TOKENS REQUIRED"
    echo "======================================================================"
    echo ""
    
    if [ $NEED_JIRA -eq 1 ]; then
        echo "📋 HOW TO GET JIRA API TOKEN:"
        echo "  1. Go to: https://id.atlassian.com/manage-profile/security/api-tokens"
        echo "  2. Click 'Create API token'"
        echo "  3. Name: 'Tempo Import Script'"
        echo "  4. Copy the token"
        echo ""
    fi
    
    if [ $NEED_TEMPO -eq 1 ]; then
        echo "📋 HOW TO GET TEMPO API TOKEN:"
        echo "  1. Open Tempo in Jira"
        echo "  2. Go to Settings → API Integration"
        echo "  3. Click 'New Token'"
        echo "  4. Name: 'Auto Import Script'"
        echo "  5. Copy the token"
        echo ""
    fi
    
    echo "Once you have the tokens, set them:"
    echo ""
    echo "  export JIRA_API_TOKEN='your-jira-token'"
    echo "  export TEMPO_API_TOKEN='your-tempo-token'"
    echo ""
    echo "Then run this script again."
    echo ""
    
    read -p "Do you have the tokens now? Want to enter them? (y/n): " ENTER_TOKENS
    if [ "$ENTER_TOKENS" = "y" ]; then
        if [ $NEED_JIRA -eq 1 ]; then
            read -sp "Enter JIRA API TOKEN: " JIRA_TOKEN
            echo ""
            export JIRA_API_TOKEN="$JIRA_TOKEN"
            echo "✓ JIRA_API_TOKEN set for this session"
        fi
        
        if [ $NEED_TEMPO -eq 1 ]; then
            read -sp "Enter TEMPO API TOKEN: " TEMPO_TOKEN
            echo ""
            export TEMPO_API_TOKEN="$TEMPO_TOKEN"
            echo "✓ TEMPO_API_TOKEN set for this session"
        fi
        echo ""
    else
        exit 0
    fi
fi

# Get configuration
echo "======================================================================"
echo "CONFIGURATION"
echo "======================================================================"
echo ""

read -p "Jira URL (e.g., https://company.atlassian.net): " JIRA_URL
read -p "Your email (for Jira/Tempo): " EMAIL

echo ""
echo "======================================================================"
echo "READY TO TEST"
echo "======================================================================"
echo ""
echo "Configuration:"
echo "  Jira URL: $JIRA_URL"
echo "  Email: $EMAIL"
echo "  User: Ruben van der Linde"
echo ""
echo "We'll now run a DRY RUN to test everything."
echo "This will NOT create anything in Jira/Tempo."
echo ""

read -p "Continue with dry run? (y/n): " RUN_DRY
if [ "$RUN_DRY" != "y" ]; then
    echo "Cancelled."
    exit 0
fi

echo ""
echo "Running dry run..."
echo ""

python3 auto_tempo_import.py \
    --user "Ruben van der Linde" \
    --jira-url "$JIRA_URL" \
    --tempo-email "$EMAIL" \
    --dry-run

DRY_RUN_EXIT=$?

echo ""
echo "======================================================================"

if [ $DRY_RUN_EXIT -eq 0 ]; then
    echo "✓ DRY RUN SUCCESSFUL"
    echo "======================================================================"
    echo ""
    echo "Everything looks good! The dry run completed successfully."
    echo ""
    echo "Next steps:"
    echo ""
    echo "1. Review the output above"
    echo "2. Check if the project mappings are correct"
    echo "3. Verify the number of issues that would be created"
    echo ""
    echo "If everything looks good, you can run the LIVE import:"
    echo ""
    echo "  python3 auto_tempo_import.py \\"
    echo "    --user \"Ruben van der Linde\" \\"
    echo "    --jira-url \"$JIRA_URL\" \\"
    echo "    --jira-email \"$EMAIL\" \\"
    echo "    --tempo-email \"$EMAIL\" \\"
    echo "    --auto-create-issues"
    echo ""
    echo "⚠️  Remember: The live run will create issues in Jira!"
    echo ""
else
    echo "✗ DRY RUN FAILED"
    echo "======================================================================"
    echo ""
    echo "There was an error during the dry run."
    echo "Please check the error messages above."
    echo ""
    echo "Common issues:"
    echo "  - API tokens incorrect"
    echo "  - Jira URL wrong"
    echo "  - Email doesn't match Jira account"
    echo "  - Network connectivity"
    echo ""
fi

echo "======================================================================"







