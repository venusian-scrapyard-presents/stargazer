#!/usr/bin/env bash
# Builds one Stargazer release package with venusian build, into build/.
# Usage: .github/build-release.sh <macos-arm64|linux-arm64|linux-x86_64> <version> <build number> [gtk|qt]
set -euo pipefail

target="$1"
version="$2"
build_number="$3"
toolkit="${4:-gtk}"
platform="${target%%-*}"
case "$platform:$toolkit" in
    macos:*|linux:gtk|linux:qt) ;;
    *)
        echo "Unknown toolkit '$toolkit' for $target; use gtk or qt." >&2
        exit 1
        ;;
esac

# composer.json carries AppKit for macOS; composer.linux.json is the same manifest with GTK, and
# composer.linux-qt.json with Qt. Each has a committed lock, so a release ships exactly the
# package set that was tested.
macos_manifest="$(jq -S 'del(.require["jovian/venusian-appkit"])' composer.json)"
for pair in composer.linux.json:jovian/venusian-gtk composer.linux-qt.json:jovian/venusian-qt; do
    other="${pair%%:*}"
    other_manifest="$(jq -S --arg package "${pair#*:}" 'del(.require[$package])' "$other")"
    if [ "$macos_manifest" != "$other_manifest" ]; then
        echo "composer.json and $other differ beyond the toolkit package; make the same change to all three and re-lock:" >&2
        diff <(echo "$macos_manifest") <(echo "$other_manifest") >&2 || true
        exit 1
    fi
done

if [ "$platform" = linux ] && [ "$toolkit" = qt ]; then
    manifest=composer.linux-qt.json
    lock=composer.linux-qt.lock
    build_manifest=linux-qt.build.json
elif [ "$platform" = linux ]; then
    manifest=composer.linux.json
    lock=composer.linux.lock
    build_manifest=linux.build.json
else
    manifest=composer.json
    lock=composer.lock
    build_manifest=macos.build.json
fi
if [ ! -f "$lock" ]; then
    echo "$lock is missing; resolve and commit it before tagging a release." >&2
    exit 1
fi
if [ "$manifest" != composer.json ]; then
    cp "$manifest" composer.json
    cp "$lock" composer.lock
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
if [ "$platform" = linux ] && [ "$toolkit" = qt ]; then
    # The Qt package installs beside the GTK one, so it carries its own app id; app.id must match build.json's.
    sed -e 's/^APP_ID=.*/APP_ID=com.venusian.stargazer.qt/' -e 's/^TOOLKIT_BRIDGE=.*/TOOLKIT_BRIDGE=qt/' .env > .env.qt
    mv .env.qt .env
fi
jq --arg version "$version" --argjson build "$build_number" --arg target "$target" \
    '.version = $version | .build = $build | .targets = [$target]' \
    "$build_manifest" > build.json

composer install --no-interaction --no-progress
composer run-script post-create-project-cmd --no-interaction

venusian build --no-interaction
