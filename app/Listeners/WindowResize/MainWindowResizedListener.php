<?php

namespace App\Listeners\WindowResize;

use Throwable;
use App\GlobalVariables;
use App\Models\PersistentVariable;
use Surface\Contracts\Windows\Mail\WindowResized;

class MainWindowResizedListener
{
    public function handle(WindowResized $signal): void
    {
        if($signal->name() == 'window.resized.stargazer')
        {
            $bag = GlobalVariables::make();
            $bag->put('window_size_save', PersistentVariable::query()->via()
                ->updateOrCreate(
                    ['key' => "window.{$signal->window}.size"],
                    ['value' => ['width' => $signal->width, 'height' => $signal->height]],
                )
                ->error(
                    fn (Throwable $e) => ($bag->warn)("{$signal->window} size not saved: {$e->getMessage()}")
                )
            );
        }
    }
}
