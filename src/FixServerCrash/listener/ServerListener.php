<?php

namespace FixServerCrash\listener;

use FixServerCrash\libs\SenseiTarzan\ExtraEvent\Class\EventAttribute;
use FixServerCrash\network\CustomRakLibInterface;
use FixServerCrash\utils\ReflectionUtils;
use pocketmine\event\EventPriority;
use pocketmine\event\server\NetworkInterfaceRegisterEvent;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\network\query\DedicatedQueryNetworkInterface;
use pocketmine\Server;
use ReflectionException;

class ServerListener implements \pocketmine\event\Listener
{
    /**
     * @param NetworkInterfaceRegisterEvent $event
     * @return void
     * @throws ReflectionException
     */
    #[EventAttribute(EventPriority::HIGH)]
    public function onInterfaceRegister(NetworkInterfaceRegisterEvent $event): void
    {
        $interface = $event->getInterface();
        if($interface instanceof DedicatedQueryNetworkInterface) {
            $event->cancel();
        }elseif (!$interface instanceof CustomRakLibInterface && $interface instanceof RakLibInterface) {
            $event->cancel();
            $server = Server::getInstance();
            $newInterface = new CustomRakLibInterface($server, $server->getIp(), $server->getPort(), false,
                ReflectionUtils::getProperty(RakLibInterface::class, $interface, "packetBroadcaster"),
                ReflectionUtils::getProperty(RakLibInterface::class, $interface, "entityEventBroadcaster"),
                ReflectionUtils::getProperty(RakLibInterface::class, $interface, "packetSerializerContext"),
                ReflectionUtils::getProperty(RakLibInterface::class, $interface, "typeConverter"),
            );
            $server->getNetwork()->registerInterface($newInterface);
        }
    }
}