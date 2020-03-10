<?php

namespace Modules\Opx\MarkUp;

use RuntimeException;

class OpxMarkUp
{
    /**
     * Parse content.
     *
     * @param string $content
     * @param string|null $class
     *
     * @return  null|string
     */
    public static function parse($content, $class = null): ?string
    {
        if (!$content) {
            return null;
        }

        $arr = preg_split("/\r\n|\n/", $content, -1, PREG_SPLIT_NO_EMPTY);
        $new = '';

        $ul_opened = false;
        $table_opened = false;
        $div_opened = 0;

        foreach ($arr as $key => $line) {
            $line = trim($line);

            // Check if previously opened list needs to be closed
            if ($ul_opened && strpos($line, '* ') !== 0) {
                $new .= "</ul>\r\n";
                $ul_opened = false;
            }

            // Check if previously opened table needs to be closed
            if ($table_opened && strpos($line, '|') !== 0) {
                $new .= "</table>\r\n";
                $new .= "</div>\r\n";
                $table_opened = false;
            }

            if (strpos($line, '###### ') === 0) {
                // Headings
                $new .= '<h6' . self::class($class, 'title') . '>' . mb_substr($line, 7) . "</h6>\r\n";
            } elseif (strpos($line, '##### ') === 0) {
                // Headings
                $new .= '<h5' . self::class($class, 'title') . '>' . mb_substr($line, 6) . "</h5>\r\n";
            } elseif (strpos($line, '#### ') === 0) {
                // Headings
                $new .= '<h4' . self::class($class, 'title') . '>' . mb_substr($line, 5) . "</h4>\r\n";
            } elseif (strpos($line, '### ') === 0) {
                // Headings
                $new .= '<h3' . self::class($class, 'title') . '>' . mb_substr($line, 4) . "</h3>\r\n";
            } elseif (strpos($line, '## ') === 0) {
                // Headings
                $new .= '<h2' . self::class($class, 'title') . '>' . mb_substr($line, 3) . "</h2>\r\n";
            } elseif (strpos($line, '# ') === 0) {
                // Headings
                $new .= '<h1' . self::class($class, 'title') . '>' . mb_substr($line, 2) . "</h1>\r\n";
            } elseif (strpos($line, '* ') === 0) {
                // List
                if (!$ul_opened) {
                    $new .= '<ul' . self::class($class, 'list') . '>' . "\r\n";
                    $ul_opened = true;
                }
                $li = mb_substr($line, 2);
                $new .= '<li' . self::class($class, 'list-item') . '>' . $li . "</li>\r\n";
            } elseif (strpos($line, '|') === 0) {
                // Table
                if (!$table_opened) {
                    $new .= '<div' . self::class($class, 'table-container') . '>' . "\r\n";
                    $new .= '<table' . self::class($class, 'table') . '>' . "\r\n";
                    $table_opened = true;
                }
                $new .= '<tr' . self::class($class, 'table-row') . '>' . "\r\n";
                $new .= preg_replace_callback('/\|([^|.]+)/', static function ($matches) use ($class) {
                    if (!empty($matches[1])) {
                        return '<td' . self::class($class, 'table-cell') . '>' . trim($matches[1]) . "</td>\r\n";
                    }

                    return null;
                }, $line);
                $new .= '</tr>' . "\r\n";
            } elseif (strpos($line, '[') === 0) {
                // open block
                $suffixes = explode(' ', trim(substr($line, 1)));
                $classes = null;
                if (!empty($suffixes)) {
                    foreach ($suffixes as $suffix) {
                        $classes .= ' ' . self::class($class, 'block-' . $suffix, true);
                    }
                }
                $new .= '<div' . self::class(null, $classes) . '>';
                $div_opened++;
            } elseif (strpos($line, ']') === 0) {
                // cloce block
                $new .= '</div>';
                $div_opened--;
            } elseif (strpos($line, '---') === 0) {
                // Page break
                $new .= '<hr' . self::class($class, 'break') . '>';
            } elseif (strpos($line, '//') === 0) {
                // Just commented string
                $new .= '<!-- ' . trim(substr($line, 2)) . '-->';
            } elseif (strpos($line, '/') === 0) {
                // Line as is
                $new .= mb_substr($line, 1);
            } else {
                // Paragraph
                if (strpos($line, '\\') === 0) {
                    // Force paragraph
                    $line = trim(mb_substr($line, 1));
                }
                $new .= '<p' . self::class($class, 'paragraph') . ">{$line}</p>\r\n";
            }
        }

        // At last close list if it still opened
        if ($ul_opened) {
            $new .= "</ul>\r\n";
        }
        // and table
        if ($table_opened) {
            $new .= "</table>\r\n";
            $new .= "</div>\r\n";
        }
        // Close all unclosed divs
        if ($div_opened > 0) {
            $new .= str_repeat("</div>\r\n", $div_opened);
        }
        $new = self::replaceImages($new, $class);
        $new = self::replaceLinks($new, $class);

        return $new;
    }

    /**
     * Make class declaration.
     *
     * @param string|null $class
     * @param string|null $suffix
     * @param bool $raw
     *
     * @return  string|null
     */
    protected static function class(?string $class = null, ?string $suffix = null, bool $raw = false): ?string
    {
        if ($class === null && $suffix === null) {
            return null;
        }

        $suffix = trim($suffix);

        if ($class === null) {
            return $raw === false ? " class=\"{$suffix}\"" : $suffix;
        }

        $class = trim($class);

        if ($suffix !== null) {
            $suffix = (strpos($class, '__') !== false ? '-' : '__') . $suffix;
        }

        return $raw === false ? " class=\"{$class}{$suffix}\"" : $class . $suffix;
    }

    /**
     * Replace images in text.
     *
     * @param string $text
     * @param string|null $class
     *
     * @return  string
     */
    protected static function replaceImages(string $text, ?string $class): string
    {
        return preg_replace_callback('/\[img::(.+)]/U', static function ($matches) use ($class) {
            $options = explode('::', $matches[1] ?? '');
            $src = $options[0];
            $alt = $options[1] ?? $options[0];
            $align = isset($options[2]) ? strtolower($options[2]) : null;

            $styling = '';

            if ($class === null) {
                if ($align !== null && ($align === 'left' || $align === 'right')) {
                    $styling = " style=\"float:{$align}\"";
                }
                if ($align !== null && $align === 'center') {
                    $styling = ' style="margin:0 auto"';
                }
            } else {
                $divider = strpos($class, '__') === false ? '__' : '-';
                $styling = " class=\"{$class}{$divider}img";
                if ($align !== null) {
                    $styling .= " {$class}{$divider}img-{$align}";
                }
                $styling .= '"';
            }

            return "<img{$styling} src=\"{$src}\" alt=\"{$alt}\">";
        }, $text);
    }

    /**
     * Replace images in text.
     *
     * @param string $text
     * @param string|null $class
     *
     * @return  string
     */
    protected static function replaceLinks(string $text, ?string $class): string
    {
        return preg_replace_callback('/\[(.*)]\((.*)\)/U', static function ($matches) use ($class) {
            if (!isset($matches[1], $matches[2])) {
                throw new RuntimeException("Wrong link format [{$matches[0]}]");
            }
            $caption = $matches[1];

            if (strpos($matches[2], '::') !== false) {
                $link = function_exists('route') ? route($matches[2], [], false) : $matches[2];
            } else {
                $link = $matches[2];
            }
            if ($class !== null) {
                $divider = strpos($class, '__') === false ? '__' : '-';
                $class = " class=\"{$class}{$divider}link\"";
            }
            return "<a{$class} href=\"{$link}\">{$caption}</a>";
        }, $text);
    }
}
