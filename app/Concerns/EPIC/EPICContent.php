<?php

namespace App\Concerns\EPIC;

use App\NutsAndBolts\PictureViewer;
use DateTimeImmutable;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDatepicker;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKProgressBar;
use Surface\Contracts\Windows\Primitives\TKSlider;

trait EPICContent
{
    protected ?TKDropdown $collection = null;

    protected ?TKDatepicker $date = null;

    protected ?TKButton $load = null;

    protected ?TKProgressBar $progress = null;

    protected ?PictureViewer $frame = null;

    protected ?TKSlider $scrub = null;

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

        // Zoomable; a zoom holds while the slider turns the Earth.
        $this->frame = new PictureViewer($root, 'frame');

        $this->scrub = $root->slider('scrub', 0, 1, 0);
        $this->scrub->disable();
        $this->caption = $root->label('caption', '');
        $this->caption->setWrap(true);
    }
}
