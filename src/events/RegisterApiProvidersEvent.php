<?php

namespace digitalpulsebe\craftmultitranslator\events;

use yii\base\Event;

class RegisterApiProvidersEvent extends Event
{
    /** @var string[] Array of Provider class names */
    public array $providers = [];
}
