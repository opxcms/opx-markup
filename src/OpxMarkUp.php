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
                $new .= '<h6' . self::class($class, 'title') . '>' . mb_substr($line, 7) . "</h6>\r\n";
            } elseif (strpos($line, '##### ') === 0) {
                $new .= '<h5' . self::class($class, 'title') . '>' . mb_substr($line, 6) . "</h5>\r\n";
            } elseif (strpos($line, '#### ') === 0) {
                $new .= '<h4' . self::class($class, 'title') . '>' . mb_substr($line, 5) . "</h4>\r\n";
            } elseif (strpos($line, '### ') === 0) {
                $new .= '<h3' . self::class($class, 'title') . '>' . mb_substr($line, 4) . "</h3>\r\n";
            } elseif (strpos($line, '## ') === 0) {
                $new .= '<h2' . self::class($class, 'title') . '>' . mb_substr($line, 3) . "</h2>\r\n";
            } elseif (strpos($line, '# ') === 0) {
                $new .= '<h1' . self::class($class, 'title') . '>' . mb_substr($line, 2) . "</h1>\r\n";
            } elseif (strpos($line, '* ') === 0) {
                if (!$ul_opened) {
                    $new .= '<ul' . self::class($class, 'list') . '>' . "\r\n";
                    $ul_opened = true;
                }
                $li = mb_substr($line, 2);
//                $split = explode(':', $li, 2);
//                if (count($split) === 2) {
//                    if (strpos($split[1], '|') !== false) {
//                        $split[1] = implode(', ', explode('|', $split[1]));
//                    }
//                    if (strpos($split[1], '^') !== false) {
//                        $split[1] = str_replace('^', ' x ', $split[1]);
//                    }
//                    $li = '<span' . self::class($class, 'list-item-key') . ">{$split[0]}:</span>";
//                    $li .= ' ';
//                    $li .= '<span' . self::class($class, 'list-item-value') . ">{$split[1]}</span>";
//                }
                $new .= '<li' . self::class($class, 'list-item') . '>' . $li . "</li>\r\n";
            } elseif (strpos($line, '|') === 0) {
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
            } elseif (strpos($line, '---') === 0) {
                $new .= '<hr>';
            } elseif (strpos($line, '/') === 0) {
                $new .= mb_substr($line, 1);
            } else {
                if (strpos($line, '\\') === 0) {
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
        $new = self::replaceImages($new, $class);
        $new = self::replaceLinks($new, $class);

        return $new;
    }

    /**
     * Make class declaration.
     *
     * @param string|null $class
     * @param string|null $suffix
     *
     * @return  string|null
     */
    protected static function class(?string $class = null, ?string $suffix = null): ?string
    {
        if ($class === null) {
            return null;
        }

        if ($suffix !== null) {
            $suffix = (strpos($class, '__') !== false ? '-' : '__') . $suffix;
        }

        return " class=\"{$class}{$suffix}\"";
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
            $align = $options[2] !== null ? strtolower($options[2]) : null;

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
                if ($align !== null && ($align === 'left' || $align === 'right' || $align === 'center')) {
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
