<?php

namespace digitalpulsebe\craftmultitranslator\events;

use yii\base\Event;

class RegisterSerializersEvent extends Event
{
    public ?array $serializers = [];
}
