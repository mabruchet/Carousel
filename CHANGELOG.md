# 3.0.0

Thelia 3 rewrite (Twig back-office required). Breaking changes are flagged **[BC]**.

## Added
- Desktop/mobile image per slide: new `carousel.mobile_file` column, rendered through `<picture>` (desktop fallback).
- Call-to-action: new i18n `button_label` column and `link_target` column (`_self`/`_blank`).
- API Platform resource: `GET /api/front/carousels` (+ `/api/admin/carousels`), filters `group`, `published`, `order[position]`, processed `imageUrl`/`mobileImageUrl` (optional `width`/`height`).
- Theme hook `carousel` (`{{ theme_hook('carousel', { group: 'home' }) }}`) with a self-contained default template (CSS scroll-snap, vanilla JS, `prefers-reduced-motion`).
- Back-office: one data table per group with drag & drop ordering, visibility toggle, publication badge (online/scheduled/expired/disabled), client-side filtering, per-slide edit page (WYSIWYG, language switcher, image previews, publication window validation), live preview per group with desktop/mobile widths.
- `CarouselSlideService` / `CarouselPresenter` service layer (transactions, position renumbering per group).
- Reusable `Carousel` Twig component (`{{ component('Carousel', { group: 'home' }) }}`), designed to be
  extended by themes (non-final class, protected dependencies — see Readme "Twig component").

## Changed
- **[BC]** The bulk edit form (`CarouselUpdateForm`, route `carousel.update`) is replaced by per-slide edition (`carousel.edit`/`carousel.save`) and dedicated routes (create, image upload per variant, update-position, toggle, delete).
- **[BC]** Back-office translation domain moved from `carousel.bo.default` to `carousel.bo.default-twig` (`I18n/backOffice/default-twig/`).
- **[BC]** The `carousel` loop no longer persists the computed publication state at read time; `filter_disable_slides` now also excludes slides outside their publication window (SQL filter). The loop is **deprecated**, removal planned in 4.0.
- Slide deletion is now CSRF-protected and removes both image variants from disk.

## Front integration (action required)
- **[BC]** The module no longer hooks `home.body`: the theme decides where carousels appear, through the `carousel` theme hook or the front API resource (see Readme).

# 2.3.0-alpha1

- Moved the images from the directory 'media' in the module to thelia/local/media/images/carousel.
- The current images will be automatically copied in the new directory during the update of the module
- Removed AdminIncludes directory
- All html,js and css files are now in 'templates'
