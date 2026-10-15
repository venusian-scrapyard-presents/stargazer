<?php

namespace App\Runner\Controllers;

use App\GlobalVariables;
use App\Services\TechTransfer\TechTransferService;
use App\Workflows\TechTransfer\SearchTechTransfer\SearchTechTransferStart;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\RowSelected;
use Surface\Contracts\Windows\Mail\View\TextSubmitted;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\Signals\NamedSignal;
use Voyager\Workflows\Flow;

class TechTransferController extends StargazerController
{
    public function __construct(
        ToolkitWindow $window,
        public readonly TechTransferService $service
    )
    {
        parent::__construct($window);
    }

    public function title(): string
    {
        return 'Tech Transfer';
    }

    public function key(): string
    {
        return 'techtransfer';
    }

    public function menu(): string
    {
        return 'main';
    }

    public function refresh(): void
    {
        $start_node = new SearchTechTransferStart($this->service);
        new Flow($start_node)->run(GlobalVariables::make());
    }

    public function index(TKColumn $root): void
    {
        $this->service->mounted($root);
    }

    public function deliver(NamedSignal $mail): bool
    {
        return match (true) {
            $mail instanceof TextSubmitted && $this->service->isQuery($mail->uuid()),
            $mail instanceof ButtonClicked && $this->service->isSearch($mail->uuid()) => $this->reload(),
            $mail instanceof RowSelected && $this->service->isResults($mail->uuid()) => $this->service->select($mail->row),
            default => false,
        };
    }

    protected function reload(): bool
    {
        $this->refresh();

        return true;
    }
}
