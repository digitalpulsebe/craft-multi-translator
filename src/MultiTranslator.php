<?php

namespace digitalpulsebe\craftmultitranslator;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\commerce\elements\Product;
use craft\commerce\Plugin as Commerce;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\log\MonologTarget;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use digitalpulsebe\craftmultitranslator\elements\actions\Translate;
use digitalpulsebe\craftmultitranslator\models\Settings;
use digitalpulsebe\craftmultitranslator\services\DeeplService;
use digitalpulsebe\craftmultitranslator\services\GoogleService;
use digitalpulsebe\craftmultitranslator\services\OpenAiService;
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
 * @author Digital Pulse nv <support@digitalpulse.be>
 * @copyright Digital Pulse nv
 * @license https://craftcms.github.io/license/ Craft License
 */
class MultiTranslator extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public ?string $name = 'Multi Translator';

    public static function config(): array
    {
        return [
            'components' => [
                'deepl' => DeeplService::class,
                'google' => GoogleService::class,
                'openai' => OpenAiService::class,
                'translate' => TranslateService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->registerLogger();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
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
            class_exists(Commerce::class)
            && $this->request->getIsCpRequest() 
            && !$this->request->getIsConsoleRequest()
        ) {
            $plugin = $this;
            Craft::$app->view->hook('cp.commerce.product.edit.details', static function(&$context) use ($plugin) {
                return Craft::$app->getView()->renderTemplate('multi-translator/_sidebar/buttons', [
                    "element" => $context['product'],
                    "plugin" => $plugin
                ]);;
            });
        }
    }

    private function registerActions(): void
    {
        foreach(static::getSupportedElementClasses() as $supportedElementClass) {
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
                    'heading' => 'DeepL Translator',
                    'permissions' => [
                        'multiTranslateContent' => [
                            'label' => 'Translate Content',
                        ],
                    ],
                ];
            }
        );
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
            Product::class,
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
