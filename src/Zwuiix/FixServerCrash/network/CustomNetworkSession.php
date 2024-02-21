<?php

namespace Zwuiix\FixServerCrash\network;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\network\mcpe\compression\DecompressionException;
use pocketmine\network\mcpe\encryption\DecryptionException;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\Player;
use pocketmine\timings\Timings;
use pocketmine\utils\BinaryDataException;
use pocketmine\utils\BinaryStream;
use ReflectionException;
use Zwuiix\FixServerCrash\FixServerCrash;
use Zwuiix\FixServerCrash\utils\ReflectionUtils;

class CustomNetworkSession extends NetworkSession
{
    /**
     * @return string
     * @throws ReflectionException
     */
    public function getDisplayName() : string{
        if(!FixServerCrash::getInstance()->getCache()[FixServerCrash::REMOVE_IPS]) {
            return parent::getDisplayName();
        }
        return ($info = ReflectionUtils::getProperty(NetworkSession::class, $this, "info")) !== null ? $info->getUsername() : "Unknown";
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    protected function createPlayer() : void{
        if(!FixServerCrash::getInstance()->getCache()[FixServerCrash::REMOVE_IPS]) {
            parent::createPlayer();
            return;
        }

        // WTF Poggit ??
        $playerCreated = function(Player $player) : void{
            if(!$this->isConnected()){
                //the remote player might have disconnected before spawn terrain generation was finished
                return;
            }
            ReflectionUtils::setProperty(NetworkSession::class, $this, "player", $player);
            if(!CustomServer::addOnlinePlayer($player)){
                return;
            }

            ReflectionUtils::setProperty(NetworkSession::class, $this, "invManager", new InventoryManager($player, $this));

            $effectManager = ReflectionUtils::getProperty(NetworkSession::class, $this, "player")->getEffects();
            $effectManager->getEffectAddHooks()->add($effectAddHook = function(EffectInstance $effect, bool $replacesOldEffect) : void{
                ReflectionUtils::getProperty(NetworkSession::class, $this, "entityEventBroadcaster")->onEntityEffectAdded([$this], ReflectionUtils::getProperty(NetworkSession::class, $this, "player"), $effect, $replacesOldEffect);
            });
            $effectManager->getEffectRemoveHooks()->add($effectRemoveHook = function(EffectInstance $effect): void{
                ReflectionUtils::getProperty(NetworkSession::class, $this, "entityEventBroadcaster")->onEntityEffectRemoved([$this], ReflectionUtils::getProperty(NetworkSession::class, $this, "player"), $effect);
            });
            ReflectionUtils::getProperty(NetworkSession::class, $this, "disposeHooks")->add(static function() use ($effectManager, $effectAddHook, $effectRemoveHook) : void{
                $effectManager->getEffectAddHooks()->remove($effectAddHook);
                $effectManager->getEffectRemoveHooks()->remove($effectRemoveHook);
            });

            $permissionHooks = ReflectionUtils::getProperty(NetworkSession::class, $this, "player")->getPermissionRecalculationCallbacks();
            $permissionHooks->add($permHook = function() : void{
                ReflectionUtils::getProperty(NetworkSession::class, $this, "logger")->debug("Syncing available commands and abilities/permissions due to permission recalculation");
                $this->syncAbilities(ReflectionUtils::getProperty(NetworkSession::class, $this, "player"));
                $this->syncAvailableCommands();
            });
            ReflectionUtils::getProperty(NetworkSession::class, $this, "disposeHooks")->add(static function() use ($permissionHooks, $permHook) : void{
                $permissionHooks->remove($permHook);
            });
            ReflectionUtils::invoke(NetworkSession::class, $this, "beginSpawnSequence");
        };
        $callable = function() : void{
            ReflectionUtils::getProperty(NetworkSession::class, $this, "logger")->error("Failed to create player");
            $this->disconnectWithError(KnownTranslationFactory::pocketmine_disconnect_error_internal());
        };
        ReflectionUtils::getProperty(NetworkSession::class, $this, "server")->createPlayer($this, ReflectionUtils::getProperty(NetworkSession::class, $this, "info"), ReflectionUtils::getProperty(NetworkSession::class, $this, "authenticated"), ReflectionUtils::getProperty(NetworkSession::class, $this, "cachedOfflinePlayerData"))->onCompletion(
            $playerCreated,
            $callable
        );
    }

    /**
     * @param Player $player
     * @return void
     * @throws ReflectionException
     */
    private function onPlayerCreated(Player $player) : void{
        if(!$this->isConnected()){
            //the remote player might have disconnected before spawn terrain generation was finished
            return;
        }
        ReflectionUtils::setProperty(NetworkSession::class, $this, "player", $player);
        if(!CustomServer::addOnlinePlayer($player)){
            return;
        }

        ReflectionUtils::setProperty(NetworkSession::class, $this, "invManager", new InventoryManager($player, $this));

        $effectManager = ReflectionUtils::getProperty(NetworkSession::class, $this, "player")->getEffects();
        $effectManager->getEffectAddHooks()->add($effectAddHook = function(EffectInstance $effect, bool $replacesOldEffect) : void{
            ReflectionUtils::getProperty(NetworkSession::class, $this, "entityEventBroadcaster")->onEntityEffectAdded([$this], ReflectionUtils::getProperty(NetworkSession::class, $this, "player"), $effect, $replacesOldEffect);
        });
        $effectManager->getEffectRemoveHooks()->add($effectRemoveHook = function(EffectInstance $effect): void{
            ReflectionUtils::getProperty(NetworkSession::class, $this, "entityEventBroadcaster")->onEntityEffectRemoved([$this], ReflectionUtils::getProperty(NetworkSession::class, $this, "player"), $effect);
        });
        ReflectionUtils::getProperty(NetworkSession::class, $this, "disposeHooks")->add(static function() use ($effectManager, $effectAddHook, $effectRemoveHook) : void{
            $effectManager->getEffectAddHooks()->remove($effectAddHook);
            $effectManager->getEffectRemoveHooks()->remove($effectRemoveHook);
        });

        $permissionHooks = ReflectionUtils::getProperty(NetworkSession::class, $this, "player")->getPermissionRecalculationCallbacks();
        $permissionHooks->add($permHook = function() : void{
            ReflectionUtils::getProperty(NetworkSession::class, $this, "logger")->debug("Syncing available commands and abilities/permissions due to permission recalculation");
            $this->syncAbilities(ReflectionUtils::getProperty(NetworkSession::class, $this, "player"));
            $this->syncAvailableCommands();
        });
        ReflectionUtils::getProperty(NetworkSession::class, $this, "disposeHooks")->add(static function() use ($permissionHooks, $permHook) : void{
            $permissionHooks->remove($permHook);
        });
        ReflectionUtils::invoke(NetworkSession::class, $this, "beginSpawnSequence");
    }

    /**
     * @throws PacketHandlingException
     */
    public function handleEncoded(string $payload) : void{
        if(!FixServerCrash::getInstance()->getCache()[FixServerCrash::DISABLE_PACKETLIMITER]) {
            parent::handleEncoded($payload);
            return;
        }

        if(!ReflectionUtils::getProperty(NetworkSession::class, $this, "connected")){
            return;
        }

        Timings::$playerNetworkReceive->startTiming();
        try{
            ReflectionUtils::getProperty(NetworkSession::class, $this, "packetBatchLimiter")->decrement();

            if(ReflectionUtils::getProperty(NetworkSession::class, $this, "cipher") !== null){
                Timings::$playerNetworkReceiveDecrypt->startTiming();
                try{
                    $payload = ReflectionUtils::getProperty(NetworkSession::class, $this, "cipher")->decrypt($payload);
                }catch(DecryptionException $e){
                    ReflectionUtils::getProperty(NetworkSession::class, $this, "logger")->debug("Encrypted packet: " . base64_encode($payload));
                    throw PacketHandlingException::wrap($e, "Packet decryption error");
                }finally{
                    Timings::$playerNetworkReceiveDecrypt->stopTiming();
                }
            }

            if(strlen($payload) < 1){
                throw new PacketHandlingException("No bytes in payload");
            }

            if(ReflectionUtils::getProperty(NetworkSession::class, $this, "enableCompression")){
                Timings::$playerNetworkReceiveDecompress->startTiming();
                $compressionType = ord($payload[0]);
                $compressed = substr($payload, 1);
                if($compressionType === CompressionAlgorithm::NONE){
                    $decompressed = $compressed;
                }elseif($compressionType === ReflectionUtils::getProperty(NetworkSession::class, $this, "compressor")->getNetworkId()){
                    try{
                        $decompressed = ReflectionUtils::getProperty(NetworkSession::class, $this, "compressor")->decompress($compressed);
                    }catch(DecompressionException $e){
                        ReflectionUtils::getProperty(NetworkSession::class, $this, "logger")->debug("Failed to decompress packet: " . base64_encode($compressed));
                        throw PacketHandlingException::wrap($e, "Compressed packet batch decode error");
                    }finally{
                        Timings::$playerNetworkReceiveDecompress->stopTiming();
                    }
                }else{
                    throw new PacketHandlingException("Packet compressed with unexpected compression type $compressionType");
                }
            }else{
                $decompressed = $payload;
            }

            try{
                $stream = new BinaryStream($decompressed);
                $count = 0;
                foreach(PacketBatch::decodeRaw($stream) as $buffer){
                    ReflectionUtils::getProperty(NetworkSession::class, $this, "gamePacketLimiter")->decrement();
                    if(++$count > 100){
                        throw new PacketHandlingException("Too many packets in batch");
                    }
                    $packet = ReflectionUtils::getProperty(NetworkSession::class, $this, "packetPool")->getPacket($buffer);
                    if($packet === null){
                        ReflectionUtils::getProperty(NetworkSession::class, $this, "logger")->debug("Unknown packet: " . base64_encode($buffer));
                        throw new PacketHandlingException("Unknown packet received");
                    }
                    try{
                        $this->handleDataPacket($packet, $buffer);
                    }catch(PacketHandlingException $e){
                        ReflectionUtils::getProperty(NetworkSession::class, $this, "logger")->debug($packet->getName() . ": " . base64_encode($buffer));
                        throw PacketHandlingException::wrap($e, "Error processing " . $packet->getName());
                    }
                }
            }catch(PacketDecodeException|BinaryDataException $e){
                ReflectionUtils::getProperty(NetworkSession::class, $this, "logger")->logException($e);
                throw PacketHandlingException::wrap($e, "Packet batch decode error");
            }
        }finally{
            Timings::$playerNetworkReceive->stopTiming();
        }
    }
}
