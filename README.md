# Stargazer

A desktop app for exploring NASA's open APIs: the Astronomy Picture of the Day, live Earth imagery, space weather, near-Earth objects and more, in native windows on macOS and Linux.

Stargazer is written in PHP on the [Venusian](https://venusian.projectsaturnstudios.com/docs) framework. It draws with AppKit on macOS and GTK 4 on Linux, and ships as a single installable package with its own PHP runtime inside.

## What's inside

| Page | NASA source | What you get |
| --- | --- | --- |
| Picture of the Day | APOD | Browse any day's picture or video in a gallery, open it in HD, zoom in, and right-click to download it or open it in its own window |
| Earth from DSCOVR | EPIC | Full-disc photos of Earth from the DSCOVR satellite, by day |
| Earth, live (GIBS) | Global Imagery Browse Services | A pannable, zoomable map of recent satellite imagery |
| Natural Events | EONET | Wildfires, storms, volcanoes and other events drawn over the Blue Marble, coloured by category |
| Space Weather | DONKI | Flares, coronal mass ejections and storms on a timeline of linked events |
| Near-Earth Objects | NeoWs | Asteroids passing Earth, with details for any one you pick |
| InSight | InSight Mars weather | Weather reports from the InSight lander on Mars |
| Satellite Orbits | TLE | Two-line element sets for Earth-orbiting satellites |
| Image Library | NASA Image and Video Library | Search NASA's photo and video archive and play results in the app |
| Tech Transfer | Technology Transfer | NASA patents, software and spinoffs |

Settings holds your API key and the hourly request quota NASA reports back, the page Stargazer opens on, and the cache store (file, or a Redis server you point it at).

## Install

Download the package for your machine from the [Releases page](https://github.com/venusian-scrapyard-presents/stargazer/releases):

- **macOS on Apple Silicon (macOS 14 or newer):** open the `.dmg` and drag Stargazer into Applications.
- **Linux on arm64 or x86_64 (Ubuntu 24.04 or newer, Debian 13, Raspberry Pi OS trixie):** install the `.deb` with apt, which pulls in GTK 4 and the other system libraries it needs:

  ```bash
  sudo apt install ./stargazer_*.deb
  ```

On first launch Stargazer asks for a NASA API key. Sign up for a free one at [api.nasa.gov](https://api.nasa.gov/); the page shows the key as soon as you submit the form. NASA's shared `DEMO_KEY` also works for a quick look, but it allows only 30 requests an hour.

## Run from source

You need PHP 8.4 or newer, Composer, and the PHP extensions listed under `require` in [composer.json](composer.json). Venusian's own extensions (`fb`, `imgdec`, `rasterize`, `pcurl`, and `appkit` or `gtk`) install with the [Venusian installer](https://github.com/VenusianPHP/installer):

```bash
venusian install:ext
```

`redis` comes from [phpredis](https://github.com/phpredis/phpredis). Stargazer falls back to file caching when no Redis server answers, but the extension itself must be loaded.

**macOS:**

```bash
git clone https://github.com/venusian-scrapyard-presents/stargazer.git
cd stargazer
cp macos.env.example .env
composer run setup
php computer migrate
php rocket stargazer
```

**Linux:** the checked-in `composer.json` targets macOS and requires the AppKit bridge, so swap it for the GTK bridge before installing:

```bash
git clone https://github.com/venusian-scrapyard-presents/stargazer.git
cd stargazer
cp linux.env.example .env
composer remove jovian/venusian-appkit --no-update
composer require jovian/venusian-gtk --no-update
composer run setup
php computer migrate
php rocket stargazer
```

`composer run setup` installs the dependencies, generates `APP_KEY` in your `.env`, and creates the SQLite database that `php computer migrate` fills.

## Build a package

Stargazer packages itself with [venusian/build](https://github.com/VenusianPHP/build). Copy the build manifest for your target over `build.json`, then build:

```bash
cp macos.build.json build.json   # or linux.build.json
venusian build
```

The macOS manifest produces a `.dmg` for `macos-arm64`. The Linux manifest produces a `.deb` each for `linux-arm64` and `linux-x86_64`, and needs Docker on a machine with each target's CPU. The [venusian/build README](https://github.com/VenusianPHP/build#readme) covers requirements, signing, and every manifest key.

## Tests

```bash
composer test
```

## License

Stargazer is free software: you can redistribute it and modify it under the terms of the [GNU General Public License v3.0](LICENSE). Anyone who distributes Stargazer, modified or not, must give recipients the complete source under the same license.

[LICENSE-EXCEPTION](LICENSE-EXCEPTION) adds a permission under GPL section 7 that lets Stargazer be shipped compiled together with the PHP runtime and its extensions, as the release packages are.

Stargazer uses NASA's public APIs and imagery. It is not affiliated with or endorsed by NASA.

Made by Project Saturn Studios, LLC.
