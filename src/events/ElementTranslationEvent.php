<?php

namespace digitalpulsebe\craftmultitranslator\events;

use craft\base\Element;
use craft\events\CancelableEvent;
use craft\models\Site;

class ElementTranslationEvent extends CancelableEvent
{
    /**
     * the element that was selected as source of the translation
     * @var Element|null
     */
    public ?Element $sourceElement = null;

    /**
     * the element that will result in the translation
     * @var Element|null
     */
    public ?Element $targetElement = null;

    /**
     * the Site of the source element
     * @var Site|null
     */
    public ?Site $sourceSite = null;

    /**
     * the Site for the target element
     * @var Site|null
     */
    public ?Site $targetSite = null;

    /**
     * false when nested Entries|Elements are processed by recursive functions
     * @var bool|null
     */
    public bool $isRootElement = true;
    
}
