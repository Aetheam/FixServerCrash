<div align="center">
    <img src="https://raw.githubusercontent.com/Aetheam/FixServerCrash/main/icon.png" alt="Logo">
</div>

# FixServerCrash
This plugin overwrites the RakLibInterface of **PocketMine-MP**, adding several new functions.

## Features

- Clean motd (Removes the motd version, so there's no longer the "- 1.20.50")
- Remove console ips (This removes the need to display a client's ip address when it connects)
- Disable PacketLimiter Kick (This avoids getting kicked for "Packet Processing Error" all the time)
- AntiCrash (When the server gets an error from a packet handle, so anything closely related to the player won't crash)
## Configurations

| Parameter                    | Type   | Status       | Default |
|:-----------------------------|:-------|:-------------|:--------|
| `clean-motd`                 | `bool` | **Required** | true    |
| `remove-console-ips`         | `bool` | **Required** | true    |
| `disable-packetLimiter-kick` | `bool` | **Required** | true    |

#### Crash (crash:)

| Parameter | Type     | Status       | Default                                                                                             |
|:----------|:---------|:-------------|:----------------------------------------------------------------------------------------------------|
| `type`    | `string` | **Required** | "kick" (available: kick, message)                                                                   |
| `message` | `string` | **Required** | "§cAn error has occurred on the server side, if this continues please ask our team for assistance." |

## Support
For advanced support, please contact Aetheam support: https://discord.gg/KtBYQ5X5dW


## Authors
- [@Zwuiix-cmd](https://www.github.com/Zwuiix-cmd)

