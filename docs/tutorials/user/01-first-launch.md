---
sidebar_position: 1
title: Open Larping for the first time
description: Open Larping, find your way around the navigation, and confirm the game data is loading.
---

# Open Larping for the first time

A first look at Larping — where the app lives, what the navigation gives you, and how to tell it is wired up to OpenRegister so character data, skills, items, and events can load.

## Goal

By the end you will have opened the Larping app, recognised the dashboard and the left-hand navigation, and confirmed that the OpenRegister-backed lists (Characters, Skills, Items, Events, …) load.

## Prerequisites

- A Nextcloud account on an instance where the **Larping** app is installed and enabled.
- The **OpenRegister** app installed and enabled — Larping stores characters, skills, items, conditions, effects and events in OpenRegister, so it is a hard dependency.
- The Larping register and its 10 schemas imported. An admin runs this once from **Settings → Administration → Larping**, picking the data source per entity (see [Manage Larping settings](../admin/03-admin-settings.md)).

## Steps

1. Open the Nextcloud app menu in the top bar and pick **Larping**. You land on the dashboard.

   ![Larping dashboard](/screenshots/tutorials/user/01-first-launch-01.png)

2. Read the dashboard KPI cards — *Characters*, *Events*, *Items*, *Players*. On a fresh install they read `0`; they fill in as the game master and players populate the setting. Below the cards are *Recent characters*, *Recent events*, and a *Skill usage* donut chart.

   ![Dashboard KPI cards](/screenshots/tutorials/user/01-first-launch-02.png)

3. Open the left-hand navigation. The entries map one-to-one onto the things Larping tracks: **Characters**, **Players**, **Items**, **Conditions**, **Events**. Below the divider sit the rule-engine entries — **Abilities**, **Skills**, **Effects** — and finally **Game settings**.

   ![Larping navigation](/screenshots/tutorials/user/01-first-launch-03.png)

4. Click **Characters**. The list view opens with a *Cards / Table* toggle, an **Add Item** button, and a search sidebar. An empty install shows *No items found* — expected until someone creates the first character.

   ![Characters list, empty state](/screenshots/tutorials/user/01-first-launch-04.png)

## Verification

You are set up correctly when: the Larping dashboard renders without an error banner, the left navigation lists the entries above, and clicking through to **Characters** (or any other list) shows either rows or a clean *No items found* state — not a load error.

## Common issues

| Symptom | Fix |
|---|---|
| "OpenRegister is not installed or enabled" banner | Install and enable the OpenRegister app, then reload Larping. |
| Lists load but **Add Item** opens a modal with no form fields | The Larping register import is incomplete — an admin re-runs the register configuration from **Settings → Administration → Larping**. |
| Larping is missing from the app menu | The app is not enabled for your account — ask an administrator to enable it (and check it is not restricted to a group you are not in). |
| Screenshots may be missing | App not yet installed in the test environment; rerun `npm run test:e2e:docs` once it is. |

## Reference

- [Create a character](02-create-character.md) — the first thing most players do once the app loads.
- [Manage Larping settings](../admin/03-admin-settings.md) — register import, per-entity data source.
- [Dashboard reference](../../features/dashboard.md) — what the KPI cards and widgets show.
