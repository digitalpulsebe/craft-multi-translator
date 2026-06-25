
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
