#!/bin/bash

# Setup script to convert existing Conduction app directories to git submodules
# This script should be run from the apps-extra directory

echo "Setting up multi-repository structure with git submodules..."

# Define the apps and their GitHub URLs
declare -A CONDUCTION_APPS=(
    ["docudesk"]="https://github.com/ConductionNL/DocuDesk"
    ["larpingapp"]="https://github.com/ConductionNL/LarpingNextApp"
    ["opencatalogi"]="https://github.com/ConductionNL/opencatalogi"
    ["openconnector"]="https://github.com/ConductionNL/OpenConnector"
    ["openregister"]="https://github.com/ConductionNL/openregister"
    ["softwarecatalog"]="https://github.com/ConductionNL/softwarecatalog"
    ["zaakafhandelapp"]="https://github.com/ConductionNL/ZaakAfhandelApp"
)

# Function to backup and convert an app to submodule
setup_submodule() {
    local app_name=$1
    local repo_url=$2
    
    echo "Processing $app_name..."
    
    if [ -d "$app_name" ]; then
        echo "  - Backing up existing $app_name directory"
        mv "$app_name" "${app_name}_backup_$(date +%Y%m%d_%H%M%S)"
    fi
    
    echo "  - Adding $app_name as git submodule"
    git submodule add "$repo_url" "$app_name"
    
    if [ $? -eq 0 ]; then
        echo "  - ✅ Successfully added $app_name as submodule"
    else
        echo "  - ❌ Failed to add $app_name as submodule"
        # Restore backup if submodule add failed
        if [ -d "${app_name}_backup_"* ]; then
            mv "${app_name}_backup_"* "$app_name"
            echo "  - Restored backup"
        fi
    fi
    
    echo
}

# Add initial files to git
echo "Adding initial files to git..."
git add .gitignore README.md setup-submodules.sh
git commit -m "Initial setup: Add gitignore, README, and setup script for multi-repository structure"

# Setup each Conduction app as a submodule
for app_name in "${!CONDUCTION_APPS[@]}"; do
    setup_submodule "$app_name" "${CONDUCTION_APPS[$app_name]}"
done

echo "Initializing and updating all submodules..."
git submodule update --init --recursive

echo
echo "🎉 Multi-repository setup complete!"
echo
echo "Next steps:"
echo "1. Review the backup directories and remove them once you're satisfied"
echo "2. Set up your remote repository and push:"
echo "   git remote add origin <your-repository-url>"
echo "   git push -u origin main"
echo
echo "To work with submodules:"
echo "- Update all: git submodule update --remote --recursive"
echo "- Update one: cd <app> && git pull origin main && cd .. && git add <app> && git commit"
echo
echo "Ignored (non-Conduction) apps:"
for dir in */; do
    app_name=${dir%/}
    if [[ ! " ${!CONDUCTION_APPS[@]} " =~ " $app_name " ]] && [ -d "$dir" ] && [[ "$app_name" != ".cursor" ]]; then
        echo "  - $app_name"
    fi
done
