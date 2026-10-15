#!/usr/bin/env bash
# Builds one Stargazer release package with venusian build, into build/.
# Usage: .github/build-release.sh <macos-arm64|linux-arm64|linux-x86_64> <version> <build number>
set -euo pipefail

target="$1"
version="$2"
build_number="$3"
platform="${target%%-*}"

# The PHP running the build only packs the phar and migrates its seed database; the app's
# extensions compile into the packaged runtime, so composer must not demand them from this PHP.
export COMPOSER_IGNORE_PLATFORM_REQS=1

cp "${platform}.env.example" .env
jq --arg version "$version" --argjson build "$build_number" --arg target "$target" \
    '.version = $version | .build = $build | .targets = [$target]' \
    "${platform}.build.json" > build.json

if [ "$platform" = linux ]; then
    # composer.json carries the macOS toolkit; a Linux package ships GTK.
    composer remove jovian/venusian-appkit --no-update --no-interaction
    composer require jovian/venusian-gtk:^0.10.1 --no-update --no-interaction
fi

composer install --no-interaction --no-progress
composer run-script post-create-project-cmd --no-interaction

venusian build --no-interaction
