<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\framework\web;

use yii\caching\ArrayCache;
use yii\web\Request;
use yii\web\UrlManager;
use yii\web\UrlRule;
use yiiunit\framework\web\stubs\InvalidRule;
use yiiunit\TestCase;

/**
 * This class implements the tests for URL parsing with "pretty" url format.
 *
 * See [[UrlManagerTest]] for tests with "default" URL format.
 * See [[UrlManagerCreateUrlTest]] for url creation with "pretty" URL format.
 *
 * Behavior of UrlManager::parseRequest() for the "pretty" URL format varies among the following options:
 *  - strict parsing = true / false
 *  - rules format
 *    - key => value
 *    - array config
 *
 * The following features are tested:
 *  - named parameters
 *    - as query params
 *    - as controller/actions '<controller:(post|comment)>/<id:\d+>/<action:(update|delete)>' => '<controller>/<action>',
 *  - Rules with Server Names
 *    - with protocol
 *    - without protocol i.e protocol relative, see https://github.com/yiisoft/yii2/pull/12697
 *    - with parameters
 *  - with suffix
 *  - with default values
 *  - with HTTP methods
 *
 *  - Adding rules dynamically
 *  - Test custom rules that only implement the interface
 *
 * NOTE: if a test is added here, you probably also need to add one in UrlManagerCreateUrlTest.
 *
 * @group web
 */
class UrlManagerParseUrlTest extends TestCase
{
    protected function getUrlManager($config = [])
    {
        // in this test class, all tests have enablePrettyUrl enabled.
        $config['enablePrettyUrl'] = true;
        // normalizer is tested in UrlNormalizerTest
        $config['normalizer'] = false;

        return new UrlManager(array_merge([
            'cache' => null,
        ], $config));
    }

    protected function getRequest($pathInfo, $hostInfo = 'http://www.example.com', $method = 'GET', $config = [])
    {
        $config['pathInfo'] = $pathInfo;
        $config['hostInfo'] = $hostInfo;
        $_POST['_method'] = $method;
        return new Request($config);
    }

    protected function tearDown()
    {
        unset($_POST['_method']);
        parent::tearDown();
    }

    public function testWithoutRules()
    {
        $manager = $this->getUrlManager();

        // empty pathinfo
        $result = $manager->parseRequest($this->getRequest(''));
        $this->assertEquals(['', []], $result);
        // normal pathinfo
        $result = $manager->parseRequest($this->getRequest('site/index'));
        $this->assertEquals(['site/index', []], $result);
        // pathinfo with module
        $result = $manager->parseRequest($this->getRequest('module/site/index'));
        $this->assertEquals(['module/site/index', []], $result);
        // pathinfo with trailing slashes
        $result = $manager->parseRequest($this->getRequest('module/site/index/'));
        $this->assertEquals(['module/site/index/', []], $result);
    }

    public function testWithoutRulesStrict()
    {
        $manager = $this->getUrlManager();
        $manager->enableStrictParsing = true;

        // empty pathinfo
        $this->assertFalse($manager->parseRequest($this->getRequest('')));
        // normal pathinfo
        $this->assertFalse($manager->parseRequest($this->getRequest('site/index')));
        // pathinfo with module
        $this->assertFalse($manager->parseRequest($this->getRequest('module/site/index')));
        // pathinfo with trailing slashes
        $this->assertFalse($manager->parseRequest($this->getRequest('module/site/index/')));
    }

    public function suffixProvider()
    {
        return [
            'first no cache' => ['.html', false],
            'first with cache' => ['.html', true],
            'second no cache' => ['/', false],
            'second with cache' => ['/', true],
        ];
    }

    /**
     * @dataProvider suffixProvider
     * @param string $suffix
     * @param bool $withCache
     */
    public function testWithoutRulesWithSuffix($suffix, $withCache)
    {
        $config = ['suffix' => $suffix];
        if ($withCache) {
            $config['cache'] = new ArrayCache();
        }
        $manager = $this->getUrlManager($config);
        if ($withCache) {
            // cache the rules
            $manager->rules;
        }

        // empty pathinfo
        $result = $manager->parseRequest($this->getRequest(''));
        $this->assertEquals(['', []], $result);
        // normal pathinfo
        $result = $manager->parseRequest($this->getRequest('site/index'));
        $this->assertFalse($result);
        $result = $manager->parseRequest($this->getRequest("site/index$suffix"));
        $this->assertEquals(['site/index', []], $result);
        // pathinfo with module
        $result = $manager->parseRequest($this->getRequest('module/site/index'));
        $this->assertFalse($result);
        $result = $manager->parseRequest($this->getRequest("module/site/index$suffix"));
        $this->assertEquals(['module/site/index', []], $result);
        // pathinfo with trailing slashes
        if ($suffix !== '/') {
            $result = $manager->parseRequest($this->getRequest('module/site/index/'));
            $this->assertFalse($result);
        }
        $result = $manager->parseRequest($this->getRequest("module/site/index/$suffix"));
        $this->assertEquals(['module/site/index/', []], $result);
    }

    public function withCacheProvider()
    {
        return [
            'no cache' => [false],
            'with cache' => [true],
        ];
    }

    /**
     * @dataProvider withCacheProvider
     * @param bool $withCache
     */
    public function testSimpleRules($withCache)
    {
        $config = [
            'rules' => [
                'post/<id:\d+>' => 'post/view',
                'posts' => 'post/index',
                'book/<id:\d+>/<title>' => 'book/view',
            ],
        ];
        if ($withCache) {
            $config['cache'] = new ArrayCache();
        }
        $manager = $this->getUrlManager($config);
        if ($withCache) {
            // cache the rules
            $manager->rules;
        }

        // matching pathinfo
        $result = $manager->parseRequest($this->getRequest('book/123/this+is+sample'));
        $this->assertEquals(['book/view', ['id' => '123', 'title' => 'this+is+sample']], $result);
        // trailing slash is significant, no match
        $result = $manager->parseRequest($this->getRequest('book/123/this+is+sample/'));
        $this->assertEquals(['book/123/this+is+sample/', []], $result);
        // empty pathinfo
        $result = $manager->parseRequest($this->getRequest(''));
        $this->assertEquals(['', []], $result);
        // normal pathinfo
        $result = $manager->parseRequest($this->getRequest('site/index'));
        $this->assertEquals(['site/index', []], $result);
        // pathinfo with module
        $result = $manager->parseRequest($this->getRequest('module/site/index'));
        $this->assertEquals(['module/site/index', []], $result);
    }

    /**
     * @dataProvider withCacheProvider
     * @param bool $withCache
     */
    public function testSimpleRulesStrict($withCache)
    {
        $config = [
            'rules' => [
                'post/<id:\d+>' => 'post/view',
                'posts' => 'post/index',
                'book/<id:\d+>/<title>' => 'book/view',
            ],
        ];
        if ($withCache) {
            $config['cache'] = new ArrayCache();
        }
        $manager = $this->getUrlManager($config);
        $manager->enableStrictParsing = true;
        if ($withCache) {
            // cache the rules
            $manager->rules;
        }

        // matching pathinfo
        $result = $manager->parseRequest($this->getRequest('book/123/this+is+sample'));
        $this->assertEquals(['book/view', ['id' => '123', 'title' => 'this+is+sample']], $result);
        // trailing slash is significant, no match
        $result = $manager->parseRequest($this->getRequest('book/123/this+is+sample/'));
        $this->assertFalse($result);
        // empty pathinfo
        $result = $manager->parseRequest($this->getRequest(''));
        $this->assertFalse($result);
        // normal pathinfo
        $result = $manager->parseRequest($this->getRequest('site/index'));
        $this->assertFalse($result);
        // pathinfo with module
        $result = $manager->parseRequest($this->getRequest('module/site/index'));
        $this->assertFalse($result);
    }

    /**
     * @dataProvider suffixProvider
     * @param string $suffix
     * @param bool $withCache
     */
    public function testSimpleRulesWithSuffix($suffix, $withCache)
    {
        $config = [
            'rules' => [
                'post/<id:\d+>' => 'post/view',
                'posts' => 'post/index',
                'book/<id:\d+>/<title>' => 'book/view',
            ],
            'suffix' => $suffix,
        ];
        if ($withCache) {
            $config['cache'] = new ArrayCache();
        }
        $manager = $this->getUrlManager($config);
        if ($withCache) {
            // cache the rules
            $manager->rules;
        }

        // matching pathinfo
        $result = $manager->parseRequest($this->getRequest('book/123/this+is+sample'));
        $this->assertFalse($result);
        $result = $manager->parseRequest($this->getRequest("book/123/this+is+sample$suffix"));
        $this->assertEquals(['book/view', ['id' => '123', 'title' => 'this+is+sample']], $result);
        // trailing slash is significant, no match
        $result = $manager->parseRequest($this->getRequest('book/123/this+is+sample/'));
        if ($suffix === '/') {
            $this->assertEquals(['book/view', ['id' => '123', 'title' => 'this+is+sample']], $result);
        } else {
            $this->assertFalse($result);
        }
        $result = $manager->parseRequest($this->getRequest("book/123/this+is+sample/$suffix"));
        $this->assertEquals(['book/123/this+is+sample/', []], $result);
        // empty pathinfo
        $result = $manager->parseRequest($this->getRequest(''));
        $this->assertEquals(['', []], $result);
        // normal pathinfo
        $result = $manager->parseRequest($this->getRequest('site/index'));
        $this->assertFalse($result);
        $result = $manager->parseRequest($this->getRequest("site/index$suffix"));
        $this->assertEquals(['site/index', []], $result);
        // pathinfo with module
        $result = $manager->parseRequest($this->getRequest('module/site/index'));
        $this->assertFalse($result);
        $result = $manager->parseRequest($this->getRequest("module/site/index$suffix"));
        $this->assertEquals(['module/site/index', []], $result);
    }

    /**
     * @dataProvider suffixProvider
     * @param string $suffix
     * @param bool $withCache
     */
    public function testSimpleRulesWithSuffixStrict($suffix, $withCache)
    {
        $config = [
            'rules' => [
                'post/<id:\d+>' => 'post/view',
                'posts' => 'post/index',
                'book/<id:\d+>/<title>' => 'book/view',
            ],
            'suffix' => $suffix,
        ];
        if ($withCache) {
            $config['cache'] = new ArrayCache();
        }
        $manager = $this->getUrlManager($config);
        $manager->enableStrictParsing = true;
        if ($withCache) {
            // cache the rules
            $manager->rules;
        }

        // matching pathinfo
        $result = $manager->parseRequest($this->getRequest('book/123/this+is+sample'));
        $this->assertFalse($result);
        $result = $manager->parseRequest($this->getRequest("book/123/this+is+sample$suffix"));
        $this->assertEquals(['book/view', ['id' => '123', 'title' => 'this+is+sample']], $result);
        // trailing slash is significant, no match
        $result = $manager->parseRequest($this->getRequest('book/123/this+is+sample/'));
        if ($suffix === '/') {
            $this->assertEquals(['book/view', ['id' => '123', 'title' => 'this+is+sample']], $result);
        } else {
            $this->assertFalse($result);
        }
        $result = $manager->parseRequest($this->getRequest("book/123/this+is+sample/$suffix"));
        $this->assertFalse($result);
        // empty pathinfo
        $result = $manager->parseRequest($this->getRequest(''));
        $this->assertFalse($result);
        // normal pathinfo
        $result = $manager->parseRequest($this->getRequest('site/index'));
        $this->assertFalse($result);
        $result = $manager->parseRequest($this->getRequest("site/index$suffix"));
        $this->assertFalse($result);
        // pathinfo with module
        $result = $manager->parseRequest($this->getRequest('module/site/index'));
        $this->assertFalse($result);
        $result = $manager->parseRequest($this->getRequest("module/site/index$suffix"));
        $this->assertFalse($result);
    }



    // TODO implement with hostinfo


    /**
     * @dataProvider withCacheProvider
     * @param bool $withCache
     */
    public function testParseRESTRequest($withCache)
    {
        $request = new Request();

        // pretty URL rules
        $manager = new UrlManager([
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'cache' => $withCache ? new ArrayCache() : null,
            'rules' => [
                'PUT,POST post/<id>/<title>' => 'post/create',
                'DELETE post/<id>' => 'post/delete',
                'post/<id>/<title>' => 'post/view',
                'POST/GET' => 'post/get',
            ],
        ]);
        if ($withCache) {
            // cache the rules
            $manager->rules;
        }

        // matching pathinfo GET request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request->pathInfo = 'post/123/this+is+sample';
        $result = $manager->parseRequest($request);
        $this->assertEquals(['post/view', ['id' => '123', 'title' => 'this+is+sample']], $result);
        // matching pathinfo PUT/POST request
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $request->pathInfo = 'post/123/this+is+sample';
        $result = $manager->parseRequest($request);
        $this->assertEquals(['post/create', ['id' => '123', 'title' => 'this+is+sample']], $result);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request->pathInfo = 'post/123/this+is+sample';
        $result = $manager->parseRequest($request);
        $this->assertEquals(['post/create', ['id' => '123', 'title' => 'this+is+sample']], $result);

        // no wrong matching
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request->pathInfo = 'POST/GET';
        $result = $manager->parseRequest($request);
        $this->assertEquals(['post/get', []], $result);

        // createUrl should ignore REST rules
        $this->mockApplication([
            'components' => [
                'request' => [
                    'hostInfo' => 'http://localhost/',
                    'baseUrl' => '/app',
                ],
            ],
        ], \yii\web\Application::className());
        $this->assertEquals('/app/post/delete?id=123', $manager->createUrl(['post/delete', 'id' => 123]));
        $this->destroyApplication();

        unset($_SERVER['REQUEST_METHOD']);
    }

    /**
     * @dataProvider withCacheProvider
     * @param bool $withCache
     */
    public function testAppendRules($withCache)
    {
        $config = ['rules' => ['post/<id:\d+>' => 'post/view']];
        if ($withCache) {
            $config['cache'] = new ArrayCache();
        }
        $manager = $this->getUrlManager($config);

        $this->assertCount(1, $manager->rules);
        $firstRule = $manager->rules[0];
        $this->assertInstanceOf('yii\web\UrlRuleInterface', $firstRule);

        $manager->addRules([
            'posts' => 'post/index',
            'book/<id:\d+>/<title>' => 'book/view',
        ]);
        $this->assertCount(3, $manager->rules);
        $this->assertSame((string)$firstRule, (string)$manager->rules[0]);
    }

    /**
     * @dataProvider withCacheProvider
     * @param bool $withCache
     */
    public function testPrependRules($withCache)
    {
        $config = ['rules' => ['post/<id:\d+>' => 'post/view']];
        if ($withCache) {
            $config['cache'] = new ArrayCache();
        }
        $manager = $this->getUrlManager($config);

        $this->assertCount(1, $manager->rules);
        $firstRule = $manager->rules[0];
        $this->assertInstanceOf('yii\web\UrlRuleInterface', $firstRule);

        $manager->addRules([
            'posts' => 'post/index',
            'book/<id:\d+>/<title>' => 'book/view',
        ], false);
        $this->assertCount(3, $manager->rules);
        $this->assertNotSame((string)$firstRule, (string)$manager->rules[0]);
        $this->assertSame((string)$firstRule, (string)$manager->rules[2]);
    }

    public function testRulesCache()
    {
        $arrayCache = new ArrayCache();

        $manager = $this->getUrlManager([
            'rules' => ['post/<id:\d+>' => 'post/view'],
            'cache' => $arrayCache,
        ]);

        $this->assertCount(1, $manager->rules);
        $firstRule = $manager->rules[0];
        $this->assertInstanceOf('yii\web\UrlRuleInterface', $firstRule);
        // cache should contain 2 records - rule and its fast parse data
        $this->assertCount(2, $this->getInaccessibleProperty($arrayCache, '_cache'));

        $manager->addRules(['posts' => 'post/index']);
        $manager->addRules([
            'book/<id:\d+>/<title>' => 'book/view',
            'book/<id:\d+>/<author>' => 'book/view'
        ]);

        $this->assertCount(4, $manager->rules);
        $this->assertSame((string)$firstRule, (string)$manager->rules[0]);
        // cache should contain 8 records - 4 rules x 2
        $this->assertCount(8, $this->getInaccessibleProperty($arrayCache, '_cache'));
    }

    public function testRulesCacheIsUsed()
    {
        $arrayCache = $this->getMockBuilder('yii\caching\ArrayCache')
            ->setMethods(['get', 'set'])
            ->getMock();

        $manager = $this->getUrlManager([
            'rules' => ['post/<id:\d+>' => 'post/view'],
            'cache' => $arrayCache,
        ]);
        $manager->rules;

        // save rules to "cache" and make sure it is reused
        $arrayCache->expects($this->exactly(4))->method('get')->willReturn([]);
        $arrayCache->expects($this->never())->method('set');

        for ($i = 0; $i < 2; $i++) {
            $manager = $this->getUrlManager([
                'rules' => ['post/<id:\d+>' => 'post/view'],
                'cache' => $arrayCache,
            ]);
            $manager->rules;
        }
    }

    /**
     * Test a scenario where catch-all rule is used at the end for a CMS but module names should use the module actions and controllers.
     */
    public function testModuleRoute()
    {
        $modules = 'user|my-admin';

        $manager = $this->getUrlManager([
            'rules' => [
                "<module:$modules>" => '<module>',
                "<module:$modules>/<controller>" => '<module>/<controller>',
                "<module:$modules>/<controller>/<action>" => '<module>/<controller>/<action>',
                '<url:[a-zA-Z0-9-/]+>' => 'site/index',
            ],
        ]);

        $result = $manager->parseRequest($this->getRequest('user'));
        $this->assertEquals(['user', []], $result);
        $result = $manager->parseRequest($this->getRequest('user/somecontroller'));
        $this->assertEquals(['user/somecontroller', []], $result);
        $result = $manager->parseRequest($this->getRequest('user/somecontroller/someaction'));
        $this->assertEquals(['user/somecontroller/someaction', []], $result);

        $result = $manager->parseRequest($this->getRequest('users/somecontroller/someaction'));
        $this->assertEquals(['site/index', ['url' => 'users/somecontroller/someaction']], $result);
    }

    public function testParsingWithInvalidRuleObjectProvided()
    {
        InvalidRule::$visited = false;

        $manager = $this->getUrlManager([
            'rules' => [
                new InvalidRule()
            ],
        ]);

        $result = $manager->parseRequest($this->getRequest('site/index'));
        $this->assertEquals(['site/index', []], $result);
        $this->assertFalse(InvalidRule::$visited);
    }

    public function testParsingWithRuleObjectProvided()
    {
        $manager = $this->getUrlManager([
            'rules' => [
                new UrlRule([
                    'pattern' => 'post/<id>',
                    'route' => 'post/view',
                ]),
            ],
        ]);

        $result = $manager->parseRequest($this->getRequest('post/1'));
        $this->assertEquals(['post/view', ['id' => 1]], $result);
    }

    public function providerForFastParseData()
    {
        return [
            'empty' => [[], true], // fast parse data is empty forcing the rule to be processed normally
            'skip' => [['skip' => 1, 'pattern' => '#^post/(?P<abf396750>[^\/]+)$#u'], false],
            'wrong verb' => [['verb' => ['POST'], 'pattern' => '#^post/(?P<abf396750>[^\/]+)$#u'], false],
            'good verb' => [['verb' => ['GET'], 'pattern' => '#^post/(?P<abf396750>[^\/]+)$#u'], true],
            'wrong pattern' => [['pattern' => '#^wrong/(?P<abf396750>[^\/]+)$#u'], false],
            'good pattern' => [['pattern' => '#^post/(?P<abf396750>[^\/]+)$#u'], true],
        ];
    }

    /**
     * @dataProvider providerForFastParseData
     * @param array $config
     * @param bool $matched
     */
    public function testFastParseData($config, $matched)
    {
        $manager = $this->getUrlManager([
            'rules' => [
                [
                    'class' => 'yiiunit\framework\web\mocks\ConfigurableFastParseDataRule',
                    'fastParseDataConfig' => $config,
                    'pattern' => 'post/<id>',
                    'route' => 'post/view'
                ],
            ],
            'cache' => new ArrayCache(),
        ]);
        // cache the rule
        $manager->rules;

        $result = $manager->parseRequest($this->getRequest('post/1'));
        $this->assertEquals($matched ? ['post/view', ['id' => 1]] : ['post/1', []], $result);
    }

    public function testFastParseDataWithWrongSuffix()
    {
        $manager = $this->getUrlManager([
            'rules' => [
                [
                    'class' => 'yiiunit\framework\web\mocks\ConfigurableFastParseDataRule',
                    'fastParseDataConfig' => [
                        'suffix' => 'wrong',
                        'pattern' => '#^post/(?P<abf396750>[^\/]+)$#u',
                    ],
                    'pattern' => 'post/<id>',
                    'route' => 'post/view',
                    'suffix' => 'wrong', // repeated here to make sure rule data would be properly initialized
                ],
            ],
            'cache' => new ArrayCache(),
        ]);
        // cache the rule
        $manager->rules;

        $result = $manager->parseRequest($this->getRequest('post/1.html'));
        $this->assertEquals(['post/1.html', []], $result);
    }

    public function testFastParseDataWithRuleSuffix()
    {
        $manager = $this->getUrlManager([
            'rules' => [
                [
                    'class' => 'yiiunit\framework\web\mocks\ConfigurableFastParseDataRule',
                    'fastParseDataConfig' => [
                        'suffix' => '.html',
                        'pattern' => '#^post/(?P<abf396750>[^\/]+)$#u',
                    ],
                    'pattern' => 'post/<id>',
                    'route' => 'post/view',
                    'suffix' => '.html', // repeated here to make sure rule data would be properly initialized
                ],
            ],
            'cache' => new ArrayCache(),
        ]);
        // cache the rule
        $manager->rules;

        $result = $manager->parseRequest($this->getRequest('post/1.html'));
        $this->assertEquals(['post/view', ['id' => 1]], $result);
    }

    public function testFastParseDataWithManagerSuffix()
    {
        $manager = $this->getUrlManager([
            'rules' => [
                [
                    'class' => 'yiiunit\framework\web\mocks\ConfigurableFastParseDataRule',
                    'fastParseDataConfig' => [
                        'pattern' => '#^post/(?P<abf396750>[^\/]+)$#u',
                    ],
                    'pattern' => 'post/<id>',
                    'route' => 'post/view',
                ],
            ],
            'cache' => new ArrayCache(),
            'suffix' => '.html',
        ]);
        // cache the rule
        $manager->rules;

        $result = $manager->parseRequest($this->getRequest('post/1.html'));
        $this->assertEquals(['post/view', ['id' => 1]], $result);
    }

    public function testFastParseDataWithInvalidHostAndHostFlag()
    {
        $manager = $this->getUrlManager([
            'rules' => [
                [
                    'class' => 'yiiunit\framework\web\mocks\ConfigurableFastParseDataRule',
                    'fastParseDataConfig' => [
                        'host' => 1,
                        'pattern' => '#^http://www\.wrong\.com/post/(?P<abf396750>[^\/]+)$#u',
                    ],
                    'pattern' => 'post/<id>',
                    'route' => 'post/view',
                    'host' => 'http://www.wrong.com',
                ],
            ],
            'cache' => new ArrayCache(),
        ]);
        // cache the rule
        $manager->rules;

        $result = $manager->parseRequest($this->getRequest('post/1'));
        $this->assertEquals(['post/1', []], $result);
    }

    public function testFastParseDataWithGoodHostAndHostFlag()
    {
        $manager = $this->getUrlManager([
            'rules' => [
                [
                    'class' => 'yiiunit\framework\web\mocks\ConfigurableFastParseDataRule',
                    'fastParseDataConfig' => [
                        'host' => 1,
                        'pattern' => '#^http://www\.example\.com/post/(?P<abf396750>[^\/]+)$#u',
                    ],
                    'pattern' => 'post/<id>',
                    'route' => 'post/view',
                    'host' => 'http://www.example.com',
                ],
            ],
            'cache' => new ArrayCache(),
        ]);
        // cache the rule
        $manager->rules;

        $result = $manager->parseRequest($this->getRequest('post/1'));
        $this->assertEquals(['post/view', ['id' => 1]], $result);
    }
}
