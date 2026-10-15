<?php

namespace App\Concerns\EONET;

use Surface\Contracts\Windows\Primitives\TableColumn;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKCanvas;
use Surface\Contracts\Windows\Primitives\TKCheckbox;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\Primitives\TKTable;

trait EONETContent
{
    protected ?TKDropdown $category = null;

    protected ?TKCheckbox $open = null;

    protected ?TKButton $load = null;

    protected ?TKCanvas $map = null;

    /** A coloured dot and name per category on the map. */
    protected ?TKRow $legend = null;

    protected ?TKTable $events_table = null;

    protected ?TKLabel $detail = null;

    protected function setMainComponents(TKColumn $root): void
    {
        $form = $root->row('form', 10);
        $this->category = $form->dropdown('category', ['All categories']);
        $this->open = $form->checkbox('open', 'Open events only', true);
        $this->load = $form->button('load', 'Load');

        $body = $root->row('body', 12);
        $body->fill();

        $this->map = $body->canvas('map');
        $this->map->minSize(...self::MAP_FLOOR)->fill();

        $this->events_table = $body->table('events', [
            new TableColumn('date', 'Date'),
            new TableColumn('category', 'Category'),
            new TableColumn('title', 'Event'),
        ]);
        // The table keeps its floor; the map takes the rest of the row.
        $this->events_table->fill(false, true)->minSize(380, 0);

        $this->legend = $root->row('legend', 14);
        $this->detail = $root->label('detail', 'Click a dot or a row.');
        $this->detail->setWrap(true);
    }
}
