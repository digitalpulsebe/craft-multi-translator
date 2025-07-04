<?php

namespace digitalpulsebe\craftmultitranslator\events;

use craft\base\Element;
use craft\base\FieldInterface;
use craft\events\CancelableEvent;
use craft\models\Site;

class FieldTranslationEvent extends CancelableEvent
{
    /**
     * the element that was selected as source of the translation
     * @var Element|null
     */
    public ?Element $sourceElement = null;

    /**
     * the field being processed
     * @var FieldInterface|null
     */
    public ?FieldInterface $field = null;

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
     * the value returned from the translation service
     * @var mixed
     */
    public mixed $translatedValue = null;
    
}
