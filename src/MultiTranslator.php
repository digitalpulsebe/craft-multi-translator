<?php

namespace digitalpulsebe\craftmultitranslator;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\events\DefineFieldActionsEvent;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\fieldlayoutelements\BaseField;
use craft\fieldlayoutelements\CustomField;
use craft\log\MonologTarget;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use digitalpulsebe\craftmultitranslator\elements\actions\Translate;
use digitalpulsebe\craftmultitranslator\models\Settings;
use digitalpulsebe\craftmultitranslator\services\SettingsService;
use digitalpulsebe\craftmultitranslator\services\TranslateService;
use digitalpulsebe\craftmultitranslator\variables\Variable;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use yii\base\Event;
use yii\log\Logger;

/**
 * Multi Translator plugin
 *
 * @method static MultiTranslator getInstance()
 * @method Settings getSettings()
 * @property TranslateService $translate
 * @property SettingsService $settingsService
 * @author Digital Pulse nv <support@digitalpulse.be>
 * @copyright Digital Pulse nv
 * @license https://craftcms.github.io/license/ Craft License
 */
class MultiTranslator extends Plugin
{
    public string $schemaVersion = '1.5.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;
    public ?string $name = 'Multi Translator';

    public static function config(): array
    {
        return [
            'components' => [
                'translate' => TranslateService::class,
                'settingsService' => SettingsService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->registerLogger();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->registerRoutes();
            $this->registerVariables();
            $this->registerSidebarHtml();
            $this->registerFieldActionMenuItems();
            $this->registerPermissions();
            $this->registerActions();
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('multi-translator/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('multiTranslator', Variable::class);

            }
        );
    }

    private function registerSidebarHtml(): void
    {
        foreach(static::getSupportedElementClasses() as $supportedElementClass) {
            Event::on(
                $supportedElementClass,
                Element::EVENT_DEFINE_SIDEBAR_HTML,
                function (DefineHtmlEvent $event) {
                    $template = Craft::$app->getView()->renderTemplate('multi-translator/_sidebar/buttons', [
                        "element" => $event->sender,
                        "plugin" => $this
                    ]);
                    $event->html .= $template;
                }
            );
        }

        // Workaround for Commerce Product
        if (
            class_exists('craft\commerce\Plugin')
            && $this->request->getIsCpRequest()
            && !$this->request->getIsConsoleRequest()
        ) {
            $plugin = $this;
            Craft::$app->view->hook('cp.commerce.product.edit.details', static function(&$context) use ($plugin) {
                return Craft::$app->getView()->renderTemplate('multi-translator/_sidebar/buttons', [
                    "element" => $context['product'],
                    "plugin" => $plugin
                ]);
            });
        }
    }

    private function registerRoutes()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['multi-translator/glossaries/edit/<id:\d+>'] = 'multi-translator/glossaries/edit';
                $event->rules['multi-translator/glossaries/new'] = 'multi-translator/glossaries/new';
                $event->rules['multi-translator/glossaries/fetch'] = 'multi-translator/glossaries/fetch';
                $event->rules['multi-translator/translate/review'] = 'multi-translator/translate/review';
                $event->rules['multi-translator/field/translate'] = 'multi-translator/field/translate';
            }
        );
    }

    /**
     * Register per-field "Translate field…" action menu items in the CP element editor.
     * Uses BaseField::EVENT_DEFINE_ACTION_MENU_ITEMS, available since Craft 5.9.0.
     * A runtime class_exists guard keeps the plugin installable on Craft < 5.9.
     */
    private function registerFieldActionMenuItems(): void
    {
        // Guard: the event was added in Craft 5.9.0
        if (!class_exists(BaseField::class) || !defined(BaseField::class . '::EVENT_DEFINE_ACTION_MENU_ITEMS')) {
            return;
        }

        Event::on(
            BaseField::class,
            BaseField::EVENT_DEFINE_ACTION_MENU_ITEMS,
            function (DefineFieldActionsEvent $event) {
                // Skip read-only forms (drafts sidebar, preview, etc.)
                if ($event->static) {
                    return;
                }

                // Only act when the current user has translation permission
                if (!Craft::$app->user->checkPermission('multiTranslateContent')) {
                    return;
                }

                // Resolve the element being edited
                $element = $event->element;
                if ($element === null) {
                    return;
                }

                // Only supported element types
                if (!in_array(get_class($element), static::getSupportedElementClasses(), true)) {
                    return;
                }

                // Only fields with a registered serializer (i.e. translatable field types)
                /** @var BaseField $layoutElement */
                $layoutElement = $event->sender;
                if (!($layoutElement instanceof CustomField)) {
                    return;
                }

                try {
                    $field = $layoutElement->getField();
                } catch (\Throwable) {
                    return;
                }

                $translateService = static::getInstance()->translate;
                if (empty($translateService->getSerializer($field))) {
                    return;
                }

                // Collect target sites the user may edit, excluding the current site
                $currentUser = Craft::$app->getUser()->getIdentity();
                $targetSites = collect(\craft\helpers\ElementHelper::supportedSitesForElement($element, true))
                    ->filter(fn($site) => $site['siteId'] !== $element->siteId)
                    ->filter(fn($site) => $currentUser->can('editSite:' . $site['siteUid']))
                    ->map(fn($site) => Craft::$app->sites->getSiteById($site['siteId']))
                    ->filter()
                    ->values();

                if ($targetSites->isEmpty()) {
                    return;
                }

                // Build the action menu item; JS will open the modal
                $actionId = sprintf('multi-translator-field-%s', mt_rand());
                $view = Craft::$app->getView();

                $view->registerJsWithVars(
                    static function ($actionId, $params) {
                        return <<<JS
$('#' + $actionId).on('activate', () => {
    new Craft.MultiTranslatorFieldModal($params);
});
JS;
                    },
                    [
                        $view->namespaceInputId($actionId),
                        [
                            'elementId'    => $element->canonicalId,
                            'elementType'  => get_class($element),
                            'sourceSiteId' => $element->siteId,
                            'fieldHandle'  => $field->handle,
                        ],
                    ]
                );

                $event->items[] = [
                    'id'    => $actionId,
                    'icon'  => 'language',
                    'label' => Craft::t('multi-translator', 'Translate field…'),
                ];
            }
        );
    }

    private function registerActions(): void
    {
        if (!Craft::$app->user->checkPermission('multiTranslateContentBulk')) {
            return;
        }

        foreach (static::getSupportedElementClasses() as $supportedElementClass) {
            Event::on(
                $supportedElementClass,
                Element::EVENT_REGISTER_ACTIONS,
                function(RegisterElementActionsEvent $event) {
                    $defaultSiteHandle = Craft::$app->sites->currentSite->handle;
                    $sourceSiteHandle = Craft::$app->request->getParam('site', $defaultSiteHandle);

                    if (Craft::$app->user->checkPermission('multiTranslateContent')) {
                        $event->actions[] = [
                            'type' => Translate::class,
                            'sourceSiteHandle' => $sourceSiteHandle
                        ];
                    }
                }
            );
        }
    }

    /**
     * Register custom permission
     *
     * @return void
     */
    private function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'Multi Translator',
                    'permissions' => [
                        'multiTranslateSettings' => [
                            'label' => 'Manage settings',
                        ],
                        'multiTranslateContent' => [
                            'label' => 'Translate Content',
                        ],
                        'multiTranslateContentBulk' => [
                            'label' => 'Translate Content in bulk (element action)',
                        ],
                    ],
                ];
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $nav = parent::getCpNavItem();
        $nav['subnav']['dashboard'] = ['label' => 'Dashboard', 'url' => 'multi-translator'];
        $nav['subnav']['glossaries'] = ['label' => 'Glossaries', 'url' => 'multi-translator/glossaries'];

        if (Craft::$app->user->checkPermission('multiTranslateSettings')) {
            $nav['subnav']['settings'] = ['label' => 'Settings', 'url' => 'multi-translator/settings'];
        }

        return $nav;
    }

    private function registerLogger(): void
    {
        if (Craft::getLogger()->dispatcher) {
            Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
                'name' => 'multi-translator',
                'categories' => ['multi-translator'],
                'level' => LogLevel::INFO,
                'logContext' => false,
                'allowLineBreaks' => true,
                'formatter' => new LineFormatter(
                    format: "%datetime% %message%\n",
                    dateFormat: 'Y-m-d H:i:s',
                ),
            ]);
        }
    }

    public static function log($message)
    {
        $message = is_array($message) ? json_encode($message) : $message;
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'multi-translator');
    }

    public static function error($message)
    {
        $message = is_array($message) ? json_encode($message) : $message;
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'multi-translator');
    }

    /**
    * @return string[] array of class names
    */
    public static function getSupportedElementClasses(): array
    {
        $supportedElementClasses = [
            Entry::class,
            Asset::class,
            'craft\commerce\elements\Product',
            'craft\commerce\elements\Variant',
        ];

        $existing = [];

        foreach($supportedElementClasses as $supportedElementClass) {
            if (class_exists($supportedElementClass)) {
                $existing[] = $supportedElementClass;
            }
        }

        return $existing;
    }
}
