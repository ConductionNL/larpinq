# realtime-updates Specification

## Purpose

Adopt the live-updates capability of `@conduction/nextcloud-vue` (>= 1.0.0-beta.212,
where `liveUpdatesPlugin` is installed by default on every `createObjectStore`
store) so app-local views that render OpenRegister-backed collections refresh
without a manual reload. The canonical realtime-updates contract (event keys
`or-collection-{register-slug}-{schema-slug}` and `or-object-{uuid}`, notify_push
transport with visibility-gated polling fallback, events as refetch hints only)
is owned by OpenRegister (`openregister/openspec/specs/realtime-updates/spec.md`);
this spec covers only Larpinq's frontend adoption.

## Requirements

### Requirement: Store-backed app-local views MUST subscribe to live collection updates

App-local views that fetch OpenRegister collections through the shared object
store MUST subscribe to the corresponding `or-collection-*` event keys via
`objectStore.subscribe(type)` while mounted, and MUST release the subscription
on destroy. Events are refetch hints only — the view MUST NOT apply event
payloads directly, but re-render from the store's refetched collection cache.

#### Scenario: Skill tree refreshes when a skill changes elsewhere

@e2e exclude Push-transport timing is not deterministically observable in e2e; the subscribe/unsubscribe lifecycle is covered by the shared library's unit tests and the wiring is exercised by the existing skill-tree e2e flows.

- **GIVEN** the skill-tree page is open and subscribed to the `skill` and `character` collections
- **WHEN** a skill or character object is created, updated or deleted by another session
- **THEN** the liveUpdatesPlugin refetches the affected collection with the last-used params and the tree re-renders from the fresh data without a manual reload

#### Scenario: Subscriptions are released on unmount

@e2e exclude Subscription-handle bookkeeping is internal state with no UI surface; covered by the shared library's unit tests.

- **GIVEN** the skill-tree page holds live collection subscriptions
- **WHEN** the user navigates away and the component is destroyed
- **THEN** every held subscription handle is released and any in-flight subscribe resolution is dropped via the epoch guard instead of leaking
