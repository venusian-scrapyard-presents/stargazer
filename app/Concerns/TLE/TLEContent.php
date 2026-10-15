<?php

namespace App\Concerns\TLE;

use Surface\Contracts\Windows\Primitives\TableColumn;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKTable;
use Surface\Contracts\Windows\Primitives\TKTextArea;
use Surface\Contracts\Windows\Primitives\TKTextInput;

trait TLEContent
{
    protected ?TKTextInput $query = null;

    protected ?TKButton $search = null;

    protected ?TKTable $satellites = null;

    protected ?TKTextArea $elements = null;

    protected function setMainComponents(TKColumn $root): void
    {
        $form = $root->row('form', 10);
        $this->query = $form->textInput('query', 'ISS', 'Satellite name, e.g. ISS');
        $this->query->minSize(260, 0);
        $this->search = $form->button('search', 'Search');

        $this->satellites = $root->table('satellites', [
            new TableColumn('id', 'NORAD id'),
            new TableColumn('epoch', 'Epoch'),
            new TableColumn('name', 'Name'),
        ]);
        $this->satellites->fill()->minSize(0, 260);

        $this->elements = $root->textArea('elements');
        $this->elements->setFont(self::monospace(13))->minSize(0, 90);
    }
}
