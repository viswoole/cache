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


use InvalidArgumentException;

class RedisConfig
{

  /**
   * @param string $host 连接地址
   * @param int $port 连接端口
   * @param string $password 密码
   * @param int $db_index redis数据库 0-15
   * @param float $timeout 连接超时时间
   * @param int $retry_interval 连接重试时间等待单位毫秒
   * @param float $read_timeout 读取超时时间
   * @param string $prefix 缓存前缀
   * @param int $expire 过期时间，单位秒
   * @param string $tag_store 标签仓库名称(用于存储标签映射列表),不能为空
   * @param int $pool_max_size 连接池最大长度
   * @param int $pool_fill_size 连接池最小长度，如果为0则默认不填充连接池
   */
  public function __construct(
    public readonly string $host = '127.0.0.1',
    public readonly int    $port = 6379,
    public readonly string $password = '',
    public readonly int    $db_index = 0,
    public readonly float  $timeout = 0,
    public readonly int    $retry_interval = 1000,
    public readonly float  $read_timeout = 0,
    public readonly string $prefix = '',
    public readonly int    $expire = 0,
    public readonly string $tag_store = 'TAG_STORE',
    public readonly int    $pool_max_size = 64,
    public readonly int    $pool_fill_size = 0
  )
  {
    if (empty($this->tag_store)) throw new InvalidArgumentException('tag_store can not be empty');
  }
}
