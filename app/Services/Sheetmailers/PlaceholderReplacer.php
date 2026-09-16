<?php

namespace App\Services\Sheetmailers;

class PlaceholderReplacer
{
    /**
     * The {{column_name}} form of a placeholder, for displaying the token itself
     * (e.g. as a table column heading) rather than substituting it. Built here,
     * not inline in a .blade.php file - Blade's echo compilation runs multiple
     * naive text passes (raw, then escaped, then regular) over the whole compiled
     * template, so a literal "{{"/"}}" pair written directly in a template, even
     * inside a {!! !!} raw-echo expression's own PHP string, gets re-matched and
     * mangled by the next pass instead of being left alone.
     */
    public static function token(string $key): string
    {
        return '{{' . $key . '}}';
    }

    /**
     * Replace every {{column_name}} token in $text with that recipient's value
     * for the matching spreadsheet column (see RecipientListParser). A token with
     * no matching column is left as-is rather than blanked, so a typo in the
     * template is obvious in the sent mail instead of silently disappearing.
     */
    public static function replace(?string $text, array $placeholders): string
    {
        if ($text === null || $text === '') {
            return (string) $text;
        }

        // The rich-text editor wraps only the selected characters when formatting
        // (bold/italic/...) is applied to part of a token - selecting just the last
        // "}" and italicising it stores "...place1}<em>}</em>", splitting the token
        // across a tag boundary. Tolerate a stray tag between any two delimiter
        // characters ("{{", between the closing braces, or before the key) so a
        // partially-formatted token still gets recognised.
        //
        // The one delimiter pair that needs care is the final "}}": a tag opened
        // there (like the <em> above) closes *after* the match, which would also
        // wrongly swallow a legitimate closing tag that wraps the *whole* token
        // (e.g. "<strong>{{place1}}</strong>" - confirmed working and must stay
        // that way). \2 requires that trailing close to actually match the name of
        // a tag opened between the closing braces, so an unrelated external closer
        // is left alone.
        $tag = '(?:<[^>]*>)*';
        $char = '(?:[^{}<>\s]' . $tag . ')';
        $interiorOpen = '(?:<(\w+)[^>]*>)?';
        $pattern = '/\{' . $tag . '\{' . $tag . '\s*(' . $char . '+)\s*' . $tag . '\}' . $interiorOpen . '\}(?:<\/\2>)?/';

        return preg_replace_callback(
            $pattern,
            function (array $match) use ($placeholders) {
                $key = strip_tags($match[1]);

                return array_key_exists($key, $placeholders)
                    ? (string) $placeholders[$key]
                    : $match[0];
            },
            $text
        );
    }
}
