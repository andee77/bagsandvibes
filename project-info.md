# Project Info — Checked Bags & Good Vibes

## Overview
- **Site name:** Checked Bags & Good Vibes
- **Domain:** bagsandvibes.com
- **Brand owner:** JourneyWell Global LLC
- **Purpose:** Group travel and vacation planning platform
- **Platform:** WordPress
- **Hosting:** SiteGround
- **Local project folder:** `C:\Users\Andre\projects\bagsandvibes`

## Brand Colors
| Name | Role | Hex |
|---|---|---|
| Ink | Primary dark | `#16232B` |
| Horizon blue | — | `#1B3A4B` |
| Coral | Accent | `#FF6B4A` |
| Gold | Accent | `#E8A94E` |
| Sand cream | Light / text-on-dark | `#FBF3E7` |
| Palm teal | Secondary accent | `#2E7D6E` |

## Fonts
- **Fraunces** — display/headings (italic, warm vintage-postcard feel)
- **Work Sans** — body copy, nav
- **Space Mono** — labels, tags, small utility text

## Homepage — Scrollytelling Landing
The homepage uses a custom page template called **"Scrollytelling Landing"**, registered via an mu-plugin rather than a theme template:

- Registration: `wp-content/mu-plugins/checkedbags-landing.php`
- Template: `wp-content/mu-plugins/checkedbags-landing/template-scrollytelling.php`
- This template **bypasses Kadence's header/footer** for that page.
- Assets: `styles.css` and `app.js` live in `wp-content/uploads/checkedbags/`
- It's a **GSAP ScrollTrigger**-driven scrollytelling sequence.

**Important:** Do not refactor this into standard Kadence blocks — the custom template and bypass of Kadence's header/footer are intentional.
