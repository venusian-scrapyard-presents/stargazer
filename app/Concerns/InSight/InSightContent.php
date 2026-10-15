<?php

namespace App\Concerns\InSight;

use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKGrid;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\NutsAndBolts\Color;

trait InSightContent
{
    protected ?TKGrid $sols = null;

    protected function setMainComponents(TKColumn $root): void
    {
        $root->label('header', 'Mars weather at Elysium Planitia')
            ->setFont(new FontSpec(18, FontWeight::SEMIBOLD));
        $root->label('note', 'The lander’s last reported sols; the mission ended in December 2022.')
            ->setTextColor(Color::hex('#8a8a8a'));

        $this->sols = $root->grid('sols', 10);
    }
}
