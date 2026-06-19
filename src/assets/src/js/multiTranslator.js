
(function (window) {
    const {Craft, Garnish, $} = window;

    /**
     * Craft.MultiTranslatorFieldModal
     *
     * Opens a small modal that lets the user pick a target site, then POSTs to
     * multi-translator/field/translate to translate a single field value.
     *
     * Expected settings keys:
     *   elementId    {number}
     *   elementType  {string}
     *   sourceSiteId {number}
     *   fieldHandle  {string}
     *   fieldName    {string}
     *   sites        {Array<{id: number, name: string}>}
     */
    Craft.MultiTranslatorFieldModal = Garnish.Modal.extend({
        $container: null,
        $select: null,
        $submitBtn: null,
        $spinner: null,
        settings: null,

        init: function (settings) {
            this.settings = settings;

            this.$container = $('<div/>', {
                class: 'modal fitted',
            }).appendTo(Garnish.$bod);

            this.base(this.$container, {resizable: false});

            this._buildContent();
        },

        _buildContent: function () {
            var self = this;
            var fieldLabel = Craft.escapeHtml(this.settings.fieldName || this.settings.fieldHandle);

            // Build site <option> list
            var optionsHtml = '<option value="">' + Craft.escapeHtml(Craft.t('multi-translator', 'Select target site…')) + '</option>';
            (this.settings.sites || []).forEach(function (site) {
                optionsHtml += '<option value="' + parseInt(site.id) + '">' + Craft.escapeHtml(site.name) + '</option>';
            });

            var html = [
                '<div class="body">',
                '  <h2>' + Craft.t('multi-translator', 'Translate field') + ': <em>' + fieldLabel + '</em></h2>',
                '  <div class="field">',
                '    <div class="heading"><label for="mt-field-target-site">' + Craft.t('multi-translator', 'Target site') + '</label></div>',
                '    <div class="input ltr">',
                '      <div class="select">',
                '        <select id="mt-field-target-site">' + optionsHtml + '</select>',
                '      </div>',
                '    </div>',
                '  </div>',
                '</div>',
                '<div class="footer">',
                '  <div class="buttons right">',
                '    <button type="button" class="btn" data-mt-cancel>' + Craft.t('app', 'Cancel') + '</button>',
                '    <button type="button" class="btn submit" data-mt-submit disabled>' + Craft.t('multi-translator', 'Translate') + '</button>',
                '    <div class="spinner hidden"></div>',
                '  </div>',
                '</div>',
            ].join('');

            this.$container.html(html);
            this.$select    = this.$container.find('#mt-field-target-site');
            this.$submitBtn = this.$container.find('[data-mt-submit]');
            this.$spinner   = this.$container.find('.spinner');

            // Enable submit only when a site is selected
            this.addListener(this.$select, 'change', function () {
                if (self.$select.val()) {
                    self.$submitBtn.prop('disabled', false);
                } else {
                    self.$submitBtn.prop('disabled', true);
                }
            });

            this.addListener(this.$container.find('[data-mt-cancel]'), 'click', 'hide');
            this.addListener(this.$submitBtn, 'click', '_submit');

            this.updateSizeAndPosition();
        },

        _submit: function () {
            var self       = this;
            var targetSiteId = parseInt(this.$select.val());
            if (!targetSiteId) return;

            this.$submitBtn.prop('disabled', true);
            this.$spinner.removeClass('hidden');

            Craft.sendActionRequest('POST', 'multi-translator/field/translate', {
                data: {
                    elementId:    this.settings.elementId,
                    elementType:  this.settings.elementType,
                    sourceSiteId: this.settings.sourceSiteId,
                    targetSiteId: targetSiteId,
                    fieldHandle:  this.settings.fieldHandle,
                },
            }).then(function (response) {
                self.hide();
                Craft.cp.displayNotice(response.data.message);
            }).catch(function (error) {
                self.$spinner.addClass('hidden');
                self.$submitBtn.prop('disabled', false);
                var msg = (error.response && error.response.data && error.response.data.message)
                    ? error.response.data.message
                    : Craft.t('app', 'An unknown error occurred.');
                Craft.cp.displayError(msg);
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
                            '<div class="btn">' + Craft.t('commerce', 'Cancel') + '</div>'
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
                    var error = Craft.t('commerce', 'An unknown error occurred.');

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

})(window);
