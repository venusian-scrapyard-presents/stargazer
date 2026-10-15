<?php

namespace App\Workflows\Images\OpenImageItem;

use App\GlobalVariables;
use App\NutsAndBolts\MediaVariant;
use App\Services\Images\ImagesService;
use App\Workflows\Fetch;
use App\Workflows\MediaDownload;
use ProjectSaturnStudios\Stargazer\ImageLibrary\DataObjects\ImageAssetFile;
use ProjectSaturnStudios\Stargazer\ImageLibrary\DataObjects\ImageAssetManifest;
use ProjectSaturnStudios\Stargazer\ImageLibrary\DataObjects\ImageItemData;
use ProjectSaturnStudios\Stargazer\ImageLibrary\Enums\ImageMediaType;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * A picked result: its asset manifest decides what the viewer shows. A picture is downloaded
 * and shown; a video is downloaded and played where the stage has a video view, else linked;
 * audio is linked. The manifest is read from image_items when the item was opened before.
 */
class OpenImageItemStart extends Node
{
    public function __construct(
        protected ImagesService $images,
        protected ImageItemData $item,
    ){
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        return $shared;
    }

    public function exec(mixed $prepRes): mixed
    {
        /** @var GlobalVariables $bag */
        $bag = $prepRes;
        $nasa = $bag->nasa;
        $images = $this->images;
        $item = $this->item;
        $name = $item->title ?? $item->nasaId;

        $images->describe($item);

        // The manifest comes from image_items when the item is stored; fetched, it is stored for next time.
        $recall = new RecallImageItem($item->nasaId);
        $fetch = new Fetch(fn () => $nasa->imageLibrary()->asset($item->nasaId), 'manifest');
        $images->failures($fetch);
        $recall->on('fetch')->next($fetch);

        // The item's media type picks the branch; the manifest's files are on the bag as 'hrefs'.
        $route = $images->render(fn (GlobalVariables $bag): string => ($item->mediaType ?? ImageMediaType::IMAGE)->value);
        $recall->on('stored')->next($route);
        $fetch->next($images->render(function (GlobalVariables $bag): ?string {
            /** @var ImageAssetManifest $manifest */
            $manifest = $bag->manifest;
            $bag->hrefs = $manifest->items->map(fn (ImageAssetFile $file): string => $file->href)->values()->all();

            return null;
        }))->next(new ArchiveImageItem($item))->next($route);

        $picture = new MediaDownload($images->media, fn (SharedBag $bag) => $images->source($bag, ImageMediaType::IMAGE), 'file');
        $images->failures($picture);
        $route->on('image')->next($picture)->next($images->render(function (GlobalVariables $bag) use ($images, $name): ?string {
            is_null($bag->file) ? $images->showLink('This item has no viewable image.') : $images->showPicture($bag->file, $bag->hrefs);
            $images->done($bag, $name);

            return null;
        }));

        // Without a video view nothing is downloaded: the link is shown instead.
        $video = new MediaDownload($images->media, fn (SharedBag $bag) => $images->hasVideoView() ? $images->source($bag, ImageMediaType::VIDEO) : null, 'file');
        $images->failures($video);
        $route->on('video')->next($video)->next($images->render(function (GlobalVariables $bag) use ($images, $name): ?string {
            $href = MediaVariant::pick($bag->hrefs, ImageMediaType::VIDEO);
            match (true) {
                is_null($href) => $images->showLink('This item has no playable video.'),
                ! $images->hasVideoView() => $images->showLink("Video plays here where the toolkit has a media runtime. Watch: {$href}"),
                default => $images->showVideo($bag->file),
            };
            $images->done($bag, "{$name} (video)");

            return null;
        }));

        $route->on('audio')->next($images->render(function (GlobalVariables $bag) use ($images, $name): ?string {
            $href = MediaVariant::pick($bag->hrefs, ImageMediaType::AUDIO);
            $images->showLink(is_null($href) ? 'This item has no audio file.' : "Listen: {$href}");
            $images->done($bag, "{$name} (audio)");

            return null;
        }));

        $bag->flow->start(
            new AsyncFlow($recall),
            $images,
            "Opening {$name}…"
        );

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
