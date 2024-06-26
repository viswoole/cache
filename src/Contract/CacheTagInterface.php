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

namespace ViSwoole\Cache\Contract;

use DateTime;

interface CacheTagInterface
{
  public function __construct(string|array $tags, CacheDriverInterface $driver);

  /**
   * 写入缓存
   *
   * @access public
   * @param string $key 缓存名称
   * @param mixed $value 存储数据
   * @param DateTime|int $expire 有效时间（秒）
   * @param bool $NX 仅在缓存不存在时设置
   * @return bool
   */
  public function set(string $key, mixed $value, DateTime|int $expire = -1, bool $NX = false): bool;

  /**
   * 清除标签缓存
   *
   * @access public
   * @return void
   */
  public function clear(): void;

  /**
   * 删除对应标签下的缓存
   *
   * @param string|array $keys 要删除的缓存
   * @return void
   */
  public function remove(string|array $keys): void;

  /**
   * 追加缓存标识到标签
   *
   * @access public
   * @param string $key
   * @return bool
   */
  public function push(string $key): bool;

  /**
   * 获取标签集合中的缓存键
   *
   * @return array 如果是多个tag返回[tag=>keyList,...tag=>keyList]，否则返回[key,...key]
   */
  public function get(): array;
}
