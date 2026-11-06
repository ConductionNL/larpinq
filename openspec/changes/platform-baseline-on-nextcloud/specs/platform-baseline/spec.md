# platform-baseline Specification

## Purpose

What larpinq takes from the Nextcloud instance and the OpenRegister it runs on: sign-in, the second factor, who may open the app, organisation scoping, the data API, hosting and the interface language.

## ADDED Requirements

### Requirement: REQ-PLB-001 An administrator limits the app to one Nextcloud group

larpinq SHALL use Nextcloud's own app group restriction to decide who sees the app. An administrator SHALL set it on the Apps admin page or with `occ app:enable larpinq` and a groups flag. larpinq SHALL NOT keep a second access list. The group id `larpers` SHALL stay the documented default and SHALL NOT move with the app id. (adm-access-group)

#### Scenario: A user outside the group does not see the app

- GIVEN the administrator restricted larpinq to the group `larpers`
- WHEN Sem, who is not in `larpers`, signs in
- THEN the navigation shows no larpinq entry
- AND opening /apps/larpinq/ returns no larpinq page to him

### Requirement: REQ-PLB-002 A second sign-in factor is enforced by Nextcloud

larpinq SHALL keep no login and no second factor of its own. When the administrator enforces two-factor authentication (an authenticator app, a security key), a user SHALL pass that factor before any larpinq page or API route answers. (adm-two-factor)

#### Scenario: An organiser without the factor is stopped

- GIVEN an instance that enforces two-factor authentication for the group `gamemasters`
- WHEN game master Anna signs in and has not entered her authenticator code
- THEN no larpinq page or route returns data to her
- AND after she enters the code, larpinq opens as usual

### Requirement: REQ-PLB-003 Every request is scoped to the active organisation

When OpenRegister has organisations, larpinq SHALL send the active organisation with every object request, so one installation keeps the data of several LARP organisations apart. With no organisation context larpinq SHALL run in single-organisation mode. (adm-multi-organisation)

#### Scenario: Two organisations on one instance

- GIVEN organisations "De Zuiderlanden" and "Noordwacht" on one instance
- AND game master Anna is active in "De Zuiderlanden"
- WHEN she opens the character list
- THEN she sees only the characters of "De Zuiderlanden"
- AND every object request carries that organisation

### Requirement: REQ-PLB-004 Other tools reach the data through the OpenRegister API

larpinq SHALL keep its data in its OpenRegister register, so other tools reach it through OpenRegister's object API with the same access rules as the app. larpinq SHALL NOT add a data endpoint of its own for other tools. (adm-api)

#### Scenario: A script reads the characters

- GIVEN an account that may read characters
- WHEN a script calls OpenRegister's object API for the `larpinq` register and the character schema
- THEN it gets the characters that account may read
- AND an account without read rights gets none

### Requirement: REQ-PLB-005 larpinq runs on any self-hosted Nextcloud with OpenRegister

larpinq SHALL install as a standard Nextcloud app on any self-hosted Nextcloud within the versions `appinfo/info.xml` declares, with OpenRegister as its only required app. It SHALL store its records only in that instance and need no service the supplier runs. (adm-self-hosted)

#### Scenario: A LARP group installs larpinq on its own server

- GIVEN a Nextcloud on the group's own server with OpenRegister installed
- WHEN the administrator enables larpinq
- THEN larpinq imports its register and opens
- AND no call goes to a supplier service

### Requirement: REQ-PLB-006 The interface follows the user's Nextcloud language

larpinq SHALL show its interface in the language of the user's Nextcloud account, and SHALL ship at least Dutch and English. A string without a translation SHALL fall back to English. (adm-multilingual)

#### Scenario: A Dutch account sees Dutch

- GIVEN player Fatima's Nextcloud language is Nederlands
- WHEN she opens the larpinq dashboard
- THEN the menu and page titles are in Dutch

### Requirement: REQ-PLB-007 A player switches the language on the phone pages

The phone pages SHALL offer a language switch with Nederlands and English, as LrTelefoon draws it. It SHALL default to the account language and SHALL save the choice as the user's Nextcloud language, so the rest of Nextcloud follows. (adm-multilingual)

#### Scenario: A player switches to English on a phone

- GIVEN player Sem opens his character sheet on a phone with his account in Nederlands
- WHEN he chooses English in the language switch
- THEN the page reloads in English
- AND his Nextcloud account language is English
