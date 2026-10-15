<?php

namespace App\Concerns\GIBS;

use DateTimeImmutable;
use DateTimeZone;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKCanvas;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDatepicker;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKLabel;

trait GIBSContent
{
    protected ?TKDropdown $projection = null;

    protected ?TKDropdown $layer = null;

    protected ?TKDatepicker $date = null;

    protected ?TKDropdown $overlay = null;

    /** @var array<string, TKButton> Navigation buttons by what they do. */
    protected array $buttons = [];

    protected ?TKCanvas $canvas = null;

    protected ?TKLabel $caption = null;

    protected function setMainComponents(TKColumn $root): void
    {
        $form = $root->row('form', 10);
        $this->projection = $form->dropdown('projection', array_keys(self::PROJECTIONS));
        $this->layer = $form->dropdown('layer', []);
        $this->layer->minSize(260, 0);
        $this->date = $form->datepicker('date', new DateTimeImmutable('yesterday', new DateTimeZone('UTC')));
        $this->overlay = $form->dropdown('overlay', ['No overlay']);

        $nav = $root->row('nav', 6);
        foreach (['zoom-in' => 'Zoom in', 'zoom-out' => 'Zoom out', 'west' => '◀ West', 'east' => 'East ▶', 'north' => '▲ North', 'south' => '▼ South', 'whole' => 'Whole map'] as $key => $label) {
            $this->buttons[$key] = $nav->button($key, $label);
        }

        $this->canvas = $root->canvas('map');
        $this->canvas->fill();
        $this->canvas->minSize(480, 300);

        $this->caption = $root->label('caption', '');
        $this->caption->fill(true, false);
        $this->caption->setWrap(true);
    }
}
