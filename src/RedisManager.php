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

use Override;
use ViSwoole\Core\Channel\ChannelManager;
use ViSwoole\Core\Channel\Contract\ConnectionPoolInterface;
use ViSwoole\Core\Exception\ConnectionPoolException;

/**
 * redis通道管理器
 *
 * @see ChannelManager
 */
class RedisManager extends ChannelManager
{
  protected function __construct()
  {
    $channels = config('redis.channels');
    $defaultChannel = config('redis.default');
    if (empty($defaultChannel)) {
      $defaultChannel = array_keys($channels)[0] ?? '';
    }
    parent::__construct($channels, $defaultChannel);
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
   * @inheritDoc
   * @return RedisPool|ConnectionPoolInterface
   */
  public function getChannel(?string $channel_name = null): RedisPool|ConnectionPoolInterface
  {
    return parent::getChannel($channel_name);
  }

  /**
   * @inheritDoc
   */
  #[Override] protected function createPool(mixed $config): ConnectionPoolInterface
  {
    if (is_array($config)) $config = new RedisConfig(...$config);
    if (!$config instanceof RedisConfig) {
      throw new ConnectionPoolException(
        'Redis通道配置错误，必须是键值对数组或\ViSwoole\Cache\RedisConfig实例'
      );
    }
    return new RedisPool($config);
  }
}
