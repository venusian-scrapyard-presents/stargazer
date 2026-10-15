<?php

namespace App\Concerns\TechTransfer;

use Surface\Contracts\Windows\Primitives\TableColumn;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKTable;
use Surface\Contracts\Windows\Primitives\TKTextArea;
use Surface\Contracts\Windows\Primitives\TKTextInput;

trait TechTransferContent
{
    protected ?TKDropdown $catalog = null;

    protected ?TKTextInput $query = null;

    protected ?TKButton $search = null;

    protected ?TKTable $results = null;

    protected ?TKTextArea $abstract = null;

    protected function setMainComponents(TKColumn $root): void
    {
        $form = $root->row('form', 10);
        $this->catalog = $form->dropdown('catalog', self::CATALOGS);
        $this->query = $form->textInput('query', 'rover', 'Search NASA technology');
        $this->query->minSize(260, 0);
        $this->search = $form->button('search', 'Search');

        $this->results = $root->table('results', [
            new TableColumn('catalog', 'Catalog'),
            new TableColumn('center', 'Center'),
            new TableColumn('category', 'Category'),
            new TableColumn('title', 'Title'),
        ]);
        $this->results->fill()->minSize(0, 260);

        $this->abstract = $root->textArea('abstract');
        $this->abstract->minSize(0, 140);
    }
}
