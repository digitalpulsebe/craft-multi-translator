
(function (window) {
    const {Craft, Garnish, $} = window;

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
            var $li = $('<li><button class="menu-item" data-icon="language">Vertalen naar…</button></li>');
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
