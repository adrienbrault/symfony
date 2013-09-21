<?php

namespace Symfony\Component\ExpressionLanguage\ParserCache;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class ArrayParserCache implements ParserCacheInterface
{
    /**
     * @var array
     */
    private $cache = array();

    /**
     * {@inheritdoc}
     */
    public function fetch($key)
    {
        return isset($this->cache[$key]) ? $this->cache[$key] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function save($key, ParsedExpression $expression)
    {
        $this->cache[$key] = $expression;
    }
}
