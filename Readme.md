# Carousel

Manage image carousels from the Thelia back-office: slides are organized by **group**, each slide carries a
**desktop image** and an optional **mobile image** (rendered through `<picture>`), a link with target, a CTA
label, and an optional **publication window** (start/end dates). The configuration screen provides drag & drop
ordering, per-slide edition, and a live preview of the default front rendering in desktop and mobile widths.

> **Thelia 3 branch** (module 3.0.0): requires Thelia 3 with the Twig back-office
> (`active-admin-template = default-twig`). For Thelia 2, use the 2.x releases.

## Installation

* Copy the module into `<thelia_root>/local/modules/` (the directory must be named `Carousel`), or require it
  with composer.
* Activate it in the administration panel. Updating from 2.x runs `Config/update/3.0.0.sql` automatically
  (new columns `mobile_file`, `link_target`, `button_label` — existing slides keep working, the mobile image
  simply falls back to the desktop one).

## Back-office

The configuration screen lives on the module page (`/admin/module/Carousel`, also linked from the Tools menu):

* one table per group, ordered by drag & drop;
* per-slide edit page: content (title, chapo, description with WYSIWYG, postscriptum), link + CTA label,
  desktop/mobile images with instant preview, visibility toggle and publication window, per-language edition;
* preview card per group rendering the actual front template inside an iframe, switchable between desktop and
  mobile widths.

## Front-office integration (to do in your theme)

The module ships **no automatic front insertion**: the theme decides where carousels appear. Two options:

### 1. Theme hook (default markup provided by the module)

```twig
{{ theme_hook('carousel', { group: 'home' }) }}
{# shorthand: theme_hook('carousel.home') — optional autoplay in ms: #}
{{ theme_hook('carousel', { group: 'home', autoplay: 5000 }) }}
```

The default template (`templates/theme-hook/carousel.html.twig`) is self-contained: CSS scroll-snap track,
`<picture>` with the mobile image under 768px, caption with title/chapo/description/CTA, prev/next buttons and
optional autoplay (vanilla JS, honours `prefers-reduced-motion`). Override it from your theme, or swap it for a
library such as Swiper if you need more (thumbnails, fade transitions…).

### 2. API resource (build your own markup)

```twig
{% set slides = resources('/api/front/carousels', {
    group: 'home',
    published: 1,
    'order[position]': 'asc',
}) %}
```

`GET /api/front/carousels` returns the slides with processed image URLs (`imageUrl`, `mobileImageUrl` — resize
them with optional `width`/`height` query parameters), the link (`url`, `linkTarget`), the position, and the
translations (`i18ns.<locale>.title|chapo|description|postscriptum|alt|buttonLabel`). The `published=1` filter
keeps only enabled slides currently inside their publication window. An authenticated
`GET /api/admin/carousels` variant exposes the raw fields as well (`file`, `mobileFile`, `disable`, `limited`,
`startDate`, `endDate`).

## Loop (deprecated)

The legacy `carousel` Smarty loop (same arguments as the `image` loop, plus `group` and
`filter_disable_slides`) still works for Smarty themes but is **deprecated since 3.0** and will be removed in
4.0 — use the theme hook or the API resource instead. Note: since 3.0 the loop no longer writes to the
database; the publication state is computed at read time.

## Data model

Table `carousel`: `file`, `mobile_file`, `group`, `position` (unique per group, managed by drag & drop),
`url`, `link_target`, `disable`, `limited` + `start_date`/`end_date`, timestamps.
Table `carousel_i18n`: `alt`, `title`, `chapo`, `description`, `postscriptum`, `button_label`.
