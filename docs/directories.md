# Project Directory Structure

This document provides an overview of the folder structure of the project and describes the purpose of each folder.

## Root Directory

- **docs/**: Contains documentation files for the project.
  - **styleguide.md**: Provides a general overview of styling decisions made during the development of the application.
  - **directories.md**: Describes the folder structure of the project (this file).

- **src/**: Contains the source code of the project.
  - **entities/**: Contains the entity classes and their related files.
    - **character/**: Contains character-related entity files.
      - **character.ts**: Defines the `Character` class.
      - **character.mock.ts**: Provides mock data for `Character`.
      - **character.types.ts**: Defines TypeScript types for `Character`.
    - **effect/**: Contains effect-related entity files.
      - **effect.ts**: Defines the `Effect` class.
      - **effect.mock.ts**: Provides mock data for `Effect`.
      - **effect.types.ts**: Defines TypeScript types for `Effect`.
    - **event/**: Contains event-related entity files.
      - **event.ts**: Defines the `Event` class.
      - **event.mock.ts**: Provides mock data for `Event`.
      - **event.types.ts**: Defines TypeScript types for `Event`.
    - **condition/**: Contains condition-related entity files.
      - **condition.ts**: Defines the `Condition` class.
      - **condition.mock.ts**: Provides mock data for `Condition`.
      - **condition.types.ts**: Defines TypeScript types for `Condition`.
    - **item/**: Contains item-related entity files.
      - **item.ts**: Defines the `Item` class.
      - **item.mock.ts**: Provides mock data for `Item`.
      - **item.types.ts**: Defines TypeScript types for `Item`.
    - **player/**: Contains player-related entity files.
      - **player.ts**: Defines the `Player` class.
      - **player.mock.ts**: Provides mock data for `Player`.
      - **player.types.ts**: Defines TypeScript types for `Player`.
    - **skill/**: Contains skill-related entity files.
      - **skill.ts**: Defines the `Skill` class.
      - **skill.mock.ts**: Provides mock data for `Skill`.
      - **skill.types.ts**: Defines TypeScript types for `Skill`.

  - **navigation/**: Contains navigation-related components.
    - **MainMenu.vue**: Defines the main navigation menu.
    - **Configuration.vue**: Defines the configuration settings for the application.

  - **sidebars/**: Contains sidebar components.
    - **SideBars.vue**: Placeholder for all sidebars.
    - **directory/**: Contains directory-related sidebar components.
      - **DirectorySideBar.vue**: Defines the sidebar for directory listings.
    - **dashboard/**: Contains dashboard-related sidebar components.
      - **DashboardSideBar.vue**: Defines the sidebar for the dashboard.

  - **views/**: Contains view components for different sections of the application.
    - **characters/**: Contains character-related view components.
      - **CharactersList.vue**: Defines the list view for characters.
      - **CharacterDetails.vue**: Defines the detail view for a character.
    - **effects/**: Contains effect-related view components.
      - **EffectsList.vue**: Defines the list view for effects.
      - **EffectsIndex.vue**: Defines the index view for effects.
      - **EffectDetails.vue**: Defines the detail view for an effect.
    - **items/**: Contains item-related view components.
      - **ItemsList.vue**: Defines the list view for items.
    - **players/**: Contains player-related view components.
      - **PlayerDetails.vue**: Defines the detail view for a player.
    - **skills/**: Contains skill-related view components.
      - **SkillsList.vue**: Defines the list view for skills.
    - **events/**: Contains event-related view components.
      - **EventsList.vue**: Defines the list view for events.
    - **auditTrail/**: Contains audit trail-related view components.
      - **ZaakEigenschappen.vue**: Defines the view for case properties.

  - **store/**: Contains the state management files.
    - **modules/**: Contains the store modules.
      - **condition.spec.js**: Contains tests for the condition store module.
    - **store.js**: Defines the main store configuration.

  - **App.vue**: The main application component.
  - **Dialogs.vue**: Placeholder for all dialogs.
  - **Views.vue**: Placeholder for all views.

- **public/**: Contains static assets for the project.
  - **img/**: Contains image files.
    - **app.svg**: The application icon.

- **tests/**: Contains test files for the project.

- **.gitignore**: Specifies files and directories to be ignored by Git.
- **.nvmrc**: Specifies the Node.js version to be used.
- **package.json**: Contains the project metadata and dependencies.
- **tsconfig.json**: Contains the TypeScript configuration.
- **webpack.config.js**: Contains the Webpack configuration.

## Configuration Files

- **psalm.xml**: Configuration file for the Psalm static analysis tool.
- **psalm copy.xml**: A copy of the Psalm configuration file.
- **composer-setup.php**: PHP script for setting up Composer.
- **appinfo/info.xml**: Contains metadata about the application for Nextcloud.

## Styles

- **css/**: Contains CSS files for styling the application.
  - **main.css**: The main CSS file for the application.

## Documentation

- **README.md**: The main readme file for the project.