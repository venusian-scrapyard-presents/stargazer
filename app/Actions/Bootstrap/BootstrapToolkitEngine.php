<?php

namespace App\Actions\Bootstrap;

use ReflectionException;
use Voyager\IOPools\EventLoop;
use Surface\Core\MagicAliases\Toolkit;
use Surface\Contracts\Bridge\BridgedToolkitSession;

class BootstrapToolkitEngine
{
    /**
     * @throws ReflectionException
     */
    public static function run(EventLoop $event_loop): BridgedToolkitSession
    {
        $driver = config('app.toolkit_bridge');

        /** @var BridgedToolkitSession $session */
        $session = Toolkit::bridge()
            ->driver($driver)
            ->connect();

        // Set the toolkit as the holder of the loop's sleep cycle
        $session->joinLoop($event_loop);

        return $session;
    }
}
