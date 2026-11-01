# Changelog

## 1.3.0 (2026-11-01)

- Print styles: pages print in black on white without buttons, tabs, filters or paging; the
  individual page prints in one column, and charts print sideways, scaled to fit.
- On phones the banner stops shrinking at 96 pixels high and is cropped instead, so lettering
  stays readable. A new setting chooses which part stays in view (left to right).
- SECURITY.md: how to report a security problem privately.

## 1.2.0 (2026-11-01)

- Chart pages use the whole window width; everything else keeps the page width.
- Six preset accent colours on the settings page, next to the colour picker.
- "Theme: Linden" in the footer, linking to Linden's home page, with a setting to turn it off.

## 1.1.0 (2026-11-01)

- The control panel tells you when a new version of Linden is available.

## 1.0.2 (2026-11-01)

- New setting: show the site title over the banner, for pictures without lettering.
- New setting: show parents' ages at each birth in chart boxes (off, as before).
- Pages are 1320px wide on screens 1400px and wider (1200px below that), as in webtrees' own themes.
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
