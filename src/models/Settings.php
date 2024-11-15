<?php

namespace digitalpulsebe\craftmultitranslator\models;

use craft\base\Model;

/**
 * Multi Translator settings
 */
class Settings extends Model
{

    /**
     * when enabled log info about translations
     * @var bool
     */
    public bool $debug = false;

    /**
     * time to reserve for the queue job when translating in bulk
     * @var int
     */
    public int $queueJobTtr = 3600;

}
