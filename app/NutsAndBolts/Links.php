<?php

namespace App\NutsAndBolts;

/**
 * The studio's web addresses, tagged so traffic-watch sees which screen of the app the visit
 * came from.
 */
class Links
{
    public const string STUDIO = 'https://projectsaturnstudios.com/';

    public const string SITE = 'https://projectsaturnstudios.com/stargazer';

    /** The studio's home page. */
    public static function studio(string $campaign, string $content): string
    {
        return self::tag(self::STUDIO, $campaign, $content);
    }

    /** Stargazer's own page on the studio site. */
    public static function site(string $campaign, string $content): string
    {
        return self::tag(self::SITE, $campaign, $content);
    }

    /** The credit line shown on the welcome screen and in the About panel. */
    public static function credit(int $year): string
    {
        return "Made with ADHD by Project Saturn Studios © {$year}";
    }

    /**
     * @param string $campaign the screen the link sits on
     * @param string $content which link on that screen
     */
    protected static function tag(string $url, string $campaign, string $content): string
    {
        return $url.'?'.http_build_query([
            'utm_source' => 'stargazer',
            'utm_medium' => 'app',
            'utm_campaign' => $campaign,
            'utm_content' => $content,
        ]);
    }
}
