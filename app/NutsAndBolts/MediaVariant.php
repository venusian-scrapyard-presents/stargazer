<?php

namespace App\NutsAndBolts;

use ProjectSaturnStudios\Stargazer\ImageLibrary\Enums\ImageMediaType;

/**
 * Which of an Image Library asset's files to show: the smallest cut that still looks right,
 * before the original (a video's original can run to gigabytes).
 */
final class MediaVariant
{
    /** Preferred cuts, best first, as the "~<cut>" before the extension. */
    protected const array CUTS = [
        'video' => ['mobile', 'small', 'preview', 'medium', 'large', 'orig'],
        'image' => ['medium', 'small', 'large', 'orig', 'thumb'],
        'audio' => ['128k', '64k', 'orig'],
    ];

    protected const array EXTENSIONS = [
        'video' => ['mp4', 'm4v', 'mov'],
        'image' => ['jpg', 'jpeg', 'png', 'gif'],
        'audio' => ['mp3', 'm4a', 'wav'],
    ];

    /**
     * @param list<string> $hrefs The asset manifest's files.
     * @param ImageMediaType $type
     * @return string|null null when no file is that kind of media.
     */
    public static function pick(array $hrefs, ImageMediaType $type): ?string
    {
        $extensions = self::EXTENSIONS[$type->value];
        $media = array_values(array_filter(
            $hrefs,
            fn (string $href): bool => in_array(strtolower(pathinfo((string) parse_url($href, PHP_URL_PATH), PATHINFO_EXTENSION)), $extensions, true),
        ));

        foreach (self::CUTS[$type->value] as $cut) {
            foreach ($media as $href) {
                if (preg_match('/~'.preg_quote($cut, '/').'\.[a-z0-9]+$/i', (string) parse_url($href, PHP_URL_PATH))) {
                    return $href;
                }
            }
        }

        return $media[0] ?? null;
    }

    /** Image cuts by size, smallest first. */
    protected const array IMAGE_SIZES = ['thumb', 'small', 'medium', 'large', 'orig'];

    /**
     * The image files bigger than $shown, biggest first: what to try when a zoom wants more pixels.
     * @param list<string> $hrefs The asset manifest's files.
     * @param string $shown The file shown now.
     * @return list<string>
     */
    public static function sharper(array $hrefs, string $shown): array
    {
        $rank = fn (string $href): ?int => preg_match('/~([a-z0-9]+)\.(jpe?g|png|gif)$/i', (string) parse_url($href, PHP_URL_PATH), $match)
            ? (array_search(strtolower($match[1]), self::IMAGE_SIZES, true) ?: (strtolower($match[1]) === 'thumb' ? 0 : null))
            : null;
        $from = $rank($shown) ?? -1;

        $bigger = array_values(array_filter($hrefs, fn (string $href): bool => $href !== $shown && ($rank($href) ?? -1) > $from));
        usort($bigger, fn (string $a, string $b): int => $rank($b) <=> $rank($a));

        return $bigger;
    }
}
