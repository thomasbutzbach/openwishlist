# OpenWishlist

**OpenWishlist** is a transparent, self-hostable wishlist application.

- **No** hidden affiliate links or tracking
- **Simple** and user-friendly
- **Modern** stack: PHP 8.2+, SSR + Vanilla JS, REST API

## Status
🧭 Planning & early development. See `docs/adr` for architecture decisions.

## Goals (MVP)
- Session-based auth (browser)
- Multiple wishlists per user (public/private)
- Wishes with title, URL, price, notes
- Images per wish: either external **link** or **local download**
- Admin area to edit configuration (no `.env`)
- REST API for integration

## License
OpenWishlist is free software, licensed under **GNU AGPLv3** (or later).
If you run a modified version as a network service, you must provide users
with the corresponding source code (Affero clause). See [LICENSE](LICENSE).
