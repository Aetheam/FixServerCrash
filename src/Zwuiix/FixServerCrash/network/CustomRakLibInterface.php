<?php

namespace Zwuiix\FixServerCrash\network;

use pocketmine\lang\KnownTranslationFactory;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\network\mcpe\raklib\RakLibPacketSender;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Utils;
use ReflectionException;
use Throwable;
use Zwuiix\FixServerCrash\FixServerCrash;
use Zwuiix\FixServerCrash\utils\ReflectionUtils;

class CustomRakLibInterface extends RakLibInterface
{
    private const MCPE_RAKNET_PACKET_ID = "\xfe";

    public function __construct(
        protected Server $server,
        string $ip,
        int $port,
        bool $ipV6,
        protected PacketBroadcaster $packetBroadcaster,
        protected EntityEventBroadcaster $entityEventBroadcaster,
        protected PacketSerializerContext $packetSerializerContext,
        protected TypeConverter $typeConverter
    ) {
        parent::__construct($server, $ip, $port, $ipV6, $packetBroadcaster, $entityEventBroadcaster, $packetSerializerContext, $typeConverter);
    }

    /**
     * @param string $name
     * @return void
     * @throws ReflectionException
     */
    public function setName(string $name) : void{
        if(!FixServerCrash::getInstance()->getCache()[FixServerCrash::CLEAN_MOTD]) {
            parent::setName($name);
            return;
        }

        $info = $this->server->getQueryInformation();
        ReflectionUtils::getProperty(RakLibInterface::class, $this, "interface")->setName(implode(";",
                [
                    "MCPE",
                    rtrim(addcslashes($name, ";"), '\\'),
                    ProtocolInfo::CURRENT_PROTOCOL,
                    "",
                    $info->getPlayerCount(),
                    $info->getMaxPlayerCount(),
                    mt_rand(0, PHP_INT_MAX),
                    $this->server->getName(),
                    match($this->server->getGamemode()){
                        GameMode::SURVIVAL => "Survival",
                        GameMode::ADVENTURE => "Adventure",
                        default => "Creative"
                    }
                ]) . ";"
        );
    }

    /**
     * @param int $sessionId
     * @param string $packet
     * @return void
     * @throws ReflectionException
     * @throws Throwable
     */
    public function onPacketReceive(int $sessionId, string $packet) : void
    {
        $sessions = ReflectionUtils::getProperty(RakLibInterface::class, $this, "sessions");
        if(isset($sessions[$sessionId])){
            if($packet === "" || $packet[0] !== self::MCPE_RAKNET_PACKET_ID){
                $sessions[$sessionId]->getLogger()->debug("Non-FE packet received: " . base64_encode($packet));
                return;
            }
            //get this now for blocking in case the player was closed before the exception was raised
            $session = $sessions[$sessionId];
            $address = $session->getIp();
            $buf = substr($packet, 1);
            $name = $session->getDisplayName();
            try{
                $session->handleEncoded($buf);
            }catch(PacketHandlingException $e){
                $logger = $session->getLogger();
                $logger->error("Bad packet: " . $e->getMessage());

                $logger->debug(implode("\n", Utils::printableExceptionInfo($e)));
                $session->disconnectWithError(KnownTranslationFactory::pocketmine_disconnect_error_badPacket());
                ReflectionUtils::getProperty(RakLibInterface::class, $this, "network")->blockAddress($address, 5);
            }catch(Throwable $e){
                $logger = $this->server->getLogger();
                $logger->debug("Packet " . (isset($pk) ? get_class($pk) : "unknown") . ": " . base64_encode($buf));
                $logger->logException($e);

                $player = $session->getPlayer();
                if(!$player instanceof Player) {
                    //record the name of the player who caused the crash, to make it easier to find the reproducing steps
                    $this->server->getLogger()->emergency("Crash occurred while handling a packet from session: $name");
                    throw $e;
                }

                $cache = FixServerCrash::getInstance()->getCache();
                $message = $cache[FixServerCrash::CRASH_MESSAGE];
                switch ($cache[FixServerCrash::CRASH_TYPE]) {
                    case "kick":
                        $player->kick($message, true);
                        break;
                    case "message":
                        $player->sendMessage($message);
                        break;
                }
            }
        }
    }

    /**
     * @param int $sessionId
     * @param string $address
     * @param int $port
     * @param int $clientID
     * @return void
     * @throws ReflectionException
     */
    public function onClientConnect(int $sessionId, string $address, int $port, int $clientID) : void{
        $sessions = ReflectionUtils::getProperty(RakLibInterface::class, $this, "sessions");
        $network = ReflectionUtils::getProperty(RakLibInterface::class, $this, "network");

        $session = new CustomNetworkSession(
            $this->server,
            $network->getSessionManager(),
            PacketPool::getInstance(),
            $this->packetSerializerContext,
            new RakLibPacketSender($sessionId, $this),
            $this->packetBroadcaster,
            $this->entityEventBroadcaster,
            ZlibCompressor::getInstance(), //TODO: this shouldn't be hardcoded, but we might need the RakNet protocol version to select it
            $this->typeConverter,
            $address,
            $port
        );
        $sessions[$sessionId] = $session;
        ReflectionUtils::setProperty(RakLibInterface::class, $this, "sessions", $sessions);
    }
}