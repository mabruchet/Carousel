$(function () {
    // The core module-configure template marks "Modules" as the sidebar location,
    // but this page is reached from Tools > "Edit your carousel": move the
    // highlight to the Tools menu. It has to happen in JS because the core
    // template sets admin_current_location = 'modules' server-side, and a hook
    // fragment renders in its own context, after the menu: it cannot change that
    // variable, so JS is the module's only non-invasive lever on this page.
    var $toolsMenu = $('#tools_menu');

    if ($toolsMenu.length) {
        $('#modules_menu').first().removeClass('active').children('a').removeClass('open');
        $('#collapse-modules').removeClass('in');
        $toolsMenu.addClass('active').children('a').addClass('open');
        $('#collapse-tools').addClass('in');
        $('#tools_menu_carousel').closest('li').addClass('active');
    }

    // Fill the slide id in the deletion dialog
    $('a.js-carousel-delete').click(function () {
        $('#carousel-delete-slide-id').val($(this).data('id'));
    });

    // Apply a viewport width to the preview iframe. The iframe width is the
    // real inner viewport, so the front theme media queries do apply.
    function carouselApplyPreviewWidth(width) {
        var iframe = document.getElementById('carousel-preview-iframe');

        if (iframe) {
            iframe.style.width = width;

            // The front theme slider computes its dimensions on mount, so a plain
            // resize leaves it broken: re-post the same src to force a remount.
            var src = iframe.getAttribute('src');

            if (src && src !== 'about:blank') {
                iframe.src = src;
            }
        }

        $('[data-carousel-preview-width]').each(function () {
            $(this).toggleClass('active', String($(this).data('carousel-preview-width')) === width);
        });
    }

    // Lazy-load the per-group preview iframe. The preview is served by a FRONT
    // route so the active front theme renders it (admin session required).
    $('a.js-carousel-preview').click(function () {
        var $iframe = $('#carousel-preview-iframe');
        $('.js-carousel-preview-group').text($(this).data('group'));
        // Reset the viewport switch, otherwise the previous modal state leaks.
        // Blank the iframe first so the reset does not reload the previous group.
        // The first preset button of the template is the default viewport, so the
        // template stays the single source of truth for the preset list.
        $iframe.attr('src', 'about:blank');
        var defaultWidth = String($('.js-carousel-preview-widths [data-carousel-preview-width]').first().data('carousel-preview-width'));
        carouselApplyPreviewWidth(defaultWidth);
        // URL built by Smarty {url}: absolute, sub-directory installs included.
        $iframe.attr('src', $(this).data('preview-url'));
    });

    // Desktop / tablet / mobile viewport switch of the preview modal.
    $(document).on('click', '[data-carousel-preview-width]', function (event) {
        event.preventDefault();
        carouselApplyPreviewWidth(String($(this).data('carousel-preview-width')));
    });

    // ---- Client-side filter bar (search / group / visibility state) ----
    // Everything is filtered in the browser: the lists are short and a reload
    // would lose the sortable, the modals and the sidebar highlight above.
    var $carouselFilterSearch = $('#carousel-filter-search');
    var $carouselFilterGroup = $('#carousel-filter-group');

    // The active button of the btn-group is the current state, so the template
    // stays the single source of truth for the available states.
    function carouselCurrentFilterState() {
        var $active = $('.js-carousel-filter-states .btn.active').first();

        return $active.length ? String($active.data('carousel-filter-state')) : 'all';
    }

    function carouselApplyFilters() {
        var search = $.trim(String($carouselFilterSearch.val() || '')).toLowerCase();
        var group = String($carouselFilterGroup.val() || '__all__');
        var state = carouselCurrentFilterState();

        $('.carousel-group-panel').each(function () {
            var $panel = $(this);
            var matchingRows = 0;

            if (group !== '__all__' && String($panel.attr('data-carousel-group')) !== group) {
                $panel.hide();
                return;
            }

            $panel.find('tr[data-slide-id]').each(function () {
                var $row = $(this);
                var title = $.trim($row.find('.js-carousel-title').text()).toLowerCase();
                var slideId = String($row.attr('data-slide-id'));
                var matchesSearch = search === ''
                    || title.indexOf(search) !== -1
                    || slideId.indexOf(search) !== -1;
                // The visibility toggle is green (btn-success) when the slide is
                // shown, and neutral (btn-default) when it is disabled.
                var isVisible = $row.find('[data-testid^="carousel-toggle-"]').hasClass('btn-success');
                var matchesState = state === 'all'
                    || (state === 'visible' ? isVisible : !isVisible);
                var matches = matchesSearch && matchesState;

                $row.toggle(matches);

                if (matches) {
                    matchingRows++;
                }
            });

            // A panel whose rows are all filtered out is hidden as a whole.
            $panel.toggle(matchingRows > 0);
        });
    }

    $carouselFilterSearch.on('input', carouselApplyFilters);
    $carouselFilterGroup.on('change', carouselApplyFilters);

    $(document).on('click', '[data-carousel-filter-state]', function (event) {
        event.preventDefault();
        $('.js-carousel-filter-states .btn').removeClass('active');
        $(this).addClass('active');
        carouselApplyFilters();
    });

    // Drag & drop ordering, one sortable per group table.
    // Same pattern as the core image-upload.js sortable.
    if ($.fn.sortable) {
        $('.js-carousel-sortable').sortable({
            handle: '.js-carousel-handle',
            helper: function (e, tr) {
                var originals = tr.children();
                var helper = tr.clone();
                helper.children().each(function (index) {
                    $(this).width(originals.eq(index).width());
                });
                return helper;
            },
            update: function (event, ui) {
                var position = ui.item.index() + 1;
                var slideId = ui.item.data('slide-id');
                // Declared before the post: the URL is carried by the tbody itself.
                var $tbody = ui.item.closest('.js-carousel-sortable');

                $.post($tbody.data('update-position-url'), {
                    slide_id: slideId,
                    position: position
                }).always(function () {
                    // Renumber the visible position column
                    $tbody.find('tr').each(function (index) {
                        $(this).find('.js-carousel-position').text(index + 1);
                    });
                });
            }
        });
    }
});
