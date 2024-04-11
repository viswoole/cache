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

use Closure;
use DateTime;
use ViSwoole\Cache\Contract\CacheDriverInterface;
use ViSwoole\Cache\Contract\CacheTagInterface;
use ViSwoole\Cache\Driver\File;
use ViSwoole\Cache\Driver\Redis;
use ViSwoole\Cache\Exception\CacheErrorException;
use ViSwoole\Core\Facades\Config;

/**
 * 缓存驱动管理器
 *
 * @method static int|false inc(string $key, int $step = 1) 自增缓存（针对数值缓存）
 * @method static mixed get(string $key, mixed $default = null) 获取缓存
 * @method static bool unlock(string $id) 解锁
 * @method static bool set(string $key, mixed $value, DateTime|int|null $expire = null, bool $NX = false) 设置缓存
 * @method static int|false ttl(string $key) 获取缓存剩余有效期 -1为长期有效 false为不存在或过期
 * @method static int|false dec(string $key, int $step = 1) 自减缓存
 * @method static mixed pull(string $key) 获取缓存并删除
 * @method static int|false delete(array|string $keys) 删除缓存
 * @method static bool has(string $key) 判断缓存是否存在
 * @method static bool clear() 清除所有缓存
 * @method static string lock(string $scene, int $expire = 10, bool $autoUnlock = false, int $retry = 5, int|float $sleep = 0.2) 获取竞争锁
 * @method static void close() 关闭连接句柄（如果不手动调用则会在实例销毁时自动调用）
 * @method static File connect() 获取连接句柄
 * @method static int|false sAddArray(string $key, array|string $values) 往数组集合中追加值
 * @method static array|false getArray(string $key) 获取数组集合
 * @method static int|false sRemoveArray(string $key, array|string $values) 删除数组集合中的值
 * @method static CacheDriverInterface setSerialize(Closure|string $set = 'serialize', Closure|string $get = 'unserialize') 设置序列化方法
 * @method static string getTagKey(string $tag) 获取标签key
 * @method static CacheTagInterface tag(array|string $tag) 标签
 * @method static array|false getTags() 获取所有缓存标签
 * @method static string getTagStoreName() 获取标签仓库名称
 * @method static string getCacheKey(string $key) 获取实际的缓存标识
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
    $config = self::getConfig($name);
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
