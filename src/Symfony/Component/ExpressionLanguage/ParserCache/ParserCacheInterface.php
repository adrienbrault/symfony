<?php

namespace Symfony\Component\ExpressionLanguage\ParserCache;

use Symfony\Component\ExpressionLanguage\ParsedExpression;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
interface ParserCacheInterface
{
    /**
     * @param  string                $key
     * @param  ParsedExpression      $data
     */
    public function save($key, ParsedExpression $expression);

    /**
     * @param  string                $key
     * @return ParsedExpression|null
     */
    public function fetch($key);
}
