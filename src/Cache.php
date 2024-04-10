<?php
/*
 *  +----------------------------------------------------------------------
 *  | ViSwoole [基于swoole开发的高性能快速开发框架]
 *  +----------------------------------------------------------------------
 *  | Copyright (c) 2024
 *  +----------------------------------------------------------------------
 *  | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
 *  +----------------------------------------------------------------------
 *  | Author: ZhuChongLin <8210856@qq.com>
 *  +----------------------------------------------------------------------
 */

declare (strict_types=1);

namespace ViSwoole\Cache;

use ViSwoole\Cache\Contract\CacheDriverInterface;
use ViSwoole\Cache\Driver\File;
use ViSwoole\Cache\Driver\Redis;
use ViSwoole\Cache\Exception\CacheErrorException;
use ViSwoole\Core\Facades\Config;

/**
 * 缓存类
 */
class Cache
{
  /**
   * @var string 文件缓存驱动器
   */
  public const string DRIVER_FILE = File::class;
  /**
   * @var string REDIS缓存驱动器
   */
  public const string DRIVER_REDIS = Redis::class;
  /**
   * @var string 默认缓存商店
   */
  protected string $defaultStore;
  /**
   * @var array{string,array{driver:string,options:array}} 缓存商店列表
   */
  protected array $stores;

  protected function __construct()
  {
    $stores = config('cache.stores', []);
    $this->stores = $stores;
    if (!empty($this->stores)) {
      $default = config('cache.default');
      $this->defaultStore = empty($default) ? array_keys($stores)[0] : $default;
      foreach ($this->stores as $key => $config) {
        $driver = $config['driver'] ?? '';
        if (!in_array(CacheDriverInterface::class, class_implements($driver))) {
          throw new CacheErrorException(
            $key . '缓存驱动配置错误，驱动类需实现' . CacheDriverInterface::class . '接口'
          );
        }
        if (empty($config['options'])) $config['options'] = [];
      }
    }
  }

  /**
   * 容器make实例化
   */
  public static function __make(): static
  {
    return self::factory();
  }

  /**
   * 工厂单例模式
   */
  public static function factory(): static
  {
    static $instance = null;
    if ($instance === null) $instance = new static();
    return $instance;
  }

  /**
   * 判断是否存在该缓存商店
   *
   * @access public
   * @param string $name
   * @return bool
   */
  public static function hasStore(string $name): bool
  {
    $name = Config::formatConfigKey($name);
    return isset(self::factory()->stores[$name]);
  }

  public static function __callStatic(string $name, array $arguments)
  {
    return call_user_func_array([self::store(), $name], $arguments);
  }

  /**
   * 指定缓存驱动
   *
   * @access public
   * @param string|null $name
   * @return CacheDriverInterface
   */
  public static function store(string $name = null): CacheDriverInterface
  {
    if (empty(self::factory()->stores)) throw new CacheErrorException(
      '缓存商店为空，请先配置缓存商店'
    );
    if (is_null($name)) $name = self::factory()->defaultStore;
    $config = self::factory()->getConfig($name);
    if (is_null($config)) throw new CacheErrorException("缓存商店{$name}不存在");
    $driver = $config['driver'];
    return new $driver(...$config['options']);
  }

  /**
   * 获取缓存商店配置
   *
   * @param string|null $store_name 商店名称,如果传null则获取所有
   * @return array{string,array{driver:string,options:array}}|null 如果返回null则代表缓存商店不存在
   */
  public static function getConfig(?string $store_name = null): ?array
  {
    if (is_null($store_name)) return self::factory()->stores;
    $store_name = Config::formatConfigKey($store_name);
    return self::factory()->stores[$store_name] ?? null;
  }

  /**
   * 转发调用
   *
   * @param string $name
   * @param array $arguments
   * @return mixed
   */
  public function __call(string $name, array $arguments)
  {
    return call_user_func_array([self::store(), $name], $arguments);
  }
}
