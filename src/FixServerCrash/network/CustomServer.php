<?php

namespace FixServerCrash\network;

use FixServerCrash\utils\ReflectionUtils;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ReflectionException;

class CustomServer
{
    /**
     * @param Player $player
     * @return bool
     * @throws ReflectionException
     */
    public static function addOnlinePlayer(Player $player) : bool{
        $ev = new PlayerLoginEvent($player, "Plugin reason");
        $ev->call();
        if($ev->isCancelled() || !$player->isConnected()){
            $player->disconnect($ev->getKickMessage());

            return false;
        }

        $position = $player->getPosition();
        $server = Server::getInstance();
        $server->getLogger()->info(sprintf(TextFormat::AQUA . "%s" . TextFormat::RESET . " logged in with entity id %s at (%s, %s, %s, %s)",
            $player->getName(),
            (string) $player->getId(),
            $position->getWorld()->getDisplayName(),
            (string) round($position->x, 4),
            (string) round($position->y, 4),
            (string) round($position->z, 4)
        ));

        $uniquePlayers = ReflectionUtils::getProperty(Server::class, $server, "uniquePlayers");
        $playerList = ReflectionUtils::getProperty(Server::class, $server, "playerList");
        foreach($playerList as $p){
            $p->getNetworkSession()->onPlayerAdded($player);
        }
        $rawUUID = $player->getUniqueId()->getBytes();
        $playerList[$rawUUID] = $player;
        ReflectionUtils::setProperty(Server::class, $server, "playerList", $playerList);

        if(ReflectionUtils::getProperty(Server::class, $server, "sendUsageTicker") > 0){
            $uniquePlayers[$rawUUID] = $rawUUID;
        }
        ReflectionUtils::setProperty(Server::class, $server, "uniquePlayers", $uniquePlayers);
        return true;
    }
}