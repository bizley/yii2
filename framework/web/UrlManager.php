<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\caching\CacheInterface;
use yii\di\Instance;
use yii\helpers\Url;

/**
 * UrlManager handles HTTP request parsing and creation of URLs based on a set of rules.
 *
 * UrlManager is configured as an application component in [[\yii\base\Application]] by default.
 * You can access that instance via `Yii::$app->urlManager`.
 *
 * You can modify its configuration by adding an array to your application config under `components`
 * as it is shown in the following example:
 *
 * ```php
 * 'urlManager' => [
 *     'enablePrettyUrl' => true,
 *     'rules' => [
 *         // your rules go here
 *     ],
 *     // ...
 * ]
 * ```
 *
 * Rules are classes implementing the [[UrlRuleInterface]], by default that is [[UrlRule]].
 * For nesting rules, there is also a [[GroupUrlRule]] class.
 *
 * For more details and usage information on UrlManager, see the [guide article on routing](guide:runtime-routing).
 *
 * @property string $baseUrl The base URL that is used by [[createUrl()]] to prepend to created URLs.
 * @property string $hostInfo The host info (e.g. `http://www.example.com`) that is used by
 * [[createAbsoluteUrl()]] to prepend to created URLs.
 * @property string $scriptUrl The entry script URL that is used by [[createUrl()]] to prepend to created
 * URLs.
 * @property array $rules
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class UrlManager extends Component
{
    /**
     * @var bool whether to enable pretty URLs. Instead of putting all parameters in the query
     * string part of a URL, pretty URLs allow using path info to represent some of the parameters
     * and can thus produce more user-friendly URLs, such as "/news/Yii-is-released", instead of
     * "/index.php?r=news%2Fview&id=100".
     */
    public $enablePrettyUrl = false;
    /**
     * @var bool whether to enable strict parsing. If strict parsing is enabled, the incoming
     * requested URL must match at least one of the [[rules]] in order to be treated as a valid request.
     * Otherwise, the path info part of the request will be treated as the requested route.
     * This property is used only when [[enablePrettyUrl]] is `true`.
     */
    public $enableStrictParsing = false;
    /**
     * @var array the rules for creating and parsing URLs when [[enablePrettyUrl]] is `true`.
     * This property is used only if [[enablePrettyUrl]] is `true`. Each element in the array
     * is the configuration array for creating a single URL rule. The configuration will
     * be merged with [[ruleConfig]] first before it is used for creating the rule object.
     *
     * A special shortcut format can be used if a rule only specifies [[UrlRule::pattern|pattern]]
     * and [[UrlRule::route|route]]: `'pattern' => 'route'`. That is, instead of using a configuration
     * array, one can use the key to represent the pattern and the value the corresponding route.
     * For example, `'post/<id:\d+>' => 'post/view'`.
     *
     * For RESTful routing the mentioned shortcut format also allows you to specify the
     * [[UrlRule::verb|HTTP verb]] that the rule should apply for.
     * You can do that  by prepending it to the pattern, separated by space.
     * For example, `'PUT post/<id:\d+>' => 'post/update'`.
     * You may specify multiple verbs by separating them with comma
     * like this: `'POST,PUT post/index' => 'post/create'`.
     * The supported verbs in the shortcut format are: GET, HEAD, POST, PUT, PATCH and DELETE.
     * Note that [[UrlRule::mode|mode]] will be set to PARSING_ONLY when specifying verb in this way
     * so you normally would not specify a verb for normal GET request.
     *
     * Here is an example configuration for RESTful CRUD controller:
     *
     * ```php
     * [
     *     'dashboard' => 'site/index',
     *
     *     'POST <controller:[\w-]+>' => '<controller>/create',
     *     '<controller:[\w-]+>s' => '<controller>/index',
     *
     *     'PUT <controller:[\w-]+>/<id:\d+>'    => '<controller>/update',
     *     'DELETE <controller:[\w-]+>/<id:\d+>' => '<controller>/delete',
     *     '<controller:[\w-]+>/<id:\d+>'        => '<controller>/view',
     * ];
     * ```
     *
     * Note that if you modify this property after the UrlManager object is created, make sure
     * you populate the array with rule objects instead of rule configurations.
     */
    //public $rules = [];
    /**
     * @var string the URL suffix used when [[enablePrettyUrl]] is `true`.
     * For example, ".html" can be used so that the URL looks like pointing to a static HTML page.
     * This property is used only if [[enablePrettyUrl]] is `true`.
     */
    public $suffix;
    /**
     * @var bool whether to show entry script name in the constructed URL. Defaults to `true`.
     * This property is used only if [[enablePrettyUrl]] is `true`.
     */
    public $showScriptName = true;
    /**
     * @var string the GET parameter name for route. This property is used only if [[enablePrettyUrl]] is `false`.
     */
    public $routeParam = 'r';
    /**
     * @var CacheInterface|array|string the cache object or the application component ID of the cache object.
     * This can also be an array that is used to create a [[CacheInterface]] instance in case you do not want to use
     * an application component.
     * Compiled URL rules will be cached through this cache object, if it is available.
     *
     * After the UrlManager object is created, if you want to change this property,
     * you should only assign it with a cache object.
     * Set this property to `false` or `null` if you do not want to cache the URL rules.
     *
     * Cache entries are stored for the time set by [[\yii\caching\Cache::$defaultDuration|$defaultDuration]] in
     * the cache configuration, which is unlimited by default. You may want to tune this value if your [[rules]]
     * change frequently.
     */
    public $cache = 'cache';
    /**
     * @var array the default configuration of URL rules. Individual rule configurations
     * specified via [[rules]] will take precedence when the same property of the rule is configured.
     */
    public $ruleConfig = ['class' => 'yii\web\UrlRule'];
    /**
     * @var UrlNormalizer|array|string|false the configuration for [[UrlNormalizer]] used by this UrlManager.
     * The default value is `false`, which means normalization will be skipped.
     * If you wish to enable URL normalization, you should configure this property manually.
     * For example:
     *
     * ```php
     * [
     *     'class' => 'yii\web\UrlNormalizer',
     *     'collapseSlashes' => true,
     *     'normalizeTrailingSlash' => true,
     * ]
     * ```
     *
     * @since 2.0.10
     */
    public $normalizer = false;

    /**
     * @var string the cache key for cached rules
     * @since 2.0.8
     */
    protected $cacheKey = __CLASS__;

    private $_baseUrl;
    private $_scriptUrl;
    private $_hostInfo;
    private $_ruleCache;
    private $_rulesDeclaration = [];
    private $_rules;
    private $_fastParseData;


    /**
     * Initializes UrlManager.
     */
    public function init()
    {
        parent::init();

        if ($this->normalizer !== false) {
            $this->normalizer = Yii::createObject($this->normalizer);
            if (!$this->normalizer instanceof UrlNormalizer) {
                throw new InvalidConfigException('`' . get_class($this) . '::normalizer` should be an instance of `' . UrlNormalizer::className() . '` or its DI compatible configuration.');
            }
        }

        if (!$this->enablePrettyUrl) {
            return;
        }
        if ($this->cache !== false && $this->cache !== null) {
            try {
                $this->cache = Instance::ensure($this->cache, 'yii\caching\CacheInterface');
            } catch (InvalidConfigException $e) {
                Yii::warning('Unable to use cache for URL manager: ' . $e->getMessage());
            }
        }
    }

    public function setRules($rules)
    {
        $this->_rulesDeclaration = $rules;
        $this->_fastParseData = null;
    }

    public function getRules()
    {
        if (!$this->enablePrettyUrl) {
            return $this->_rulesDeclaration;
        }
        if ($this->_rules === null) {
            $this->_rules = $this->buildRules($this->_rulesDeclaration);
        }

        return array_values($this->_rules);
    }

    public function getFastParseData()
    {
        if (!$this->enablePrettyUrl) {
            return [];
        }
        if ($this->_fastParseData === null) {
            if ($this->_rules === null) {
                $this->_rules = $this->buildRules($this->_rulesDeclaration);
            }
            $this->_fastParseData = $this->buildFastParseData($this->_rules, $this->_rulesDeclaration);
        }

        return $this->_fastParseData;
    }

    /**
     * Adds additional URL rules.
     *
     * @param array $rules the new rules to be added. Each array element represents a single rule declaration.
     * Please refer to [[setRules()]] for the acceptable rule format.
     * @param bool $append whether to add the new rules by appending them to the end of the existing rules.
     * Since 2.0.41 this method does not call [[buildRules()]] anymore and [[enablePrettyUrl]] does not affect it.
     */
    public function addRules($rules, $append = true)
    {
        if (!$this->enablePrettyUrl) {
            return;
        }
        $this->setRules(
            $append
            ? array_merge($this->_rulesDeclaration, $rules)
            : array_merge($rules, $this->_rulesDeclaration)
        );
    }

    /**
     * Builds URL rule objects from the given rule declarations.
     *
     * @param array $ruleDeclarations the rule declarations. Each array element represents a single rule declaration.
     * Please refer to [[rules]] for the acceptable rule formats.
     * @return UrlRuleInterface[] the rule objects built from the given rule declarations
     * @throws InvalidConfigException if a rule declaration is invalid
     */
    protected function buildRules($ruleDeclarations)
    {
        $builtRules = $this->getBuiltRulesFromCache($ruleDeclarations);
        if ($builtRules !== false) {
            return $builtRules;
        }

        $builtRules = [];
        $verbs = 'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS';
        foreach ($ruleDeclarations as $key => $rule) {
            $ruleKey = null;
            if (is_string($rule)) {
                $rule = ['route' => $rule];
                if (preg_match("/^((?:($verbs),)*($verbs))\\s+(.*)$/", $key, $matches)) {
                    $rule['verb'] = explode(',', $matches[1]);
                    // rules that are not applicable for GET requests should not be used to create URLs
                    if (!in_array('GET', $rule['verb'], true)) {
                        $rule['mode'] = UrlRule::PARSING_ONLY;
                    }
                    $key = $matches[4];
                }
                $rule['pattern'] = $key;
            }
            if (is_array($rule)) {
                $ruleKey = serialize($rule);
                $rule = Yii::createObject(array_merge($this->ruleConfig, $rule));
            }
            if (!$rule instanceof UrlRuleInterface) {
                throw new InvalidConfigException('URL rule class must implement UrlRuleInterface.');
            }
            if ($ruleKey === null) {
                // rule is object from the beginning
                $ruleKey = serialize($rule);
            }
            $builtRules[md5($ruleKey)] = $rule;
        }

        $this->setBuiltRulesCache($ruleDeclarations, $builtRules);

        return $builtRules;
    }

    private $_buildingFastParseData;

    /**
     * @param array $rulesData
     * @param array $ruleDeclarations
     * @return array
     * @since 2.0.41
     */
    protected function buildFastParseData($rulesData, $ruleDeclarations)
    {
        $builtFastParseData = $this->getBuiltFastParseDataFromCache($ruleDeclarations);
        if ($builtFastParseData !== false) {
            return $builtFastParseData;
        }

        $this->_buildingFastParseData = [];

        /* @var $rule UrlRuleInterface */
        foreach ($rulesData as $key => $rule) {
            if (!method_exists($rule, 'getFastParseData')) {
                $this->addDataEntry('', ['key' => $key, 'pass' => true]);
                continue;
            }

            $this->processFastParseDataSet($key, $rule->getFastParseData());
        }

        $fastParseData = [];
        // make sure non-prefixed data is last
        foreach ($this->_buildingFastParseData as $prefix => $data) {
            if ($prefix !== '') {
                $fastParseData[$prefix] = $data;
            }
        }
        if (array_key_exists('', $this->_buildingFastParseData)) {
            $fastParseData[''] = $this->_buildingFastParseData[''];
        }
        $this->setBuiltFastParseDataCache($ruleDeclarations, $fastParseData);
        $this->_buildingFastParseData = null;

        return $fastParseData;
    }

    private function processFastParseDataSet($key, $data, $prefix = '')
    {
        if (!is_array($data) || $data === []) {
            $this->addDataEntry('', ['key' => $key, 'pass' => true], $prefix);
            return;
        }

        if (isset($data['skip']) && (bool)$data['skip'] === true) {
            return;
        }

        if (!empty($data['prefix'])) {
            $prefix = $data['prefix'];
        }

        if (!empty($data['group']) && is_array($data['group'])) {
            foreach ($data['group'] as $subRuleData) {
                $this->processFastParseDataSet($key, $subRuleData, $prefix);
            }
            return;
        }

        if (empty($data['pattern'])) {
            $this->addDataEntry('', ['key' => $key, 'pass' => true], $prefix);
            return;
        }
        $ruleFastParseData = ['key' => $key, 'pattern' => $data['pattern']];
        if (!empty($data['suffix'])) {
            $ruleFastParseData['suffix'] = $data['suffix'];
        }
        if (isset($data['host']) && (bool)$data['host'] === true) {
            $ruleFastParseData['host'] = true;
        }
        if (!empty($data['req'])) {
            $ruleFastParseData['req'] = $data['req'];
        }
        $verbs = !empty($data['verb']) && is_array($data['verb']) ? $data['verb'] : [];
        if ($verbs === []) {
            $this->addDataEntry('', $ruleFastParseData, $prefix);
        } else {
            foreach ($verbs as $verb) {
                $this->addDataEntry($verb, $ruleFastParseData, $prefix);
            }
        }
    }

    private function addDataEntry($key, $entry, $prefix = '')
    {
        if (!array_key_exists($prefix, $this->_buildingFastParseData)) {
            $this->_buildingFastParseData[$prefix] = [];
        }
        if (!array_key_exists($key, $this->_buildingFastParseData[$prefix])) {
            $this->_buildingFastParseData[$prefix][$key] = [];
        }
        $this->_buildingFastParseData[$prefix][$key][] = $entry;
    }

    /**
     * Stores $builtRules to cache, using $rulesDeclaration as a part of cache key.
     *
     * @param array $ruleDeclarations the rule declarations. Each array element represents a single rule declaration.
     * Please refer to [[rules]] for the acceptable rule formats.
     * @param UrlRuleInterface[] $builtRules the rule objects built from the given rule declarations.
     * @return bool whether the value is successfully stored into cache
     * @since 2.0.14
     */
    protected function setBuiltRulesCache($ruleDeclarations, $builtRules)
    {
        if (!$this->cache instanceof CacheInterface) {
            return false;
        }

        return $this->cache->set([$this->cacheKey, $this->ruleConfig, $ruleDeclarations], $builtRules);
    }

    /**
     * @param array $ruleDeclarations
     * @param array $builtFastParseData
     * @return bool
     * @since 2.0.41
     */
    protected function setBuiltFastParseDataCache($ruleDeclarations, $builtFastParseData)
    {
        if (!$this->cache instanceof CacheInterface) {
            return false;
        }

        return $this->cache->set(
            [$this->cacheKey . 'FastParseData', $this->ruleConfig, $ruleDeclarations],
            $builtFastParseData
        );
    }

    /**
     * Provides the built URL rules that are associated with the $ruleDeclarations from cache.
     *
     * @param array $ruleDeclarations the rule declarations. Each array element represents a single rule declaration.
     * Please refer to [[rules]] for the acceptable rule formats.
     * @return UrlRuleInterface[]|false the rule objects built from the given rule declarations or boolean `false` when
     * there are no cache items for this definition exists.
     * @since 2.0.14
     */
    protected function getBuiltRulesFromCache($ruleDeclarations)
    {
        if (!$this->cache instanceof CacheInterface) {
            return false;
        }

        return $this->cache->get([$this->cacheKey, $this->ruleConfig, $ruleDeclarations]);
    }

    /**
     * @param array $ruleDeclarations
     * @return array|false
     * @since 2.0.41
     */
    protected function getBuiltFastParseDataFromCache($ruleDeclarations)
    {
        if (!$this->cache instanceof CacheInterface) {
            return false;
        }

        return $this->cache->get([$this->cacheKey . 'FastParseData', $this->ruleConfig, $ruleDeclarations]);
    }

    /**
     * Parses the user request.
     * @param Request $request the request component
     * @return array|bool the route and the associated parameters. The latter is always empty
     * if [[enablePrettyUrl]] is `false`. `false` is returned if the current request cannot be successfully parsed.
     */
    public function parseRequest($request)
    {
        if ($this->enablePrettyUrl) {
            $fastParseData = $this->getFastParseData();
            if ($fastParseData !== []) {
                $pathInfo = $request->getPathInfo();
                $method = $request->getMethod();

                foreach ($fastParseData as $prefix => $data) {
                    if ($prefix === '' || strpos($pathInfo . '/', $prefix . '/') === 0) {
                        // check matching verb data first
                        if (array_key_exists($method, $data)) {
                            $result = $this->fastParseRequestForRuleSet($data[$method], $request);
                            if ($result !== false) {
                                return $result;
                            }
                        }

                        // finally check non-verb data
                        if (array_key_exists('', $data)) {
                            $result = $this->fastParseRequestForRuleSet($data[''], $request);
                            if ($result !== false) {
                                return $result;
                            }
                        }
                    }
                }
            }

            if ($this->enableStrictParsing) {
                return false;
            }

            Yii::debug('No matching URL rules. Using default URL parsing logic.', __METHOD__);

            $suffix = (string) $this->suffix;
            $normalized = false;
            if ($this->normalizer !== false) {
                $pathInfo = $this->normalizer->normalizePathInfo($pathInfo, $suffix, $normalized);
            }

            $result = $this->fitSuffix($suffix, $pathInfo);
            if ($result === false) {
                return false;
            }

            if ($normalized) {
                // pathInfo was changed by normalizer - we need also normalize route
                return $this->normalizer->normalizeRoute([$pathInfo, []]);
            }

            return [$pathInfo, []];
        }

        Yii::debug('Pretty URL not enabled. Using default URL parsing logic.', __METHOD__);
        $route = $request->getQueryParam($this->routeParam, '');
        if (is_array($route)) {
            $route = '';
        }

        return [(string) $route, []];
    }

    /**
     * @param array $fastParseData
     * @param Request $request
     * @return string|false
     * @throws \Exception
     */
    protected function fastParseRequestForRuleSet($fastParseData, $request)
    {
        $requestPathInfo = $request->getPathInfo();
        $requestHostInfo = strtolower($request->getHostInfo());

        foreach ($fastParseData as $data) {
            if (!isset($data['pass']) || !(bool)$data['pass']) {
                $pathInfo = $requestPathInfo;
                $required = !empty($data['req']) ? $data['req'] : null;
                if ($required && strpos($pathInfo, $required) === false) {
                    continue;
                }
                $suffix = !empty($data['suffix']) ? $data['suffix'] : '';
                $suffix = (string)($suffix === '' ? $this->suffix : $suffix);
                $normalized = false;
                if ($this->normalizer !== false && (!isset($data['norm']) || (bool)$data['norm'])) {
                    $pathInfo = $this->normalizer->normalizePathInfo($pathInfo, $suffix, $normalized);
                }
                $result = $this->fitSuffix($suffix, $pathInfo);
                if ($result === false) {
                    continue;
                }
                if (isset($data['host']) && (bool)$data['host']) {
                    $pathInfo = $requestHostInfo . ($pathInfo === '' ? '' : '/' . $pathInfo);
                }
                if (empty($data['pattern']) || !preg_match($data['pattern'], $pathInfo)) {
                    continue;
                }
            }

            if (array_key_exists($data['key'], $this->_rules)) {
                $rule = $this->_rules[$data['key']];
                $result = $rule->parseRequest($this, $request);
                if (YII_DEBUG) {
                    Yii::debug([
                        'rule' => method_exists($rule, '__toString') ? $rule->__toString() : get_class($rule),
                        'match' => $result !== false,
                        'parent' => null,
                    ], __METHOD__);
                }
                if ($result !== false) {
                    return $result;
                }
            }
        }

        return false;
    }

    private function fitSuffix($suffix, &$pathInfo)
    {
        if ($suffix !== '' && $pathInfo !== '') {
            $n = strlen($suffix);
            if (substr_compare($pathInfo, $suffix, -$n, $n) === 0) {
                $pathInfo = substr($pathInfo, 0, -$n);
                if ($pathInfo === '') {
                    // suffix alone is not allowed
                    return false;
                }
            } else {
                // suffix doesn't match
                return false;
            }
        }

        return true;
    }

    /**
     * Creates a URL using the given route and query parameters.
     *
     * You may specify the route as a string, e.g., `site/index`. You may also use an array
     * if you want to specify additional query parameters for the URL being created. The
     * array format must be:
     *
     * ```php
     * // generates: /index.php?r=site%2Findex&param1=value1&param2=value2
     * ['site/index', 'param1' => 'value1', 'param2' => 'value2']
     * ```
     *
     * If you want to create a URL with an anchor, you can use the array format with a `#` parameter.
     * For example,
     *
     * ```php
     * // generates: /index.php?r=site%2Findex&param1=value1#name
     * ['site/index', 'param1' => 'value1', '#' => 'name']
     * ```
     *
     * The URL created is a relative one. Use [[createAbsoluteUrl()]] to create an absolute URL.
     *
     * Note that unlike [[\yii\helpers\Url::toRoute()]], this method always treats the given route
     * as an absolute route.
     *
     * @param string|array $params use a string to represent a route (e.g. `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * @return string the created URL
     */
    public function createUrl($params)
    {
        $params = (array) $params;
        $anchor = isset($params['#']) ? '#' . $params['#'] : '';
        unset($params['#'], $params[$this->routeParam]);

        $route = trim($params[0], '/');
        unset($params[0]);

        $baseUrl = $this->showScriptName || !$this->enablePrettyUrl ? $this->getScriptUrl() : $this->getBaseUrl();

        if ($this->enablePrettyUrl) {
            $cacheKey = $route . '?';
            foreach ($params as $key => $value) {
                if ($value !== null) {
                    $cacheKey .= $key . '&';
                }
            }

            $url = $this->getUrlFromCache($cacheKey, $route, $params);
            if ($url === false) {
                /* @var $rule UrlRule */
                foreach ($this->rules as $rule) {
                    if (in_array($rule, $this->_ruleCache[$cacheKey], true)) {
                        // avoid redundant calls of `UrlRule::createUrl()` for rules checked in `getUrlFromCache()`
                        // @see https://github.com/yiisoft/yii2/issues/14094
                        continue;
                    }
                    $url = $rule->createUrl($this, $route, $params);
                    if ($this->canBeCached($rule)) {
                        $this->setRuleToCache($cacheKey, $rule);
                    }
                    if ($url !== false) {
                        break;
                    }
                }
            }

            if ($url !== false) {
                if (strpos($url, '://') !== false) {
                    if ($baseUrl !== '' && ($pos = strpos($url, '/', 8)) !== false) {
                        return substr($url, 0, $pos) . $baseUrl . substr($url, $pos) . $anchor;
                    }

                    return $url . $baseUrl . $anchor;
                } elseif (strncmp($url, '//', 2) === 0) {
                    if ($baseUrl !== '' && ($pos = strpos($url, '/', 2)) !== false) {
                        return substr($url, 0, $pos) . $baseUrl . substr($url, $pos) . $anchor;
                    }

                    return $url . $baseUrl . $anchor;
                }

                $url = ltrim($url, '/');
                return "$baseUrl/{$url}{$anchor}";
            }

            if ($this->suffix !== null) {
                $route .= $this->suffix;
            }
            if (!empty($params) && ($query = http_build_query($params)) !== '') {
                $route .= '?' . $query;
            }

            $route = ltrim($route, '/');
            return "$baseUrl/{$route}{$anchor}";
        }

        $url = "$baseUrl?{$this->routeParam}=" . urlencode($route);
        if (!empty($params) && ($query = http_build_query($params)) !== '') {
            $url .= '&' . $query;
        }

        return $url . $anchor;
    }

    /**
     * Returns the value indicating whether result of [[createUrl()]] of rule should be cached in internal cache.
     *
     * @param UrlRuleInterface $rule
     * @return bool `true` if result should be cached, `false` if not.
     * @since 2.0.12
     * @see getUrlFromCache()
     * @see setRuleToCache()
     * @see UrlRule::getCreateUrlStatus()
     */
    protected function canBeCached(UrlRuleInterface $rule)
    {
        return
            // if rule does not provide info about create status, we cache it every time to prevent bugs like #13350
            // @see https://github.com/yiisoft/yii2/pull/13350#discussion_r114873476
            !method_exists($rule, 'getCreateUrlStatus') || ($status = $rule->getCreateUrlStatus()) === null
            || $status === UrlRule::CREATE_STATUS_SUCCESS
            || $status & UrlRule::CREATE_STATUS_PARAMS_MISMATCH;
    }

    /**
     * Get URL from internal cache if exists.
     * @param string $cacheKey generated cache key to store data.
     * @param string $route the route (e.g. `site/index`).
     * @param array $params rule params.
     * @return bool|string the created URL
     * @see createUrl()
     * @since 2.0.8
     */
    protected function getUrlFromCache($cacheKey, $route, $params)
    {
        if (!empty($this->_ruleCache[$cacheKey])) {
            foreach ($this->_ruleCache[$cacheKey] as $rule) {
                /* @var $rule UrlRule */
                if (($url = $rule->createUrl($this, $route, $params)) !== false) {
                    return $url;
                }
            }
        } else {
            $this->_ruleCache[$cacheKey] = [];
        }

        return false;
    }

    /**
     * Store rule (e.g. [[UrlRule]]) to internal cache.
     * @param $cacheKey
     * @param UrlRuleInterface $rule
     * @since 2.0.8
     */
    protected function setRuleToCache($cacheKey, UrlRuleInterface $rule)
    {
        $this->_ruleCache[$cacheKey][] = $rule;
    }

    /**
     * Creates an absolute URL using the given route and query parameters.
     *
     * This method prepends the URL created by [[createUrl()]] with the [[hostInfo]].
     *
     * Note that unlike [[\yii\helpers\Url::toRoute()]], this method always treats the given route
     * as an absolute route.
     *
     * @param string|array $params use a string to represent a route (e.g. `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * @param string|null $scheme the scheme to use for the URL (either `http`, `https` or empty string
     * for protocol-relative URL).
     * If not specified the scheme of the current request will be used.
     * @return string the created URL
     * @see createUrl()
     */
    public function createAbsoluteUrl($params, $scheme = null)
    {
        $params = (array) $params;
        $url = $this->createUrl($params);
        if (strpos($url, '://') === false) {
            $hostInfo = $this->getHostInfo();
            if (strncmp($url, '//', 2) === 0) {
                $url = substr($hostInfo, 0, strpos($hostInfo, '://')) . ':' . $url;
            } else {
                $url = $hostInfo . $url;
            }
        }

        return Url::ensureScheme($url, $scheme);
    }

    /**
     * Returns the base URL that is used by [[createUrl()]] to prepend to created URLs.
     * It defaults to [[Request::baseUrl]].
     * This is mainly used when [[enablePrettyUrl]] is `true` and [[showScriptName]] is `false`.
     * @return string the base URL that is used by [[createUrl()]] to prepend to created URLs.
     * @throws InvalidConfigException if running in console application and [[baseUrl]] is not configured.
     */
    public function getBaseUrl()
    {
        if ($this->_baseUrl === null) {
            $request = Yii::$app->getRequest();
            if ($request instanceof Request) {
                $this->_baseUrl = $request->getBaseUrl();
            } else {
                throw new InvalidConfigException('Please configure UrlManager::baseUrl correctly as you are running a console application.');
            }
        }

        return $this->_baseUrl;
    }

    /**
     * Sets the base URL that is used by [[createUrl()]] to prepend to created URLs.
     * This is mainly used when [[enablePrettyUrl]] is `true` and [[showScriptName]] is `false`.
     * @param string $value the base URL that is used by [[createUrl()]] to prepend to created URLs.
     */
    public function setBaseUrl($value)
    {
        $this->_baseUrl = $value === null ? null : rtrim(Yii::getAlias($value), '/');
    }

    /**
     * Returns the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     * It defaults to [[Request::scriptUrl]].
     * This is mainly used when [[enablePrettyUrl]] is `false` or [[showScriptName]] is `true`.
     * @return string the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     * @throws InvalidConfigException if running in console application and [[scriptUrl]] is not configured.
     */
    public function getScriptUrl()
    {
        if ($this->_scriptUrl === null) {
            $request = Yii::$app->getRequest();
            if ($request instanceof Request) {
                $this->_scriptUrl = $request->getScriptUrl();
            } else {
                throw new InvalidConfigException('Please configure UrlManager::scriptUrl correctly as you are running a console application.');
            }
        }

        return $this->_scriptUrl;
    }

    /**
     * Sets the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     * This is mainly used when [[enablePrettyUrl]] is `false` or [[showScriptName]] is `true`.
     * @param string $value the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     */
    public function setScriptUrl($value)
    {
        $this->_scriptUrl = $value;
    }

    /**
     * Returns the host info that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     * @return string the host info (e.g. `http://www.example.com`) that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     * @throws InvalidConfigException if running in console application and [[hostInfo]] is not configured.
     */
    public function getHostInfo()
    {
        if ($this->_hostInfo === null) {
            $request = Yii::$app->getRequest();
            if ($request instanceof \yii\web\Request) {
                $this->_hostInfo = $request->getHostInfo();
            } else {
                throw new InvalidConfigException('Please configure UrlManager::hostInfo correctly as you are running a console application.');
            }
        }

        return $this->_hostInfo;
    }

    /**
     * Sets the host info that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     * @param string $value the host info (e.g. "http://www.example.com") that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     */
    public function setHostInfo($value)
    {
        $this->_hostInfo = $value === null ? null : rtrim($value, '/');
    }
}
