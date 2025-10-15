<?php

namespace digitalpulsebe\craftmultitranslator;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\log\MonologTarget;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use digitalpulsebe\craftmultitranslator\elements\actions\Translate;
use digitalpulsebe\craftmultitranslator\models\Settings;
use digitalpulsebe\craftmultitranslator\services\DeeplService;
use digitalpulsebe\craftmultitranslator\services\GoogleService;
use digitalpulsebe\craftmultitranslator\services\OpenAiService;
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
 * @property DeeplService $deepl
 * @property OpenAiService $openai
 * @property GoogleService $google
 * @property TranslateService $translate
 * @property SettingsService $settingsService
 * @author Digital Pulse nv <support@digitalpulse.be>
 * @copyright Digital Pulse nv
 * @license https://craftcms.github.io/license/ Craft License
 */
class MultiTranslator extends Plugin
{
    public string $schemaVersion = '1.3.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;
    public ?string $name = 'Multi Translator';

    public static function config(): array
    {
        return [
            'components' => [
                'deepl' => DeeplService::class,
                'google' => GoogleService::class,
                'openai' => OpenAiService::class,
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
