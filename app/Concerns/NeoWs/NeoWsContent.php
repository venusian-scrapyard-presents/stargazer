<?php

namespace App\Concerns\NeoWs;

use App\Concerns\BuildsWithFallbacks;
use DateTimeImmutable;
use Surface\Contracts\Windows\Primitives\TableColumn;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKCheckbox;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDatepicker;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKSlider;
use Surface\Contracts\Windows\Primitives\TKTable;
use Surface\Contracts\Windows\Primitives\TKTextArea;
use Surface\Contracts\Windows\Primitives\TKToggle;

trait NeoWsContent
{
    use BuildsWithFallbacks;

    protected ?TKDatepicker $start = null;

    protected ?TKSlider $days = null;

    protected ?TKLabel $days_label = null;

    protected TKToggle|TKCheckbox|null $hazardous = null;

    protected ?TKButton $load = null;

    protected ?TKTable $objects = null;

    protected ?TKTextArea $orbit = null;

    protected function setMainComponents(TKColumn $root): void
    {
        $form = $root->row('form', 10);
        $form->label('start-label', 'From');
        $this->start = $form->datepicker('start', new DateTimeImmutable('today'));
        $this->days = $form->slider('days', 1, 28, 7);
        $this->days->minSize(180, 0);
        $this->days_label = $form->label('days-label', '7 days');
        $this->hazardous = $this->switchIn($form, 'hazardous', 'Hazardous only');
        $this->load = $form->button('load', 'Load');

        $this->objects = $root->table('objects', [
            new TableColumn('date', 'Closest'),
            new TableColumn('diameter', 'Diameter (m)'),
            new TableColumn('miss', 'Miss (km)'),
            new TableColumn('speed', 'Speed (km/s)'),
            new TableColumn('hazard', 'Hazard'),
            new TableColumn('name', 'Name'),
        ]);
        $this->objects->fill()->minSize(0, 260);

        $this->orbit = $root->textArea('orbit');
        $this->orbit->setFont(self::monospace(12))->minSize(0, 130);
    }
}
