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

namespace ViSwoole\Cache\Driver;

use DateTime;
use FilesystemIterator;
use Override;
use Swoole\Coroutine\System;
use Throwable;
use ViSwoole\Cache\Driver;
use ViSwoole\Cache\Exception\CacheErrorException;

class File extends Driver
{
  protected string $storage;
  /**
   * @var array 锁
   */
  private array $lockList = [];

  /**
   * @param string $storage 存储目录
   * @param string $prefix 前缀
   * @param string $tag_store 标签仓库名称(用于存储标签映射列表)
   * @param int $expire 过期时间 默认0不过期
   */
  public function __construct(
    string           $storage = BASE_PATH . '/runtime/cache',
    protected string $prefix = '',
    protected string $tag_store = 'TAG_STORE',
    protected int    $expire = 0
  )
  {
    $this->storage = rtrim($storage, '/');
  }

  /**
   * 自增缓存（针对数值缓存）
   *
   * @access public
   * @param string $key 缓存标识
   * @param int $step 步长
   * @return false|int
   */
  #[Override] public function inc(string $key, int $step = 1): bool|int
  {
    $data = $this->get($key);
    if (is_float($data) || is_int($data)) {
      $data += $step;
      return $this->set($key, $data);
    } else {
      throw new CacheErrorException('缓存值非数值，不能调用自增方法。');
    }
  }

  /**
   * 读取缓存
   *
   * @access public
   * @param string $key 不带前缀的名称
   * @param mixed $default 默认值
   * @return mixed
   */
  #[Override] public function get(string $key, mixed $default = null): mixed
  {
    return $this->getRaw($key) ?? $default;
  }

  /**
   * 获取缓存内容
   *
   * @param string $key
   * @return mixed|null
   */
  protected function getRaw(string $key): mixed
  {
    $filename = $this->filename($key);
    if (!is_file($filename)) return null;

    $fileContent = file_get_contents($filename);

    $expirePattern = '/^expire\((\d+)\)/';

    if (preg_match($expirePattern, $fileContent, $matches)) {
      $fileExpireTime = (int)$matches[1];
      if ($fileExpireTime < time()) {
        // 文件已经过期，删除文件
        $this->unlink($filename);
        return null;
      } else {
        // 文件尚未过期，提取数据并反序列化
        $data = substr($fileContent, strlen($matches[0]));
        return $this->unserialize($data);
      }
    } else {
      // 文件没有设置到期时间，永久有效
      return $this->unserialize($fileContent);
    }
  }

  /**
   * 获取文件名
   *
   * @param string $key 缓存标识
   * @return string
   */
  protected function filename(string $key): string
  {
    $key = $this->getCacheKey($key);
    return $this->dir() . $key;
  }

  /**
   * 获取存储目录
   *
   * @param string $dir
   * @return string
   */
  protected function dir(string $dir = ''): string
  {
    if (str_starts_with($dir, '/')) {
      $dir = $this->storage . $dir;
    } else {
      $dir = $this->storage . DIRECTORY_SEPARATOR . $dir;
    }
    // 创建目录（如果不存在）
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
    return str_ends_with($dir, DIRECTORY_SEPARATOR) ? $dir : $dir . DIRECTORY_SEPARATOR;
  }

  /**
   * 删除文件
   *
   * @param string $path
   * @return bool
   */
  protected function unlink(string $path): bool
  {
    try {
      $result = is_file($path) && unlink($path);
      $dir = dirname($path);
      // 如果目录为空，删除目录
      if (count(glob($dir . '/*')) === 0) rmdir($dir);
      return $result;
    } catch (Throwable) {
      return false;
    }
  }

  /**
   * 写入缓存
   *
   * @access public
   * @param string $key 缓存标识
   * @param mixed $value 存储数据
   * @param DateTime|int $expire 有效时间（秒）
   * @param bool $NX 如果为true则缓存不存在才会写入
   * @return bool
   */
  #[Override] public function set(
    string       $key,
    mixed        $value,
    DateTime|int $expire = 0,
    bool         $NX = false
  ): bool
  {
    return $this->setRaw($key, $value, $expire, $NX);
  }

  /**
   * 写入缓存
   *
   * @param string $key
   * @param mixed $value 记录值
   * @param DateTime|int|null $expire 过期时间
   * @param bool $NX 如果不存在则写入
   * @return bool
   */
  protected function setRaw(string $key, mixed $value, DateTime|int $expire = null, bool $NX = false
  ): bool
  {
    $filename = $this->filename($key);
    $data = $this->serialize($value);
    $expire = $expire === null ? $this->expire : $this->getExpireTime($expire);
    // 判断是否需要设置过期时间
    if ($expire > 0) {
      $expire = time() + $expire;
      $data = "expire($expire)$data";
    }
    if ($NX) {
      // 如果是文件不存在则写入
      if (!is_file($filename)) {
        $result = file_put_contents($filename, $data, LOCK_EX | LOCK_NB);
      } else {
        // 文件存在则判断文件是否过期，过期则写入
        $content = file_get_contents($filename);
        if ($this->isExpire($content)) {
          $result = file_put_contents($filename, $data, LOCK_EX | LOCK_NB);
        } else {
          $result = false;
        }
      }
    } else {
      $result = file_put_contents($filename, $data);
    }
    clearstatcache();
    return (bool)$result;
  }

  /**
   * 判断是否过期
   *
   * @param string $fileContent
   * @return bool
   */
  protected function isExpire(string $fileContent): bool
  {
    $expirePattern = '/^expire\((\d+)\)/';
    if (preg_match($expirePattern, $fileContent, $matches)) {
      $fileExpireTime = (int)$matches[1];
      if ($fileExpireTime <= time()) return true;
    }
    return false;
  }

  /**
   * 自减缓存（针对数值缓存）
   *
   * @access public
   * @param string $key 缓存标识
   * @param int $step 步长
   * @return false|int
   */
  #[Override] public function dec(string $key, int $step = 1): bool|int
  {
    $data = $this->get($key);
    if (is_float($data) || is_int($data)) {
      $data -= $step;
      return $this->set($key, $data);
    } else {
      throw new CacheErrorException('缓存值非数值，不能调用自减方法。');
    }
  }

  /**
   * 追加（数组）缓存
   *
   * @access public
   * @param string $key 缓存标识
   * @param mixed $value 存储数据
   * @return void
   */
  #[Override] public function push(string $key, mixed $value): void
  {
    $item = $this->get($key, []);

    if (!is_array($item)) throw new CacheErrorException('only array cache can be push');

    $item[] = $value;

    $item = array_unique($item);

    $this->set($key, $item);
  }

  /**
   * 读取缓存并删除
   *
   * @access public
   * @param string $key 缓存标识
   * @return mixed
   */
  #[Override] public function pull(string $key): mixed
  {
    $result = $this->get($key, false);

    if ($result !== false) $this->delete($key);

    return $result;
  }

  /**
   * 删除缓存
   *
   * @access public
   * @param array|string $keys
   * @return false|int
   */
  #[Override] public function delete(array|string $keys): false|int
  {
    if (is_string($keys)) $keys = [$keys];
    $number = 0;
    foreach ($keys as $name) {
      $filename = $this->filename($name);
      $result = $this->unlink($filename);
      if ($result) $number++;
    }
    return $number === 0 ? false : $number;
  }

  /**
   * 判断缓存
   *
   * @access public
   * @param string $key 缓存标识
   * @return bool
   */
  #[Override] public function has(string $key): bool
  {
    return is_file($this->filename($key));
  }

  /**
   * 清除所有缓存
   *
   * @access public
   * @return bool
   */
  #[Override] public function clear(): bool
  {
    return $this->rmdir($this->dir());
  }

  /**
   * 删除目录
   *
   * @param string $dirname
   * @return bool
   */
  protected function rmdir(string $dirname): bool
  {
    if (!is_dir($dirname)) return true;

    $items = new FilesystemIterator($dirname);

    foreach ($items as $item) {
      if ($item->isDir() && !$item->isLink()) {
        $this->rmdir($item->getPathname());
      } else {
        $this->unlink($item->getPathname());
      }
    }
    return rmdir($dirname);
  }

  /**
   * 取锁/上锁
   *
   * @access public
   * @param string $scene 业务场景
   * @param int $expire 锁过期时间/秒
   * @param bool $autoUnlock 在程序运行完毕后自动解锁默认false
   * @param int $retry 等待尝试次数
   * @param int|float $sleep 等待休眠时间/秒 最小精度为毫秒（0.001 秒）
   * @return string 成功返回锁id 失败抛出系统繁忙错误
   */
  #[Override] public function lock(
    string    $scene,
    int       $expire = 10,
    bool      $autoUnlock = false,
    int       $retry = 5,
    float|int $sleep = 0.2
  ): string
  {
    $expire = $expire <= 0 ? null : time() + $expire;

    if ($retry <= 0) $retry = 1;

    $result = false;

    $scene = $this->getLockKey($scene);

    $filename = $this->getLockFilename($scene);

    $lockId = md5(uniqid("{$scene}_", true));

    $data = $expire ? "expire($expire)$lockId" : $lockId;

    while ($retry-- > 0) {

      $lockHandle = fopen($filename, 'w');

      if (flock($lockHandle, LOCK_EX | LOCK_NB)) {
        // 读取文件内容
        $fileContent = file_get_contents(stream_get_meta_data($lockHandle)['uri']);
        //如果上一个锁还未过期则取锁失败
        if (!$this->isExpire($fileContent)) break;
        // 将锁ID写入锁文件
        fwrite($lockHandle, $data);
        // 刷新文件缓冲区
        fflush($lockHandle);
        // 记录到锁列表
        $this->lockList[$lockId] = [
          'scene' => $scene,
          'secretKey' => $data,
          'autoUnlock' => $autoUnlock,
          'lockHandle' => $lockHandle
        ];
        $result = true;
        break;
      } else {
        //未获得锁 休眠
        System::sleep($sleep);
      }
    }
    if ($result === false) throw new CacheErrorException('缓存系统繁忙，请稍后重试');
    return $lockId;
  }

  /**
   * 获取锁缓存名
   *
   * @param string $scene
   * @return string
   */
  private function getLockKey(string $scene): string
  {
    return $this->getCacheKey('lock_' . $scene);
  }

  /**
   * 获取锁文件
   *
   * @param $scene
   * @return string
   */
  private function getLockFilename($scene): string
  {
    $dir = $this->dir('/lock');
    return $dir . $this->getCacheKey($scene);
  }

  /**
   * 对象销毁解锁
   */
  public function __destruct()
  {
    $this->close();
  }

  /**
   * 关闭连接(实例销毁会自动关闭连接/归还连接到连接池)
   *
   * @return void
   */
  #[Override] public function close(): void
  {
    foreach ($this->lockList as $lockId => $lockInfo) {
      if ($lockInfo['autoUnlock']) $this->unlock($lockId);
    }
  }

  /**
   * 解除锁
   *
   * @access public
   * @param string $id 通过lock方法返回的锁ID
   * @return bool 解锁成功返回true，否则返回false
   */
  #[Override] public function unlock(string $id): bool
  {
    if (empty($this->lockList)) return false;
    if (!isset($this->lockList[$id])) return false;
    $lockInfo = $this->lockList[$id];
    $lockHandle = $lockInfo['lockHandle'];
    $secretKey = $lockInfo['secretKey'];
    if (is_resource($lockHandle)) {
      // 读取文件内容
      $fileContent = file_get_contents(stream_get_meta_data($lockHandle)['uri']);
      if ($fileContent === $secretKey) {
        // 如果文件内容与锁的secretKey匹配，解锁
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        unset($this->lockList[$id]);
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  /**
   * 获取当前对象实例
   *
   * @return File
   */
  #[Override] public function handler(): File
  {
    return $this;
  }

  /**
   * 往数组集合中添加记录
   *
   * @access public
   * @param string $key 缓存名称
   * @param array|string $array 集合
   * @return false|int 如果写入的值已存在则会返回false，其他返回写入的数量
   */
  #[Override] public function sAddArray(
    string       $key,
    array|string $array,
  ): false|int
  {
    if (is_string($array)) $array = [$array];

    $oldArray = $this->getArray($key);

    $oldArray = $oldArray === false ? [] : $oldArray;

    $newArray = array_merge($oldArray, $array);

    $oldLen = count($oldArray);

    $newLen = count($newArray);

    if ($oldLen === $newLen) return false;
    $result = $this->set($key, $newArray);
    return $result ? $newLen - $oldLen : false;
  }

  /**
   * 获取数组集合
   *
   * @access public
   * @param string $key 集合名称
   * @return array|false
   */
  #[Override] public function getArray(string $key): array|false
  {
    $array = $this->get($key, []);
    if (empty($array)) return false;
    return $array;
  }

  /**
   * 从集合中移除元素
   *
   * @access public
   * @param string $key 集合名称
   * @param array|string $values 要删除的值
   * @return false|int
   */
  #[Override] public function sRemoveArray(
    string       $key,
    array|string $values,
  ): false|int
  {
    $array = $this->getArray($key);
    if ($array === false) return false;
    if (is_string($values)) $values = [$values];
    $newArray = array_filter($array, function ($value) use ($values, &$count) {
      return !in_array($value, $values);
    });
    $count = count($array) - count($newArray);
    $result = $this->set($key, $newArray);
    return $result ? $count : false;
  }
}
