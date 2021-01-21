<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\framework\web\stubs;

class InvalidRule
{
    public static $visited = false;

    public function parseRequest($manager, $request)
    {
        static::$visited = true;
        return false;
    }
}
