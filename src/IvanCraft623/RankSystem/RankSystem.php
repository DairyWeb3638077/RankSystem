<?php

#Plugin By:

/*
	8888888                            .d8888b.                   .d888 888     .d8888b.   .d8888b.   .d8888b.  
	  888                             d88P  Y88b                 d88P"  888    d88P  Y88b d88P  Y88b d88P  Y88b 
	  888                             888    888                 888    888    888               888      .d88P 
	  888  888  888  8888b.  88888b.  888        888d888 8888b.  888888 888888 888d888b.       .d88P     8888"  
	  888  888  888     "88b 888 "88b 888        888P"      "88b 888    888    888P "Y88b  .od888P"       "Y8b. 
	  888  Y88  88P .d888888 888  888 888    888 888    .d888888 888    888    888    888 d88P"      888    888 
	  888   Y8bd8P  888  888 888  888 Y88b  d88P 888    888  888 888    Y88b.  Y88b  d88P 888"       Y88b  d88P 
	8888888  Y88P   "Y888888 888  888  "Y8888P"  888    "Y888888 888     "Y888  "Y8888P"  888888888   "Y8888P"  
*/

declare(strict_types=1);

namespace IvanCraft623\RankSystem;

use IvanCraft623\RankSystem\{command\RanksCommand, session\SessionManager, rank\RankManager, task\UpdateTask};
use IvanCraft623\RankSystem\provider\{Provider, YAML, SQLite3};

use pocketmine\{plugin\PluginBase, utils\Config, utils\SingletonTrait, permission\DefaultPermissions};

class RankSystem extends PluginBase {
	use SingletonTrait;

	/** @var array */
	private static $globalPerms = [];

	/** @var array */
	private static $pmDefaultPerms = [];

	/** @var Provider */
	private $provider;

	public function onLoad() : void {
		self::setInstance($this);
		$this->saveResources();
		$this->getRankManager()->load();
	}

	public function onEnable() : void {
		$this->loadCommands();
		$this->loadEvents();
		$this->loadProvider();
		$this->getScheduler()->scheduleRepeatingTask(new UpdateTask(), 20);
	}

	public function getProvider() : Provider{
		return $this->provider;
	}

	public function getSessionManager() : SessionManager {
		return SessionManager::getInstance();
	}

	public function getRankManager() : RankManager {
		return RankManager::getInstance();
	}

	public function getConfigs(string $value) : Config {
		return new Config(self::getInstance()->getDataFolder() . $value, Config::YAML);
	}

	public function getGlobalPerms() : array {
		if (self::$globalPerms === []) {
			self::$globalPerms = $this->getConfigs("config.yml")->get("Global_Perms");
		}
		return self::$globalPerms;
	}
	
	private function fixConfig()
    {
        $config = $this->getConfig();

        if(!$config->exists("superadmin-ranks"))
            $config->set("superadmin-ranks", ["OP"]);

        $this->saveConfig();
        $this->reloadConfig();
    }

	/**
	 * From PurePerms
	 */
	public function getPmmpPerms() : array {
		if (self::$pmDefaultPerms === []) {
			foreach ($this->getServer()->getPluginManager()->getPermissions() as $permission) {
				if (strpos($permission->getName(), DefaultPermissions::ROOT) !== false) {
					self::$pmDefaultPerms[] = $permission;
				}
			}
		}
		return self::$pmDefaultPerms;
	}

	public function getPluginPerms(PluginBase $plugin) : array {
		return $plugin->getDescription()->getPermissions();
	}

	public function saveResources() : void {
		$this->saveResource("config.yml");
		$this->saveResource("ranks.yml");
	}
	
	/**
     * @param IPlayer $player
     * @param string|null $levelName
     */
    public function updatePermissions(IPlayer $player, string $levelName = null)
    {
        if($player instanceof Player)
        {
            if($this->getConfigValue("enable-multiworld-perms") == null) {
                $levelName = null;
            }elseif($levelName == null) {
                $levelName = $player->getLevel()->getName();
            }

            $permissions = [];

            /** @var string $permission */
            foreach($this->getPermissions($player, $levelName) as $permission)
            {
                if($permission === '*')
                {
                    foreach($this->getServer()->getPluginManager()->getPermissions() as $tmp)
                    {
                        $permissions[$tmp->getName()] = true;
                    }
                }
                else
                {
                    $isNegative = substr($permission, 0, 1) === "-";

                    if($isNegative)
                        $permission = substr($permission, 1);

                    $permissions[$permission] = !$isNegative;
                }
            }

            $permissions[self::CORE_PERM] = true;

            /** @var \pocketmine\permission\PermissionAttachment $attachment */
            $attachment = $this->getAttachment($player);

            $attachment->clearPermissions();

            $attachment->setPermissions($permissions);
        }
    }

	public function loadCommands() : void {
		$values = [new RanksCommand($this)];
		foreach ($values as $commands) {
			$this->getServer()->getCommandMap()->register('_cmd', $commands);
		}
		unset($values);
	}

	public function loadEvents() : void {
		$values = [new EventListener()];
		foreach ($values as $events) {
			$this->getServer()->getPluginManager()->registerEvents($events, $this);
		}
		unset($values);
	}

	public function loadProvider() : void {
		switch (strtolower($this->getConfigs("config.yml")->get("Database-provider"))) {
			case 'yaml':
				$provider = YAML::class;
			break;
			
			
			case 'sqlite3':
			default:
				$provider = SQLite3::class;
			break;
		}
		$this->provider = $provider::getInstance();
		$this->provider->load();
		$this->getLogger()->info("User provider was set to: " . $this->provider->getName());
	}
}
