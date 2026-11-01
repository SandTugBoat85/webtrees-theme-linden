# Changelog

## Unreleased

- New setting: show the site title over the banner, for pictures without lettering.
- New setting: let everyone choose a theme, or keep the theme menu for administrators.
  Everyone gets the menu unless you untick it (1.0 always kept it for administrators).

## 1.0.1 (2026-11-01)

- A second copy of the theme folder (for example an old one kept during an upgrade) no longer
  stops every page with a fatal error
- Images over 16 megapixels are refused with a clear message, rather than possibly running
  out of memory
- Home-screen name, short name and description are limited to 100, 30 and 200 characters
- Hardening: image addresses in the page's styles are escaped

## 1.0.0 (2026-11-01)

First release.

- Banner, icon, accent colour and home-screen name set in the control panel
- Link and button colours adjust so text stays readable on any accent colour
- Compact chart boxes, one-row footer, Source Sans 3 served locally
- Blocks with side-by-side columns stack on phones
- Only administrators see the theme menu
