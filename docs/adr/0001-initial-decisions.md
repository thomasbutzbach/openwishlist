# ADR-0001: Initial Decisions

**Status:** Accepted  
**Date:** 2025-08-08

## Context
We want a transparent, self-hostable wishlist app with a modern but simple stack, no hidden affiliate logic, and a clean architecture that can grow over time.

## Decision
- **Name:** OpenWishlist
- **Frontend:** Server-Side Rendering (SSR) with Vanilla JS; later we may add framework “islands” where needed.
- **Auth:** Session cookies for the browser; JWT only for future integrations.
- **Images:** Per-wish choice: `link` (remote) or `local` (downloaded & stored).
- **Configuration:** Editable in an Admin UI (DB-backed settings). Only bootstrap secrets in a local config file.
- **Architecture:** Light service-oriented approach (services + repositories; thin controllers).
- **DB:** MySQL/MariaDB first.

## Consequences
- Fast initial development with solid foundations.
- Easy to contribute to (clear separation, tests later).
- No vendor lock-in; self-hosting is the default path.
