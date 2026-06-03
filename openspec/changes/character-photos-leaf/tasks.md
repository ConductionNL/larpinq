---
status: draft
---

# Tasks

## 1. Integration wiring
- [ ] 1.1 Confirm the OR photos leaf is exposed via the integration registry (ADR-019)
- [ ] 1.2 Add the photos leaf host to `src/views/ObjectDetail.vue` for the `character` object type

## 2. Image storage
- [ ] 2.1 Store portrait images via the OR files / object-interactions abstraction (no app-local image column)

## 3. Graceful degradation
- [ ] 3.1 Omit the portrait widget when the photos leaf / registry is unavailable

## 4. Tests
- [ ] 4.1 Frontend test: photos leaf renders and accepts an upload
- [ ] 4.2 Frontend test: widget hidden when leaf absent
