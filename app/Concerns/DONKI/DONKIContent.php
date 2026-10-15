<?php

namespace App\Concerns\DONKI;

use App\NutsAndBolts\DonkiRows;
use DateTimeImmutable;
use Surface\Contracts\Windows\Primitives\TableColumn;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKCanvas;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDatepicker;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\Primitives\TKTable;
use Surface\Contracts\Windows\Primitives\TKTextArea;
use Surface\Contracts\Windows\Primitives\TKToggleButton;

trait DONKIContent
{
    protected ?TKDropdown $type = null;

    protected ?TKDatepicker $from = null;

    protected ?TKDatepicker $to = null;

    protected ?TKButton $load = null;

    protected ?TKToggleButton $list = null;

    protected ?TKCanvas $timeline_view = null;

    protected ?TKLabel $hint = null;

    protected ?TKRow $chain = null;

    protected ?TKTable $events_table = null;

    protected ?TKTextArea $detail = null;

    protected function setMainComponents(TKColumn $root): void
    {
        $today = new DateTimeImmutable('today');

        $form = $root->row('form', 10);
        $this->type = $form->dropdown('type', [self::ALL, ...array_keys(DonkiRows::TYPES)]);
        $form->label('from-label', 'From');
        $this->from = $form->datepicker('from', $today->modify('-30 days'));
        $form->label('to-label', 'to');
        $this->to = $form->datepicker('to', $today);
        $this->load = $form->button('load', 'Load');
        $this->list = $form->toggleButton('list', 'List');

        // The timeline: one lane per event type across the dates, links drawn between records.
        $this->timeline_view = $root->canvas('timeline');
        $this->timeline_view->fill(true, false)->minSize(480, 260);
        $this->hint = $root->label('hint', 'Click an event on the timeline, or pick a row in the list, to follow its chain.');
        $this->hint->setWrap(true);

        // The picked event's chain: a card per linked record, oldest first, rebuilt on each pick.
        $this->chain = $root->row('chain', 8);
        $this->chain->hide();

        $this->events_table = $root->table('events', [
            new TableColumn('type', 'Type'),
            new TableColumn('time', 'Time'),
            new TableColumn('detail', 'Detail'),
            new TableColumn('id', 'Event'),
        ]);
        $this->events_table->fill()->minSize(0, 160);
        $this->events_table->hide();

        $this->detail = $root->textArea('detail');
        $this->detail->setFont(self::monospace())->minSize(0, 120);
        $this->detail->fill();
    }
}
