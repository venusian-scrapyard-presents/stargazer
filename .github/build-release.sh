#!/usr/bin/env bash
# Builds one Stargazer release package with venusian build, into build/.
# Usage: .github/build-release.sh <macos-arm64|linux-arm64|linux-x86_64> <version> <build number>
set -euo pipefail

target="$1"
version="$2"
build_number="$3"
platform="${target%%-*}"

# composer.json carries AppKit for macOS; composer.linux.json is the same manifest with GTK.
# Each has a committed lock, so a release ships exactly the package set that was tested.
macos_manifest="$(jq -S 'del(.require["jovian/venusian-appkit"])' composer.json)"
linux_manifest="$(jq -S 'del(.require["jovian/venusian-gtk"])' composer.linux.json)"
if [ "$macos_manifest" != "$linux_manifest" ]; then
    echo "composer.json and composer.linux.json differ beyond the toolkit package; make the same change to both and re-lock:" >&2
    diff <(echo "$macos_manifest") <(echo "$linux_manifest") >&2 || true
    exit 1
fi

if [ "$platform" = linux ]; then
    manifest=composer.linux.json
    lock=composer.linux.lock
else
    manifest=composer.json
    lock=composer.lock
fi
if [ ! -f "$lock" ]; then
    echo "$lock is missing; resolve and commit it before tagging a release." >&2
    exit 1
fi
if [ "$platform" = linux ]; then
    cp composer.linux.json composer.json
    cp composer.linux.lock composer.lock
fi
if ! validation="$(composer validate --no-check-publish --no-check-all --no-interaction 2>&1)"; then
    echo "$lock does not match $manifest; re-lock and commit it before tagging a release:" >&2
    echo "$validation" >&2
    exit 1
fi

# The PHP running the build only packs the phar and migrates its seed database; the app's
# extensions compile into the packaged runtime, so composer must not demand them from this PHP.
export COMPOSER_IGNORE_PLATFORM_REQS=1

cp "${platform}.env.example" .env
# The packaged .env ships to every user: only NASA's shared DEMO_KEY may ride in it.
if [ "$(sed -n 's/^NASA_API_KEY=//p' .env)" != DEMO_KEY ]; then
    echo "${platform}.env.example must set NASA_API_KEY=DEMO_KEY; a real key would ship inside the package." >&2
    exit 1
fi
jq --arg version "$version" --argjson build "$build_number" --arg target "$target" \
    '.version = $version | .build = $build | .targets = [$target]' \
    "${platform}.build.json" > build.json

composer install --no-interaction --no-progress
composer run-script post-create-project-cmd --no-interaction

venusian build --no-interaction
