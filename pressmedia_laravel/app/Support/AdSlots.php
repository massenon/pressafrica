<?php

namespace App\Support;

class AdSlots
{
    /** Inserts $html after the $n-th top-level paragraph of an article body (at the end when it is shorter). */
    public static function afterParagraph(string $body, int $n, string $html): string
    {
        $pos = -1;
        for ($i = 0; $i < $n; $i++) {
            $next = stripos($body, '</p>', $pos + 1);
            if ($next === false) {
                return $body.$html;
            }
            $pos = $next;
        }

        return substr_replace($body, $html, $pos + 4, 0);
    }
}
