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
use ViSwoole\Core\ServiceProvider;

/**
 * 缓存服务注册
 */
class CacheService extends ServiceProvider
{
  /**
   * @inheritDoc
   */
  #[Override] public function boot(): void
  {
    $this->app->make('redis');
  }

  /**
   * @inheritDoc
   */
  #[Override] public function register(): void
  {
    $this->app->bind('cache', Cache::class);
    $this->app->bind('redis', RedisManager::class);
  }
}
