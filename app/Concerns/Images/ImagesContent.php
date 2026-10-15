<?php

namespace App\Concerns\Images;

use App\Concerns\BuildsWithFallbacks;
use App\NutsAndBolts\PictureMenu;
use App\NutsAndBolts\PictureViewer;
use Surface\Contracts\Windows\Primitives\TableColumn;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKCheckbox;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\Primitives\TKSlider;
use Surface\Contracts\Windows\Primitives\TKTable;
use Surface\Contracts\Windows\Primitives\TKTextArea;
use Surface\Contracts\Windows\Primitives\TKTextInput;
use Surface\Contracts\Windows\Primitives\TKToggle;
use Surface\Contracts\Windows\Primitives\TKToggleButton;
use Surface\Contracts\Windows\Primitives\TKVideo;

trait ImagesContent
{
    use BuildsWithFallbacks;

    protected ?TKTextInput $query = null;

    protected ?TKDropdown $media_type = null;

    protected ?TKButton $search = null;

    protected ?TKTable $results = null;

    protected ?PictureViewer $picture = null;

    protected ?TKVideo $video = null;

    protected ?TKLabel $link = null;

    protected ?TKRow $controls = null;

    protected ?TKToggleButton $play = null;

    protected ?TKSlider $seek = null;

    protected ?TKCheckbox $mute = null;

    protected TKToggle|TKCheckbox|null $loop = null;

    protected ?TKTextArea $description = null;

    /** Right-click actions for the shown picture. */
    protected ?PictureMenu $picture_menu = null;

    protected function setMainComponents(TKColumn $root): void
    {
        $form = $root->row('form', 10);
        ['query' => $query, 'media' => $media] = $this->lastSearch();
        $this->query = $form->textInput('query', $query, 'Search NASA images, video and audio');
        $this->query->minSize(260, 0);
        $this->media_type = $form->dropdown('media', array_keys(self::MEDIA));
        $this->media_type->select($media);
        $this->search = $form->button('search', 'Search');

        $body = $root->row('body', 12);
        $body->fill();
        $this->results = $body->table('results', [
            new TableColumn('date', 'Date'),
            new TableColumn('center', 'Center'),
            new TableColumn('title', 'Title'),
        ]);
        $this->results->fill(false, true)->minSize(420, 0);

        $this->setViewer($body);

        $this->description = $root->textArea('description');
        $this->description->minSize(0, 110);
    }

    /**
     * The stage shows one of a picture, a video, or a line of text (a link, or why there is
     * nothing to show); the controls row appears with a video.
     */
    private function setViewer(TKRow $body): void
    {
        $viewer = $body->column('viewer', 8);
        $viewer->fill();
        $stage = $viewer->column('stage');
        $stage->fill()->minSize(360, 240);
        // Zoomable; zooming past the cut shown asks for a bigger one.
        $this->picture = new PictureViewer($stage, 'picture', fn (string $file) => $this->sharpenPicture($file));
        $this->video = $this->videoIn($stage, 'video');
        $this->video?->fill();
        $this->video?->hide();
        $this->link = $stage->label('link', 'Pick a result.');
        $this->link->setWrap(true);

        $this->controls = $viewer->row('controls', 8);
        $this->play = $this->controls->toggleButton('play', 'Play');
        $this->seek = $this->controls->slider('seek', 0, 1, 0);
        $this->seek->fill(true, false);
        $this->mute = $this->controls->checkbox('mute', 'Mute');
        $this->loop = $this->switchIn($this->controls, 'loop', 'Loop');
        $this->controls->hide();

        $this->picture_menu = new PictureMenu('images');
    }
}
