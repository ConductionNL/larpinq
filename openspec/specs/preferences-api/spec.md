---
status: done
---

# preferences-api Specification

## Purpose
Provides the per-user preference read/write surface of the app. It retrieves and persists per-user key/value preferences scoped to the authenticated user within the `pref_` namespace, sanitising keys to a safe charset, returning a default when unset, and clearing a preference when an empty value is supplied. Unauthenticated requests are rejected and invalid keys yield a bad-request response.
## Requirements
### Requirement: Get user preference (REQ-PREF-001)

The system SHALL return a stored per-user preference value for a given key (scoped to the current user), or a default when unset. The get-preference endpoint MUST require an authenticated user, MUST sanitize the requested key to a safe charset within the `pref_` namespace, and MUST return the stored value (or null when unset). An unauthenticated request MUST be rejected and an invalid key MUST yield a bad-request response.

@e2e exclude pure-backend HTTP API — PreferencesController.getPreference() is an IConfig-backed key/value endpoint consumed by shared nextcloud-vue widgets (CnSupportDialog "seen" flag); 401-auth-rejection and server-side sanitizeKey() behaviors have no browser-navigable UI surface and are covered by Newman/PHPUnit

#### Scenario: Unauthenticated read rejected
- **GIVEN** no logged-in user
- **WHEN** get-preference is called
- **THEN** the response MUST be 401 Unauthorized

#### Scenario: Key is sanitized
- **GIVEN** a key containing unsafe characters
- **WHEN** the value is read
- **THEN** only the sanitized key within the `pref_` namespace MUST be consulted

### Requirement: Set user preference (REQ-PREF-002)

The system SHALL persist a per-user preference value for a given key scoped to the authenticated user. The set-preference endpoint MUST require an authenticated user, MUST sanitize the key, MUST store a non-empty value, and MUST clear the value when an empty string is supplied; an unauthenticated request MUST be rejected and an invalid key MUST yield a bad-request response.

@e2e exclude pure-backend HTTP API — PreferencesController.setPreference() persists/clears IConfig user values via a state-mutating PUT; empty-value-clears (deleteUserValue) and sanitization persistence behaviors have no browser-navigable UI surface and are covered by Newman/PHPUnit

#### Scenario: Empty value clears the preference
- **GIVEN** an existing stored preference
- **WHEN** set-preference is called with an empty value
- **THEN** the preference MUST be deleted and null returned

