<?php

namespace Stringy;

if (!\function_exists('Stringy\create')) {
    /**
     * Creates a Stringy object and returns it on success.
     *
     * @param mixed  $str      Value to modify, after being cast to string
     * @param string $encoding The character encoding
     *
     * @return Stringy A Stringy object
     *
     * @throws \InvalidArgumentException if an array or object without a
     *                                   __toString method is passed as the first argument
     *
     */
    function create($str, string $encoding = null)
    {
        return new Stringy($str, $encoding);
    }
}

if (!\function_exists('Stringy\collection')) {
    /**
     * @param string[]|Stringy[]|null $input
     *
     * @return CollectionStringy
     *
     * @throws \TypeError
     */
    function collection($input = null)
    {
        // init
        $newCollection = new CollectionStringy();

        if ($input === null) {
            return $newCollection;
        }

        if (!\is_array($input)) {
            $input = [$input];
        }

        foreach ($input as &$stringOrStringy) {
            if (\is_string($stringOrStringy)) {
                $stringOrStringy = new Stringy($stringOrStringy);
            }

            $newCollection[] = $stringOrStringy;
        }

        return $newCollection;
    }
}
