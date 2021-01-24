<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\framework\web;

use yii\caching\ArrayCache;

class SpeedUrlManagerParseUrlTest extends UrlManagerParseUrlTest
{
    public function testSimpleSpeed()
    {
        $rules = [];
        $charSet = range('a', 'z');
        foreach ($charSet as $c1) {
            foreach ($charSet as $c2) {
                foreach ($charSet as $c3) {
                    foreach ($charSet as $c4) {
                        $name = $c1 . $c2 . $c3 . $c4;
                        $rules[$name] = $name . '/view';
                    }
                }
            }
        }

        $cache = new ArrayCache();

        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => $rules,
                                            'cache' => $cache,
                                        ]);
        var_dump(['init+cache' => microtime(true) - $step]);


        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => $rules,
                                            'cache' => $cache,
                                        ]);
        var_dump(['init' => microtime(true) - $step]);

        $step = microtime(true);
        $result = $manager->parseRequest($this->getRequest('site/index'));
        var_dump(['parsed' => microtime(true) - $step]);
        $this->assertEquals(['site/index', []], $result);
    }

    public function testSimpleSingleSpeed()
    {
        $cache = new ArrayCache();

        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => ['test' => 'test/view'],
                                            'cache' => $cache,
                                        ]);
        var_dump(['init+cache' => microtime(true) - $step]);


        $time = 0;
        $i = 0;
        $count = 100000;
        while ($i < $count) {
            $manager = $this->getUrlManager(
                [
                    'rules' => ['test' => 'test/view'],
                    'cache' => $cache,
                ]
            );
            $step = microtime(true);
            $manager->parseRequest($this->getRequest('site/index'));
            $time += microtime(true) - $step;
            $i++;
        }

        var_dump(['avg.parse' => $time / $count]);
    }

    public function testRestSpeed()
    {
        $rules = [];
        $charSet = range('a', 'p');
        foreach ($charSet as $c1) {
            foreach ($charSet as $c2) {
                foreach ($charSet as $c3) {
                    foreach ($charSet as $c4) {
                        $name = $c1 . $c2 . $c3 . $c4;
                        $rules[] = [
                            'class' => 'yii\rest\UrlRule',
                            'controller' => $name,
                        ];
                    }
                }
            }
        }

        $cache = new ArrayCache();

        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => $rules,
                                            'cache' => $cache,
                                        ]);
        var_dump(['init+cache' => microtime(true) - $step]);


        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => $rules,
                                            'cache' => $cache,
                                        ]);
        var_dump(['init' => microtime(true) - $step]);

        $step = microtime(true);
        $result = $manager->parseRequest($this->getRequest('site/index'));
        var_dump(['parsed' => microtime(true) - $step]);
        $this->assertEquals(['site/index', []], $result);
    }

    public function testRest2Speed()
    {
        $rules = [];
        $charSet = range('a', 'p');
        foreach ($charSet as $c1) {
            foreach ($charSet as $c2) {
                foreach ($charSet as $c3) {
                    foreach ($charSet as $c4) {
                        $name = $c1 . $c2 . $c3 . $c4;
                        $rules[] = $name;
                    }
                }
            }
        }

        $cache = new ArrayCache();

        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => [[
                                                'class' => 'yii\rest\UrlRule',
                                                'controller' => $rules,
                                            ]],
                                            'cache' => $cache,
                                        ]);
        var_dump(['init+cache' => microtime(true) - $step]);


        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => [[
                                                'class' => 'yii\rest\UrlRule',
                                                'controller' => $rules,
                                            ]],
                                            'cache' => $cache,
                                        ]);
        var_dump(['init' => microtime(true) - $step]);

        $step = microtime(true);
        $result = $manager->parseRequest($this->getRequest('site/index'));
        var_dump(['parsed' => microtime(true) - $step]);
        $this->assertEquals(['site/index', []], $result);
    }

    public function testRestSingleSpeed()
    {
        $cache = new ArrayCache();

        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => [
                                                [
                                                    'class' => 'yii\rest\UrlRule',
                                                    'controller' => 'test',
                                                ]
                                            ],
                                            'cache' => $cache,
                                        ]);
        var_dump(['init+cache' => microtime(true) - $step]);


        $time = 0;
        $i = 0;
        $count = 100000;
        while ($i < $count) {
            $manager = $this->getUrlManager(
                [
                    'rules' => [[
                        'class' => 'yii\rest\UrlRule',
                        'controller' => 'test',
                    ]],
                    'cache' => $cache,
                ]
            );
            $step = microtime(true);
            $manager->parseRequest($this->getRequest('site/index'));
            $time += microtime(true) - $step;
            $i++;
        }

        var_dump(['avg.parse' => $time / $count]);
    }

    public function testGroupSpeed()
    {
        $rules = [];
        $charSet = range('a', 'z');
        foreach ($charSet as $c1) {
            foreach ($charSet as $c2) {
                foreach ($charSet as $c3) {
                    foreach ($charSet as $c4) {
                        $name = $c1 . $c2 . $c3 . $c4;
                        $rules[$name] = $name . '/view';
                    }
                }
            }
        }
        $rule = [
            'class' => 'yii\web\GroupUrlRule',
            'rules' => $rules,
        ];

        $cache = new ArrayCache();

        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => [$rule],
                                            'cache' => $cache,
                                        ]);
        var_dump(['init+cache' => microtime(true) - $step]);


        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => [$rule],
                                            'cache' => $cache,
                                        ]);
        var_dump(['init' => microtime(true) - $step]);

        $step = microtime(true);
        $result = $manager->parseRequest($this->getRequest('site/index'));
        var_dump(['parsed' => microtime(true) - $step]);
        $this->assertEquals(['site/index', []], $result);
    }

    public function testGroupSingleSpeed()
    {
        $cache = new ArrayCache();

        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => [
                                                [
                                                    'class' => 'yii\web\GroupUrlRule',
                                                    'rules' => ['test' => 'test/view'],
                                                ]
                                            ],
                                            'cache' => $cache,
                                        ]);
        var_dump(['init+cache' => microtime(true) - $step]);


        $time = 0;
        $i = 0;
        $count = 100000;
        while ($i < $count) {
            $manager = $this->getUrlManager(
                [
                    'rules' => [[
                        'class' => 'yii\web\GroupUrlRule',
                        'rules' => ['test' => 'test/view'],
                    ]],
                    'cache' => $cache,
                ]
            );
            $step = microtime(true);
            $manager->parseRequest($this->getRequest('site/index'));
            $time += microtime(true) - $step;
            $i++;
        }

        var_dump(['avg.parse' => $time / $count]);
    }

    public function testSimpleSpeedNoCache()
    {
        $rules = [];
        $charSet = range('a', 'z');
        foreach ($charSet as $c1) {
            foreach ($charSet as $c2) {
                foreach ($charSet as $c3) {
                    foreach ($charSet as $c4) {
                        $name = $c1 . $c2 . $c3 . $c4;
                        $rules[$name] = $name . '/view';
                    }
                }
            }
        }

        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => $rules,
                                        ]);
        var_dump(['init' => microtime(true) - $step]);

        $step = microtime(true);
        $result = $manager->parseRequest($this->getRequest('site/index'));
        var_dump(['parsed' => microtime(true) - $step]);
        $this->assertEquals(['site/index', []], $result);
    }

    public function testSimpleSingleSpeedNoCache()
    {
        $time = 0;
        $i = 0;
        $count = 100000;
        while ($i < $count) {
            $manager = $this->getUrlManager(
                [
                    'rules' => ['test' => 'test/view'],
                ]
            );
            $step = microtime(true);
            $manager->parseRequest($this->getRequest('site/index'));
            $time += microtime(true) - $step;
            $i++;
        }

        var_dump(['avg.parse' => $time / $count]);
    }

    public function testRestSpeedNoCache()
    {
        $rules = [];
        $charSet = range('a', 'p');
        foreach ($charSet as $c1) {
            foreach ($charSet as $c2) {
                foreach ($charSet as $c3) {
                    foreach ($charSet as $c4) {
                        $name = $c1 . $c2 . $c3 . $c4;
                        $rules[] = [
                            'class' => 'yii\rest\UrlRule',
                            'controller' => $name,
                        ];
                    }
                }
            }
        }

        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => $rules,
                                        ]);
        var_dump(['init' => microtime(true) - $step]);

        $step = microtime(true);
        $result = $manager->parseRequest($this->getRequest('site/index'));
        var_dump(['parsed' => microtime(true) - $step]);
        $this->assertEquals(['site/index', []], $result);
    }

    public function testRest2SpeedNoCache()
    {
        $rules = [];
        $charSet = range('a', 'p');
        foreach ($charSet as $c1) {
            foreach ($charSet as $c2) {
                foreach ($charSet as $c3) {
                    foreach ($charSet as $c4) {
                        $name = $c1 . $c2 . $c3 . $c4;
                        $rules[] = $name;
                    }
                }
            }
        }

        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => [[
                                                'class' => 'yii\rest\UrlRule',
                                                'controller' => $rules,
                                            ]],
                                        ]);
        var_dump(['init' => microtime(true) - $step]);

        $step = microtime(true);
        $result = $manager->parseRequest($this->getRequest('site/index'));
        var_dump(['parsed' => microtime(true) - $step]);
        $this->assertEquals(['site/index', []], $result);
    }

    public function testRestSingleSpeedNoCache()
    {
        $time = 0;
        $i = 0;
        $count = 100000;
        while ($i < $count) {
            $manager = $this->getUrlManager(
                [
                    'rules' => [[
                        'class' => 'yii\rest\UrlRule',
                        'controller' => 'test',
                    ]],
                ]
            );
            $step = microtime(true);
            $manager->parseRequest($this->getRequest('site/index'));
            $time += microtime(true) - $step;
            $i++;
        }

        var_dump(['avg.parse' => $time / $count]);
    }

    public function testGroupSpeedNoCache()
    {
        $rules = [];
        $charSet = range('a', 'z');
        foreach ($charSet as $c1) {
            foreach ($charSet as $c2) {
                foreach ($charSet as $c3) {
                    foreach ($charSet as $c4) {
                        $name = $c1 . $c2 . $c3 . $c4;
                        $rules[$name] = $name . '/view';
                    }
                }
            }
        }
        $rule = [
            'class' => 'yii\web\GroupUrlRule',
            'rules' => $rules,
        ];

        $step = microtime(true);
        $manager = $this->getUrlManager([
                                            'rules' => [$rule],
                                        ]);
        var_dump(['init' => microtime(true) - $step]);

        $step = microtime(true);
        $result = $manager->parseRequest($this->getRequest('site/index'));
        var_dump(['parsed' => microtime(true) - $step]);
        $this->assertEquals(['site/index', []], $result);
    }

    public function testGroupSingleSpeedNoCache()
    {
        $time = 0;
        $i = 0;
        $count = 100000;
        while ($i < $count) {
            $manager = $this->getUrlManager(
                [
                    'rules' => [[
                        'class' => 'yii\web\GroupUrlRule',
                        'rules' => ['test' => 'test/view'],
                    ]],
                ]
            );
            $step = microtime(true);
            $manager->parseRequest($this->getRequest('site/index'));
            $time += microtime(true) - $step;
            $i++;
        }

        var_dump(['avg.parse' => $time / $count]);
    }
}
