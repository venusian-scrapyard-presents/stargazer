<?php

namespace App\Runner\Controllers;

use App\GlobalVariables;
use App\Services\Images\ImagesService;
use App\Workflows\Images\OpenImageItem\OpenImageItemStart;
use App\Workflows\Images\SearchImages\SearchImagesStart;
use Surface\Contracts\Windows\Mail\MenuActivated;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\RowSelected;
use Surface\Contracts\Windows\Mail\View\TextSubmitted;
use Surface\Contracts\Windows\Mail\View\Toggled;
use Surface\Contracts\Windows\Mail\View\ValueChanged;
use Surface\Contracts\Windows\Mail\View\VideoEnded;
use Surface\Contracts\Windows\Mail\View\VideoFailed;
use Surface\Contracts\Windows\Mail\View\VideoPaused;
use Surface\Contracts\Windows\Mail\View\VideoPlaying;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\Signals\NamedSignal;
use Voyager\Workflows\Flow;

class ImagesController extends StargazerController
{
    public function __construct(
        ToolkitWindow $window,
        public readonly ImagesService $service
    )
    {
        parent::__construct($window);
    }

    public function title(): string
    {
        return 'Image Library';
    }

    public function key(): string
    {
        return 'images';
    }

    public function menu(): string
    {
        return 'main';
    }

    public function refresh(): void
    {
        $start_node = new SearchImagesStart($this->service);
        new Flow($start_node)->run(GlobalVariables::make());
    }

    public function index(TKColumn $root): void
    {
        $this->service->mounted($root);
    }

    public function deliver(NamedSignal $mail): bool
    {
        $service = $this->service;

        // The shown picture zooms on its own clicks and buttons.
        if ($service->viewer()->handles($mail)) {
            return true;
        }

        return match (true) {
            $mail instanceof TextSubmitted && $service->isQuery($mail->uuid()),
            $mail instanceof ButtonClicked && $service->isSearch($mail->uuid()) => $this->reload(),
            $mail instanceof RowSelected && $service->isResults($mail->uuid()) => $this->open($mail->row),
            $mail instanceof MenuActivated => $service->menuActivated($mail),
            $mail instanceof Toggled && $service->isPlay($mail->uuid()) => $service->togglePlay($mail->on),
            $mail instanceof Toggled && $service->isMute($mail->uuid()) => $service->setMuted($mail->on),
            $mail instanceof Toggled && $service->isLoop($mail->uuid()) => $service->setLoop($mail->on),
            $mail instanceof ValueChanged && $service->isSeek($mail->uuid()) => $service->seek($mail->value),
            $mail instanceof VideoPlaying && $service->isVideo($mail->uuid()) => $service->playing(true),
            ($mail instanceof VideoPaused || $mail instanceof VideoEnded) && $service->isVideo($mail->uuid()) => $service->playing(false),
            $mail instanceof VideoFailed && $service->isVideo($mail->uuid()) => $service->videoFailed($mail->reason),
            default => false,
        };
    }

    /**
     * Every frame: a playing video moves the seek bar.
     */
    public function tick(): void
    {
        $this->service->tick();
        $this->service->viewer()->tick();
    }

    protected function reload(): bool
    {
        $this->refresh();

        return true;
    }

    /**
     * A table row picked: open its item. A cleared selection opens nothing.
     */
    protected function open(?int $row): bool
    {
        $item = $this->service->item($row);

        if (! is_null($item)) {
            $start_node = new OpenImageItemStart($this->service, $item);
            new Flow($start_node)->run(GlobalVariables::make());
        }

        return true;
    }
}
