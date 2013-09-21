<?php

namespace Symfony\Component\ExpressionLanguage\ParserCache;

use Doctrine\Common\Cache\Cache;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class DoctrineParserCache implements ParserCacheInterface
{
    /**
     * @var Cache
     */
    private $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($key)
    {
        return $this->cache->fetch($key);
    }

    /**
     * {@inheritdoc}
     */
    public function save($key, ParsedExpression $expression)
    {
        $this->cache->save($key, $expression);
    }
}
