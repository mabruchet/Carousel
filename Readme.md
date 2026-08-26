# Carousel

> **Thelia 3 users**: the Thelia 3 rewrite of this module lives in a new module,
> [ImageGallery](https://github.com/mabruchet/ImageGallery). This repository targets **Thelia 2**.

Manage image carousels from the Thelia 2 back-office: slides are organized by **group**, each slide carries
a **desktop image** and an optional **mobile image** (rendered through `<picture>`), a link with target, a
localized CTA label, and an optional **publication window** (start/end dates). The 2.7.0 back-office is a
full redesign: one panel per group, drag & drop ordering, a dedicated edition page per slide, per-group
preview — and, most importantly, **extension points** so projects never need to fork the module again.

## Installation

Require `thelia/carousel-module` with composer (installs to `local/modules/Carousel`), then activate it in
the administration panel. Updating from 2.4+ applies `Config/update/2.7.0.sql` automatically (new columns
`mobile_file`, `link_target`, localized `button_label`, and the back-office extension hooks).

## Back-office

The configuration page (`/admin/module/Carousel`, also linked from the Tools menu) shows one panel per
group with a sortable slide table (drag & drop persists positions), a visibility toggle, a publication
window summary, a per-group preview (an iframe rendering the group through your **active front theme**,
with Desktop / Tablet / Mobile width presets — see [Back-office preview](#back-office-preview)) and a creation
dialog. Each slide is edited on a dedicated page: content (title, alt, chapo, WYSIWYG description,
postscriptum), link + target + button label, publication window, desktop and mobile image uploads.

Uploads are hardened: the stored extension derives from the server-detected MIME type, never from the
client file name.

## Front

- The default front template (`templates/frontOffice/default/carousel.html`, hook `home.body`) renders a
  Bootstrap carousel with `<picture>` (mobile source when `mobile_file` is set) and the CTA button.
- The `carousel` loop keeps its historical arguments and outputs, plus: `MOBILE_IMAGE_URL`, `LINK_TARGET`,
  `BUTTON_LABEL` (alias `LABEL_BUTTON` kept for backward compatibility). The publication window is now
  evaluated **in SQL** — the loop no longer writes the computed state back to the `disable` column at
  render time. New argument `backend_context` (deprecated alias `back_office_location`) tells extenders to
  skip their front filters.

## Back-office preview

The per-group **Preview** button opens a modal whose iframe loads the **front** route
`/carousel-preview/{group}`. The route is declared by the module itself, so it exists on any theme as
soon as the module is active; it requires an authenticated **admin session** (anyone else gets a 403).
The Desktop (1280 px) / Tablet (768 px) / Mobile (390 px) presets resize the iframe — the iframe width
is the real inner viewport, so your theme media queries apply — and reload it on each switch so JS
sliders re-mount cleanly at the new width. On a screen narrower than the selected preset (a 1366 px
laptop with the 1280 px Desktop preset, say), the preview scrolls horizontally inside its container
rather than being scaled down: the real viewport width wins over display comfort.

Because it is served by a front controller, the `carousel-preview` template is resolved in the
**active front theme first**, and only falls back to the module's own
`templates/frontOffice/default/carousel-preview.html` when the theme ships no override. The fallback is
a neutral, dependency-free page (CSS `scroll-snap` slider, `<picture>` with the mobile source below
768 px, title/chapo captions, "no published slide" message): the preview always works out of the box,
but it does not show your theme's real markup. Note that the mobile source is served under 768 px
(`max-width: 767px`), so the Tablet preset (768 px) shows the desktop asset — the standard Bootstrap
convention, where ≥ 768 px is already desktop.

### Make the preview render your real front

Create `carousel-preview.html` at the root of your active front theme
(`templates/frontOffice/<your-theme>/carousel-preview.html`) and reproduce your real carousel markup:

- **Standalone page** — the template is loaded in an iframe: output a full `<html>` document, do not
  extend your layout (no header / navigation / footer). Load only the assets your carousel needs
  (Encore entries, webfonts, icon sprite…).
- **The controller provides `$group`** — feed it to the `carousel` loop.
- **Publication filtering is your call** — `backend_context=1` also renders disabled and
  out-of-window slides (like the back-office list); use `backend_context=0 filter_disable_slides=1`
  to preview exactly what visitors currently see.
- **Eager-load the images** (`loading="eager"`, a real `src` rather than a `data-*-lazy` attribute):
  lazy-loading tricks tend to leave the first paint of the iframe blank.
- **No resize handling needed** — the back-office reloads the iframe at each width switch, so a
  slider that computes its dimensions on mount (Splide, Swiper…) re-mounts correctly.

Minimal skeleton:

```smarty
{* templates/frontOffice/<your-theme>/carousel-preview.html *}
<!DOCTYPE html>
<html lang="{lang attr="code"}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Carousel preview &mdash; {$group}</title>
    {* The entry bundling your carousel CSS *}
    {encore_entry_link_tags entry="app"}
</head>
<body>
    {ifloop rel="carousel.preview"}
        {* Your real carousel markup (Splide here, adapt to your theme) *}
        <div class="splide">
            <div class="splide__track">
                <ul class="splide__list">
                    {loop type="carousel" name="carousel.preview" group=$group backend_context=1 width=1436 height=412 resize_mode="crop"}
                        <li class="splide__slide">
                            <picture>
                                {if $MOBILE_IMAGE_URL}<source media="(max-width: 767px)" srcset="{$MOBILE_IMAGE_URL}">{/if}
                                <img src="{$IMAGE_URL}" alt="{$ALT}" loading="eager">
                            </picture>
                        </li>
                    {/loop}
                </ul>
            </div>
        </div>
    {/ifloop}
    {elseloop rel="carousel.preview"}
        <p>{intl l="No published slide in this group." d='carousel.bo.default'}</p>
    {/elseloop}
    {* The entry mounting the slider *}
    {encore_entry_script_tags entry="app"}
</body>
</html>
```

## Extending the module (no fork needed)

Four extension points cover what previously required forking:

1. **Form fields** — the per-slide forms have stable names: listen to
   `TheliaEvents::FORM_AFTER_BUILD . '.carousel_slide'` (or `.carousel_slide_creation`) and add your fields
   to the builder. Extra submitted values travel with the form data.
2. **Rendering** — Smarty hooks in the back-office templates:
   `carousel.configuration.top` / `carousel.configuration.bottom`,
   `carousel.slide-list.row-actions` (args: `slide_id`, `group`),
   `carousel.slide-edit.extra-fields` (args: `slide_id`, `group` — render inputs named
   `carousel_slide[<your_field>]`), `carousel.slide-edit.js`.
3. **Persistence** — business events (`Carousel\Event\CarouselEvents`): `carousel.slide.create|update|delete`
   (pre-save) and `carousel.slide.created|updated|deleted` (post-save). The `CarouselSlideEvent` carries the
   slide model and the COMPLETE validated form data (including your fields): persist them on the post-save
   events.
4. **Loop** — `carousel.loop.define_args` (add loop arguments), `carousel.loop.extend_criteria` (alter the
   Propel query — front filters, joins; check `isBackendContext()`), `carousel.loop.enrich_row` (add output
   variables).

The guide below wires the first three (plus the loop) together, end to end.

### How to add business fields to the slide edition form

This is **the** supported way for a project module to attach its own business data to a carousel slide.
The example below adds a single `my_field` choice to the slide edition page from a module named
`MyModule`. All four files are auto-discovered: as long as your module's `configureServices()` loads its
namespace with `->autowire(true)->autoconfigure(true)` (the default in a Thelia 2.6 module skeleton), you
have **nothing to declare in `config.xml`** — neither the subscribers nor the hook.

#### 1. Add the form field

The edition form has a stable name (`CarouselSlideForm::getName()` === `carousel_slide`), so
`FORM_AFTER_BUILD` is the extension point. Subscribe to it and push your field into the form builder:

```php
<?php
// local/modules/MyModule/FormExtend/CarouselSlideFormExtend.php

namespace MyModule\FormExtend;

use Carousel\Form\CarouselSlideForm;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Core\Translation\Translator;

class CarouselSlideFormExtend implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::FORM_AFTER_BUILD.'.'.CarouselSlideForm::getName() => [
                ['addMyFields', 64],
            ],
        ];
    }

    public function addMyFields(TheliaFormEvent $event): void
    {
        $translator = Translator::getInstance();

        $event->getForm()->getFormBuilder()
            ->add('my_field', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    $translator->trans('All customers', [], 'mymodule') => 'all',
                    $translator->trans('Members only', [], 'mymodule') => 'members',
                ],
                'label' => $translator->trans('Audience', [], 'mymodule'),
            ]);
    }
}
```

Notes:

- Keep `'required' => false` unless you really want to break every other code path that submits the form
  (slide creation, programmatic saves): a missing value must never invalidate the slide form.
- Use `CarouselSlideForm::getName()` instead of the literal `'carousel_slide'` — the constant follows the
  module.
- For a field that must also be filled at creation time, subscribe the same way to
  `TheliaEvents::FORM_AFTER_BUILD.'.'.CarouselCreateForm::getName()` (`carousel_slide_creation`).

#### 2. Render the field in the back-office

The slide edition template exposes an extension slot inside the `carousel_slide` form:
`{hook name="carousel.slide-edit.extra-fields" slide_id=$ID group=$GROUP}`. Implement a back-office hook,
load the current value from your own tables and render your template:

```php
<?php
// local/modules/MyModule/Hook/CarouselBackHook.php

namespace MyModule\Hook;

use MyModule\Model\MySlideDataQuery;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class CarouselBackHook extends BaseHook
{
    public static function getSubscribedHooks(): array
    {
        return [
            'carousel.slide-edit.extra-fields' => [
                'type' => 'back',
                'method' => 'onSlideEditExtraFields',
            ],
        ];
    }

    public function onSlideEditExtraFields(HookRenderEvent $event): void
    {
        $slideId = (int) $event->getArgument('slide_id');

        $myData = MySlideDataQuery::create()->findPk($slideId);

        $event->add($this->render('carousel-slide-edit-extra-fields.html', [
            'slide_id' => $slideId,
            'my_field' => $myData?->getMyField() ?? 'all',
        ]));
    }
}
```

```smarty
{* local/modules/MyModule/templates/backOffice/default/carousel-slide-edit-extra-fields.html *}
<div class="panel panel-default">
    <div class="panel-heading">{intl l="Audience" d='mymodule'}</div>
    <div class="panel-body">
        <div class="form-group">
            <label class="control-label">{intl l="Audience" d='mymodule'}</label>
            <select name="carousel_slide[my_field]" class="form-control">
                <option value="all" {if $my_field == 'all'}selected{/if}>{intl l="All customers" d='mymodule'}</option>
                <option value="members" {if $my_field == 'members'}selected{/if}>{intl l="Members only" d='mymodule'}</option>
            </select>
        </div>
    </div>
</div>
```

**The key constraint is the input name**: `carousel_slide[<field>]` (and
`carousel_slide[<field>][]` for a `multiple` field). It must match the *form name* of step 1, otherwise the
value never reaches the validated form data. The hook is rendered inside the module's `<form>`, so no extra
form tag is needed — and no `{form_field}` either, since your fields are not part of the module's template.

Two important points about hook wiring:

- The five hook **codes** are inserted in the `hook` table by the Carousel module itself
  (`Config/TheliaMain.sql` on install, `Config/update/2.7.0.sql` on update), as back-office hooks
  (`type = 2`) with `by_module = 0` — they are *not* owned by any module, which is exactly what makes them
  usable by yours.
- The `module_hook` row that binds **your** module to that hook is created automatically by Thelia's
  `RegisterHookListenersPass` on the next `cache:clear`. You never insert it by hand. If Carousel is not
  installed/updated (hook code missing from the `hook` table), the pass logs `Hook … is unknown` and
  silently skips your hook — check that first when nothing renders.

#### 3. Persist the value

`CarouselSlideEvent::getData()` returns a `ParameterBag` holding the **complete validated form data**,
including the fields added in step 1. Persist on the *post-save* events (`SLIDE_CREATED` / `SLIDE_UPDATED`),
where the slide already has an id and its native fields are written:

```php
<?php
// local/modules/MyModule/EventListener/CarouselMyModuleListener.php

namespace MyModule\EventListener;

use Carousel\Event\CarouselEvents;
use Carousel\Event\CarouselSlideEvent;
use MyModule\Model\MySlideData;
use MyModule\Model\MySlideDataQuery;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CarouselMyModuleListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CarouselEvents::SLIDE_CREATED => ['persistMyFields', 64],
            CarouselEvents::SLIDE_UPDATED => ['persistMyFields', 64],
            CarouselEvents::SLIDE_DELETED => ['cleanupMyData', 64],
        ];
    }

    public function persistMyFields(CarouselSlideEvent $event): void
    {
        $data = $event->getData();

        // Field absent from the submitted data (creation dialog, programmatic
        // save): leave the existing value untouched.
        if (!$data->has('my_field')) {
            return;
        }

        $slideId = $event->getSlide()->getId();

        $myData = MySlideDataQuery::create()->findPk($slideId)
            ?? (new MySlideData())->setCarouselId($slideId);

        $myData
            ->setMyField((string) $data->get('my_field'))
            ->save();
    }

    public function cleanupMyData(CarouselSlideEvent $event): void
    {
        MySlideDataQuery::create()
            ->filterByCarouselId($event->getSlide()->getId())
            ->delete();
    }
}
```

Notes:

- **Never add columns to the `carousel` / `carousel_i18n` tables.** Store your data in your own module's
  tables, keyed by the slide id (that is what makes the module updatable without a fork).
- Always guard with `$data->has(...)`: the creation form does not carry your edition fields, and other code
  paths may dispatch the events with a partial bag.
- For an array value (a `multiple` choice), read it through `$data->all()['my_field'] ?? []` — a
  `ParameterBag` is meant for scalars.
- `SLIDE_DELETED` is dispatched with an **empty** data bag: only `getSlide()` is meaningful there.
- `$event->getLocale()` gives the back-office edition locale, if your data is localized.
- The pre-save events (`SLIDE_CREATE` / `SLIDE_UPDATE`) are where the module writes its own native fields;
  use them only if you must alter the slide model *before* it is saved.

#### 4. (Optional) Filter or enrich the front loop

`carousel.loop.extend_criteria` hands you the `CarouselQuery` before execution — the place for front
filters. Skip it when `isBackendContext()` is true, or the back-office lists would be filtered too:

```php
use Carousel\Event\Loop\CarouselLoopCriteriaEvent;
use Carousel\Event\Loop\CarouselLoopRowEvent;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;

// …add to getSubscribedEvents():
//   CarouselEvents::LOOP_EXTEND_CRITERIA => ['applyFrontFilters', 64],
//   CarouselEvents::LOOP_ENRICH_ROW      => ['enrichRow', 64],

public function applyFrontFilters(CarouselLoopCriteriaEvent $event): void
{
    if ($event->isBackendContext()) {
        return;
    }

    $join = new Join();
    $join->addExplicitCondition('carousel', 'id', null, 'my_slide_data', 'carousel_id');
    $join->setJoinType(Criteria::LEFT_JOIN);

    $event->getQuery()
        ->addJoinObject($join, 'msd')
        ->where('msd.my_field IS NULL OR msd.my_field = ?', 'all', \PDO::PARAM_STR);
}

public function enrichRow(CarouselLoopRowEvent $event): void
{
    $myData = MySlideDataQuery::create()->findPk($event->getSlide()->getId());

    // New output variable, usable as $MY_FIELD in the front template.
    $event->getRow()->set('MY_FIELD', $myData?->getMyField() ?? 'all');
}
```

`CarouselLoopCriteriaEvent` also exposes the resolved loop arguments through `getLoopArguments()` /
`getLoopArgument('group')` (currently `group` and `filter_disable_slides`). `CarouselLoopRowEvent` gives
`getRow()`, `getSlide()` and `isBackendContext()`. A LEFT JOIN plus a `IS NULL OR …` condition keeps slides
that have no row in your table visible — prefer that to an INNER JOIN.

> **Real-world example.** The Scal project module implements exactly these four steps for three business
> fields (`display_when`, `is_cash`, `catalog_selection`), persisted in its own `scal_carousel_data` /
> `carousel_catalog` tables, with customer/catalog/delivery front filters on the loop:
> `local/modules/Scal/FormExtend/CarouselSlideFormExtend.php`,
> `local/modules/Scal/Hook/CarouselBackHook.php`,
> `local/modules/Scal/templates/backOffice/default/carousel-slide-edit-extra-fields.html` and
> `local/modules/Scal/EventListener/CarouselScalListener.php`. It replaces what used to be a fork of the
> module.

### Other extension slots

| Slot | Kind | Arguments / payload |
| --- | --- | --- |
| `carousel.configuration.top` / `carousel.configuration.bottom` | back-office hook | none — wrap the configuration page with your own panels |
| `carousel.slide-list.row-actions` | back-office hook | `slide_id`, `group` — add buttons to a slide row |
| `carousel.slide-edit.extra-fields` | back-office hook | `slide_id`, `group` — see the guide above |
| `carousel.slide-edit.js` | back-office hook | `slide_id` — inject scripts at the end of the edition page |
| `carousel_slide_creation` form | `FORM_AFTER_BUILD` | fields of the creation dialog (`CarouselCreateForm`) |
| `carousel.loop.define_args` | event (`CarouselLoopArgumentEvent`) | `getArguments()`: the loop `ArgumentCollection` |

## Data model

Table `carousel`: `file`, `mobile_file`, `group`, `position` (per group), `url`, `link_target`, `disable`,
`limited` + `start_date`/`end_date`, timestamps. Table `carousel_i18n`: `alt`, `title`, `chapo`,
`description`, `postscriptum`, `button_label`.
