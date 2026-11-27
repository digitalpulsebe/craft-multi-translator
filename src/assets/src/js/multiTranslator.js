
(function (window) {
    const {Craft, Garnish, $} = window;

    /**
     * Craft.MultiTranslatorFieldModal
     *
     * Opens a modal whose body is fetched from multi-translator/field/review,
     * identical to the block translation modal pattern.
     *
     * Expected settings keys:
     *   elementId    {number}
     *   elementType  {string}
     *   sourceSiteId {number}
     *   fieldHandle  {string}
     */
    Craft.MultiTranslatorFieldModal = Garnish.Modal.extend({
        $container: null,

        init: function (settings) {
            this.$container = $('<div/>', {
                class: 'modal fitted loading',
            }).appendTo(Garnish.$bod);

            this.base(this.$container, {resizable: false});

            var data = {
                elementId:    settings.elementId,
                elementType:  settings.elementType,
                sourceSiteId: settings.sourceSiteId,
                fieldHandle:  settings.fieldHandle,
            };

            var $this = this;

            Craft.sendActionRequest('POST', 'multi-translator/field/review', {data})
                .then(function (response) {
                    $this.$container.removeClass('loading');
                    $this.$container.append(response.data.html);

                    var $buttons = $('.buttons', $this.$container),
                        $cancelBtn = $(
                            '<div class="btn">' + Craft.t('app', 'Cancel') + '</div>'
                        ).prependTo($buttons);

                    $this.addListener($cancelBtn, 'click', 'hide');

                    setTimeout(function () {
                        Craft.initUiElements($this.$container);
                        $this.updateSizeAndPosition();
                    }, 200);
                })
                .catch(function (error) {
                    $this.$container.removeClass('loading');
                    var msg = Craft.t('app', 'An unknown error occurred.');

                    if (error.response && error.response.data && error.response.data.message) {
                        msg = error.response.data.message;
                    }

                    $this.$container.append('<div class="body">' + msg + '</div>');
                });
        },
    }, {});

    Craft.translateBlockModal = Garnish.Modal.extend({
        $container: null,
        $body: null,

        init: function (settings) {
            this.$container = $('<div/>', {
                id: 'translatemodal',
                class: 'modal fitted loading',
            }).appendTo(Garnish.$bod);

            this.base(
                this.$container,
                $.extend(
                    {
                        resizable: false,
                    },
                    settings
                )
            );

            var data = {
                blockId: settings.blockId,
                sourceSiteId: settings.sourceSiteId
            };

            Craft.sendActionRequest('POST','multi-translator/block/review', {data})
                .then((response) => {
                    this.$container.removeClass('loading');
                    var $this = this;
                    this.$container.append(response.data.html);

                    var $buttons = $('.buttons', this.$container),
                        $cancelBtn = $(
                            '<div class="btn">' + Craft.t('app', 'Cancel') + '</div>'
                        ).prependTo($buttons);

                    this.addListener($cancelBtn, 'click', 'cancelTranslation');

                    setTimeout(function () {
                        Craft.initUiElements(this.$container);
                        $this.updateSizeAndPosition();
                    }, 200);
                })
                .catch(({response}) => {
                    console.log(response);
                    this.$container.removeClass('loading');
                    var error = Craft.t('app', 'An unknown error occurred.');

                    if (response.data.message) {
                        error = response.data.message;
                    }

                    this.$container.append('<div class="body">' + error + '</div>');
                });
        },

        cancelTranslation: function () {
            this.hide();
        },
    }, {});

    /**
     * Craft.MultiTranslatorTargetGroups
     *
     * Target groups table in the plugin settings (templates/_settings/targetGroups.twig):
     * add/delete/reorder rows, a multiselect of sites per group and a handle generated from the name.
     */
    Craft.MultiTranslatorTargetGroups = Garnish.Base.extend({
        $container: null,
        $table: null,
        $tbody: null,
        template: null,
        sorter: null,
        newRowCount: 0,

        init: function (container) {
            this.$container = $(container);
            this.$table = this.$container.find('[data-target-groups-table]');
            this.$tbody = this.$table.children('tbody');
            this.template = this.$container.find('[data-target-groups-template]').html();

            this.sorter = new Garnish.DragSort({
                handle: '.move',
                axis: Garnish.Y_AXIS,
                collapseDraggees: true,
                magnetStrength: 4,
                helperLagBase: 1.5,
                helperOpacity: 0.9,
            });

            this.$tbody.children('tr').each((i, row) => this.initRow($(row), false));

            this.addListener(this.$container.find('[data-target-groups-add]'), 'click', 'addRow');
        },

        initRow: function ($row, isNew) {
            $row.find('[data-sites]').selectize({
                plugins: ['remove_button'],
                dropdownParent: 'body',
                closeAfterSelect: false,
                hideSelected: true,
            });

            // only generate the handle for new groups, an existing handle shouldn't change when renaming
            if (isNew) {
                new Craft.HandleGenerator($row.find('[data-name]'), $row.find('[data-handle]'));
            }

            this.addListener($row.find('[data-delete]'), 'click', () => this.deleteRow($row));
            this.sorter.addItems($row);
        },

        addRow: function () {
            const $row = $(this.template.replace(/__ROW__/g, 'new' + (++this.newRowCount)).trim());
            this.$tbody.append($row);
            this.$table.removeClass('hidden');
            this.initRow($row, true);
            $row.find('[data-name]').trigger('focus');
        },

        deleteRow: function ($row) {
            this.sorter.removeItems($row);
            $row.find('[data-sites]').each((i, select) => select.selectize && select.selectize.destroy());
            $row.remove();
            this.$table.toggleClass('hidden', this.$tbody.children('tr').length === 0);
        },
    });

    Craft.MultiTranslator = Garnish.Base.extend(
    {
        init: function () {
            // Hook op bestaande matrixvelden
            $('.matrixblock').each(function() {
                injectTranslationAction($(this));
            });
        },
    });

    function injectTranslationAction($block) {
        var $menu = $block.find('> .actions .menu');

        if ($menu.length && !$menu.find('ul.translate-action').length) {
            var $li = $('<li><button class="menu-item" data-icon="language">Translate to…</button></li>');
            var $ul = $('<ul class="translate-action"></ul>')

            $li.on('click', function(e) {
                e.preventDefault();
                openTranslationDialog($block);
            });

            $ul.append($li);
            $menu.prepend($ul);
        }
    }

    function openTranslationDialog($block) {
        const blockId = $block.data('id');
        const sourceSite = $block.data('site-id');

        var modal = new Craft.translateBlockModal({
            blockId: blockId, sourceSiteId: sourceSite
        });
    }

    new Craft.MultiTranslator();

    /**
     * Sidebar translate links: submit as a POST request with a CSRF token instead of a GET link.
     * Uses a temporary form outside the element editor form (forms can't be nested).
     */
    if (!window.multiTranslatorSidebarListener) {
        window.multiTranslatorSidebarListener = true;

        $(document).on('click', 'a[data-multi-translator-translate]', function (ev) {
            ev.preventDefault();

            const $link = $(this);
            const confirmMessage = $link.data('multi-translator-confirm');

            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            }

            const params = $link.data('multi-translator-translate') || {};
            const $form = $('<form/>', {method: 'post', action: Craft.getActionUrl('multi-translator/sidebar/translate')});

            if (Craft.csrfTokenName) {
                $('<input/>', {type: 'hidden', name: Craft.csrfTokenName, value: Craft.csrfTokenValue}).appendTo($form);
            }

            Object.keys(params).forEach(function (name) {
                $('<input/>', {type: 'hidden', name: name, value: params[name]}).appendTo($form);
            });

            $form.appendTo(Garnish.$bod).trigger('submit');
        });
    }

})(window);
