# 2.7.0

Back-office UX redesign + extension points. Breaking changes are flagged **[BC]**.

## Added
- Mobile image variant per slide (`carousel.mobile_file`, optional), link target and localized button label (`carousel_i18n.button_label`) — rendered through `<picture>` and the CTA button in the default front template.
- Redesigned back-office: one panel per group, drag & drop ordering, per-slide edition page (WYSIWYG, publication window), per-group preview iframe, CSRF-protected toggle/delete.
- Extension points so projects no longer fork the module: stable per-slide form names (`carousel_slide`, `carousel_slide_creation`) usable with `FORM_AFTER_BUILD`; Smarty hooks `carousel.configuration.top|bottom`, `carousel.slide-list.row-actions`, `carousel.slide-edit.extra-fields`, `carousel.slide-edit.js`; business events `carousel.slide.create|update|delete(d)`; loop events `carousel.loop.define_args|extend_criteria|enrich_row`.
- Secure uploads: stored extension derived from the server-detected MIME type.
- Loop: new outputs `MOBILE_IMAGE_URL`, `LINK_TARGET`, `BUTTON_LABEL` (+ `LABEL_BUTTON` alias), new `id` and `backend_context` arguments.

## Changed
- **[BC]** The all-slides form (`carousel_update`, fields suffixed by slide id) and its `/admin/module/carousel/update` route are replaced by per-slide edition (`/admin/module/carousel/edit/{slideId}`) and dedicated routes (create, per-variant image upload, update-position, toggle, delete, preview).
- **[BC]** The publication window (`limited` + dates) is evaluated in SQL at read time: the loop no longer writes the computed state back to the `disable` column when rendering.

# 2.3.0-alpha1

- Moved the images from the directory 'media' in the module to thelia/local/media/images/carousel.
- The current images will be automatically copied in the new directory during the update of the module
- Removed AdminIncludes directory
- All html,js and css files are now in 'templates'