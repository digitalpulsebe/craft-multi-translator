<?php

namespace digitalpulsebe\craftmultitranslator\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class MultiTranslatorBundle extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = __DIR__ . '/src';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/multiTranslator.js'
        ];

        $this->css = [

        ];

        parent::init();
    }
}
