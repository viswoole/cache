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

namespace ViSwoole\Cache\Facades;

use Override;
use ViSwoole\Core\Channel\ChannelManagerBaseFacade;

/**
 * Redis管理器门面
 * @see ChannelManagerBaseFacade
 */
class RedisManager extends ChannelManagerBaseFacade
{

  /**
   * @inheritDoc
   */
  #[Override] protected static function getFacadeClass(): string
  {
    return \ViSwoole\Cache\RedisManager::class;
  }
}
