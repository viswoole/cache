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
use Override;
use ViSwoole\Cache\Contract\CacheDriverInterface;
use ViSwoole\Cache\Contract\CacheTagInterface;

abstract class Driver implements CacheDriverInterface
{
  /**
   * @var string 缓存前缀
   */
  protected string $prefix;
  /**
   * @var int 缓存默认的过期时间
   */
  protected int $expire = 0;
  /**
   * @var string 缓存标签库名称
   */
  protected string $tag_store = 'TAG_STORE';
  /**
   * @var array 序列化
   */
  protected array $serialize = [
    'get' => 'unserialize',
    'set' => 'serialize'
  ];

  /**
   * 获取实际的缓存标识
   *
   * @access public
   * @param string $key 缓存名
   * @return string
   */
  #[Override] public function getCacheKey(string $key): string
  {
    return $this->prefix . $key;
  }

  /**
   * 设置序列化方法
   *
   * @access public
   * @param string|Closure $set
   * @param string|Closure $get
   * @return $this
   */
  #[Override] public function setSerialize(
    string|Closure $set = 'serialize', string|Closure $get = 'unserialize'
  ): static
  {
    $this->serialize = [
      'set' => $set,
      'get' => $get
    ];
    return $this;
  }

  /**
   * 获取实际标签名
   *
   * @access public
   * @param string $tag 标签名
   * @return string
   */
  #[Override] public function getTagKey(string $tag): string
  {
    return $this->prefix . $tag;
  }

  /**
   * 标签
   *
   * @access public
   * @param string|array $tag
   * @return CacheTagInterface
   */
  #[Override] public function tag(array|string $tag): CacheTagInterface
  {
    return new Tag($tag, $this);
  }

  /**
   * 获取所有缓存标签
   *
   * @access public
   * @return array|false
   */
  #[Override] public function getTags(): array|false
  {
    return $this->getArray($this->getTagStoreName(), false);
  }

  /**
   * 获取标签仓库名称
   *
   * @return string
   */
  public function getTagStoreName(): string
  {
    return $this->prefix . $this->tag_store;
  }

  /**
   * 获取有效期
   *
   * @access protected
   * @param DateTime|int $expire 有效期
   * @return int 秒
   */
  protected function expireTimeToInt(DateTime|int $expire): int
  {
    if ($expire instanceof DateTime) {
      $expire = $expire->getTimestamp() - time();
    }
    return (int)$expire;
  }

  /**
   * 序列化数据
   * @access protected
   * @param mixed $data 缓存数据
   * @return string
   */
  protected function serialize(mixed $data): string
  {
    $serialize = $this->serialize['set'] ?? 'serialize';
    return $serialize($data);
  }

  /**
   * 反序列化数据
   * @access protected
   * @param string $data 缓存数据
   * @return string
   */
  protected function unserialize(string $data): string
  {
    $unserialize = $this->serialize['get'] ?? 'unserialize';
    return $unserialize($data);
  }
}
