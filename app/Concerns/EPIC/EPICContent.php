<?php

namespace App\Concerns\EPIC;

use App\NutsAndBolts\FramePlayer;
use App\NutsAndBolts\PictureMenu;
use App\NutsAndBolts\PictureViewer;
use DateTimeImmutable;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDatepicker;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKProgressBar;

trait EPICContent
{
    protected ?TKDropdown $collection = null;

    protected ?TKDatepicker $date = null;

    protected ?TKButton $load = null;

    protected ?TKProgressBar $progress = null;

    protected ?PictureViewer $frame = null;

    protected ?PictureMenu $frame_menu = null;

    protected ?FramePlayer $player = null;

    protected ?TKButton $previous = null;

    protected ?TKButton $play = null;

    protected ?TKButton $next = null;

    protected ?TKLabel $position = null;

    protected ?TKDropdown $speed = null;

    protected ?TKLabel $caption = null;

    protected function setMainComponents(TKColumn $root): void
    {
        $form = $root->row('form', 10);
        $this->collection = $form->dropdown('collection', array_keys(self::COLLECTIONS));
        $this->date = $form->datepicker('date', new DateTimeImmutable('today'));
        $this->load = $form->button('load', 'Load day');
        $this->progress = $form->progressBar('progress', 0.0);
        $this->progress->minSize(200, 0);
        $this->progress->hide();

        // Zoomable; a zoom holds while the player turns the Earth.
        $this->frame = new PictureViewer($root, 'frame');
        $this->frame_menu = new PictureMenu('epic');

        $this->player = new FramePlayer();
        $controls = $root->row('player', 10);
        $this->previous = $controls->button('previous', '◀ Prev');
        $this->play = $controls->button('play', '▶ Play');
        $this->next = $controls->button('next', 'Next ▶');
        $this->position = $controls->label('position', '');
        $this->speed = $controls->dropdown('speed', array_keys(FramePlayer::SPEEDS));
        $this->speed->select(FramePlayer::defaultSpeed());
        $this->speed->minSize(150, 0);
        $this->setPlayerEnabled(false);
        $this->caption = $root->label('caption', '');
        $this->caption->setWrap(true);
    }

    /** The player's buttons work only when there is a frame to step to. */
    protected function setPlayerEnabled(bool $enabled): void
    {
        $this->previous->setEnabled($enabled);
        $this->play->setEnabled($enabled);
        $this->next->setEnabled($enabled);
    }
}
