<?php
declare(strict_types=1);

namespace HZEX\Think\Cors;

/**
 * @param string $haystack
 * @param string $needle
 * @return bool
 * @see https://stackoverflow.com/a/10473026/10242420
 */
function str_starts_with(string $haystack, string $needle): bool
{
    return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
}

/**
 * @param string $haystack
 * @param string $needle
 * @return bool
 * @see https://stackoverflow.com/a/10473026/10242420
 */
function str_ends_with(string $haystack, string $needle): bool
{
    return substr_compare($haystack, $needle, -strlen($needle)) === 0;
}