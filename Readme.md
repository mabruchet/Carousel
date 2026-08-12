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

`GET /api/front/carousels` returns the slides with processed image URLs and `srcset` values (`imageUrl`,
`mobileImageUrl`, `imageSrcset`, `mobileImageSrcset` — see the next section), the link (`url`, `linkTarget`), the position, and the
translations (`i18ns.<locale>.title|chapo|description|postscriptum|alt|buttonLabel`). The `published=1` filter
keeps only enabled slides currently inside their publication window. An authenticated
`GET /api/admin/carousels` variant exposes the raw fields as well (`file`, `mobileFile`, `disable`, `limited`,
`startDate`, `endDate`).

## Responsive images (desktop / mobile)

### Approach: art direction + resolution switching

Each slide carries **one content** (title, texts, link) and **two visuals**: a mandatory **desktop image**
and an optional **mobile image**. This is *art direction*: the mobile visual can be a different framing
(portrait crop, zoom on the subject), not just a scaled-down copy of the desktop one. The browser picks the
variant through `<picture><source media="(max-width: …)">`.

Within each variant, *resolution switching* is handled by `srcset`/`sizes`: several widths of the same image
(desktop: 768 / 1280 / 1920&nbsp;w — mobile: 480 / 828&nbsp;w), generated on the fly by the Thelia image
cache (ratio-preserving resize). The browser downloads the one best suited to the display width and pixel
density.

### Storage

Table `carousel`: the `file` column holds the desktop file name, `mobile_file` the mobile one (`NULL` when
absent — fallback: the desktop image is served everywhere). Files live in `local/media/images/carousel/`,
named `<name>-<id>-desktop.<ext>` / `<name>-<id>-mobile.<ext>` (the variant suffix prevents the two images
of a slide from colliding on disk — see `CarouselSlideService::attachImage()`). The size variants are **not**
stored in the database: they are derived on demand from the source file by the image cache
(`/cache/images/carousel/…`).

### What the API returns

* `imageUrl` / `mobileImageUrl` — full-size URL, or resized when `?width=&height=` is passed;
* `imageSrcset` / `mobileImageSrcset` — ready-to-drop `srcset` attribute values (only filled on full-size
  renders, i.e. without `width`/`height` parameters).

`mobileImageSrcset` is `null` when the slide has no mobile image: in that case do **not** render the
`<source>` tag, the desktop `<img>` covers every viewport.

### Theme markup

Recommended pattern:

```twig
<picture>
    {% if slide.mobileImageSrcset %}
        <source media="(max-width: 1023px)" srcset="{{ slide.mobileImageSrcset }}" sizes="100vw">
    {% endif %}
    <img src="{{ slide.imageUrl }}" srcset="{{ slide.imageSrcset }}" sizes="100vw"
         alt="{{ slide.i18ns.alt }}" loading="lazy">
</picture>
```

The module's `theme_hook('carousel', …)` default template already applies this markup, and the
vallereuil-scierie theme ships a `{{ component('Flexy:Carousel', { group: 'home' }) }}` component built on
the same pattern.

### Adjusting the widths

The width sets are constants of `Service/CarouselPresenter.php` (`DESKTOP_SRCSET_WIDTHS`,
`MOBILE_SRCSET_WIDTHS`): adapt them if your theme breakpoints change. The `sizes` attribute must reflect the
actual rendered width — `100vw` for a full-width carousel, e.g. `50vw` if the carousel sits in a half-width
column.

## Loop (deprecated)

The legacy `carousel` Smarty loop (same arguments as the `image` loop, plus `group` and
`filter_disable_slides`) still works for Smarty themes but is **deprecated since 3.0** and will be removed in
4.0 — use the theme hook or the API resource instead. Note: since 3.0 the loop no longer writes to the
database; the publication state is computed at read time.

## Data model

Table `carousel`: `file`, `mobile_file` (desktop / optional mobile visuals, see « Responsive images » above),
`group`, `position` (unique per group, managed by drag & drop),
`url`, `link_target`, `disable`, `limited` + `start_date`/`end_date`, timestamps.
Table `carousel_i18n`: `alt`, `title`, `chapo`, `description`, `postscriptum`, `button_label`.
