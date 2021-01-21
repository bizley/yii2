<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\framework\web\mocks;

use yii\web\UrlRule;

class ConfigurableFastParseDataRule extends UrlRule
{
    public $fastParseDataConfig = [];

    public function getFastParseData()
    {
        return $this->fastParseDataConfig;
    }
}
