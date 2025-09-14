# Rapid Application Development - Nextcloud Apps Environment

**A personal project by Ruben van der Linde**

This repository is part of the "Rapid Application Development" initiative - a personal project run in spare time to create an optimal Cursor AI development environment for quickly building Nextcloud applications using AI assistance.

The repository manages multiple Nextcloud applications developed by Conduction as a multi-repository project structure using git submodules, specifically optimized for AI-assisted development workflows. It also e

## Project Vision

The Rapid Application Development project aims to:

- **AI-First Development**: Leverage Cursor AI capabilities for rapid Nextcloud app creation
- **Streamlined Workflow**: Minimize setup friction and maximize development velocity  
- **Best Practices**: Maintain code quality while accelerating development cycles
- **Multi-Repository Management**: Coordinate multiple related applications efficiently
- **Documentation-Driven**: Generate comprehensive docs alongside code using AI

This environment is designed to enable building production-ready Nextcloud applications with AI assistance in record time, while maintaining professional development standards.

## Conduction Applications

The following applications are managed as separate git repositories and included as submodules:

- **docudesk** - Document services (anonymisation, metadata enhancement)
- **larpingapp** - Custom app for LARP management
- **opencatalogi** - Cataloguing application
- **openconnector** - API endpoint creator
- **openregister** - Object-oriented datastore (all apps should use this for storage)
- **softwarecatalog** - Software catalog management
- **zaakafhandelapp** - Case handling application

## Repository Structure

```
apps-extra/
├── .gitignore                 # Ignores non-Conduction apps
├── README.md                  # This file
├── docudesk/                  # Git submodule
├── larpingapp/                # Git submodule
├── opencatalogi/              # Git submodule
├── openconnector/             # Git submodule
├── openregister/              # Git submodule
├── softwarecatalog/           # Git submodule
├── zaakafhandelapp/           # Git submodule
└── [other apps ignored]       # Non-Conduction apps (gitignored)
```

## Setup Instructions

### Initial Setup

1. Clone this repository:
   ```bash
   git clone <repository-url>
   cd apps-extra
   ```

2. Initialize and update all submodules:
   ```bash
   git submodule update --init --recursive
   ```

### Working with Submodules

#### Update all submodules to latest:
```bash
git submodule update --remote --recursive
```

#### Update a specific submodule:
```bash
cd openregister
git pull origin main
cd ..
git add openregister
git commit -m "Update openregister to latest"
```

#### Add a new submodule:
```bash
git submodule add <repository-url> <app-name>
```

### Development Workflow

1. **Working on individual apps**: Navigate to the app directory and work normally with git
2. **Updating the main repository**: After changes in submodules, commit the submodule reference updates in the main repository
3. **Deployment**: Use `git submodule update --init --recursive` to ensure all submodules are at the correct versions

## Non-Conduction Apps

The following apps are present in the development environment but are gitignored as they are not part of the Conduction development scope:

- circles
- files_pdfviewer
- hmr_enabler
- profiler
- recommendations
- viewer
- dsonextcloud
- opencatalog

## Development Environment

This multi-repository setup is designed for:
- **Environment**: WSL with Docker containers
- **Database**: MySQL accessible via Docker
- **API**: Nextcloud API endpoints
- **Documentation**: Docusaurus (in individual app `website/` folders)

## Notes

- Each Conduction app should maintain its own documentation in `website/docs/`
- Use single quotes (') instead of backticks (`) in documentation due to Docusaurus limitations
- All apps should use openregister for data storage
- Follow the coding standards defined in `.cursor/rules/`

## About This Project

**Rapid Application Development** is a personal initiative by Ruben van der Linde, developed during spare time to explore the intersection of AI-assisted development and rapid application prototyping. 

The project serves as both a practical development environment and an experiment in maximizing development velocity through AI collaboration, specifically focused on the Nextcloud ecosystem.

### Contact & Contributions

This is a personal learning project. While the code is open for reference, please note this represents individual experimentation with AI-assisted development workflows.

**Author**: Ruben van der Linde  
**Focus**: AI-assisted Nextcloud application development  
**Status**: Personal project / Experimental
