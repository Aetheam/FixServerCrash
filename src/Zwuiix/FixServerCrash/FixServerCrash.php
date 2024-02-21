<?php

namespace Zwuiix\FixServerCrash;

use pocketmine\plugin\PluginBase;
use pocketmine\ServerProperties;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use Zwuiix\FixServerCrash\libs\SenseiTarzan\ExtraEvent\Component\EventLoader;
use Zwuiix\FixServerCrash\listener\ServerListener;

class FixServerCrash extends PluginBase
{
    use SingletonTrait;

    const MINIMUM_API_VERSION = "5.11.0";
    const CLEAN_MOTD = "cleanMotd";
    const REMOVE_IPS = "removeIps";
    const DISABLE_PACKETLIMITER = "disablePacketLimiter";
    const CRASH_TYPE = "crashType";
    const CRASH_MESSAGE = "crashMessage";

    protected array $cache = [];

    /**
     * @return void
     */
    protected function onLoad(): void
    {
        $this::setInstance($this);
        $this->reloadConfig();
    }

    /**
     * @return void
     */
    protected function onEnable(): void
    {
        if ($this->getServer()->getApiVersion() < $this::MINIMUM_API_VERSION) {
            $this->getLogger()->warning(sprintf("Sorry, you are using a version prior to %s, please update your pocketmine version to make this plugin work.", $this::MINIMUM_API_VERSION));
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        if ($this->getServer()->getConfigGroup()->getConfigBool(ServerProperties::ENABLE_IPV6)) {
            $this->getLogger()->warning("Please deactivate the \"enable-ipv6\" option in the server.properties file.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $data = $this->getConfig();
        if (is_bool($cleanMotd = $data->get("clean-motd", true))) {
            $this->cache[$this::CLEAN_MOTD] = $cleanMotd;
        } else $this->cache[$this::CLEAN_MOTD] = boolval($cleanMotd);

        if (is_bool($removeConsoleIps = $data->get("remove-console-ips", true))) {
            $this->cache[$this::REMOVE_IPS] = $removeConsoleIps;
        } else $this->cache[$this::REMOVE_IPS] = boolval($removeConsoleIps);

        if (is_bool($disablePacketLimiter = $data->get("disable-packetLimiter-kick", true))) {
            $this->cache[$this::DISABLE_PACKETLIMITER] = $disablePacketLimiter;
        } else $this->cache[$this::DISABLE_PACKETLIMITER] = boolval($disablePacketLimiter);

        $this->cache[$this::CRASH_TYPE] = match ($crashType = $data->getNested("crash.type", "kick")) {
            "message" => $crashType,
            default => "kick"
        };
        $this->cache[$this::CRASH_MESSAGE] = $data->getNested("crash.message", TextFormat::RED . "An error has occurred on the server side, if this continues please ask our team for assistance.");

        EventLoader::loadEventWithClass($this, ServerListener::class);
        $this->getLogger()->debug("Successfully loaded!");
    }

    /**
     * @return array
     */
    public function getCache(): array
    {
        return $this->cache;
    }
}
