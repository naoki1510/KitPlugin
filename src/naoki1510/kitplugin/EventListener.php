<?php

namespace naoki1510\kitplugin;

use naoki1510\kitplugin\tasks\BlockRecoveryTask;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\player\PlayerAnimationEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\level\Explosion;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerEvent;
use pocketmine\event\entity\EntityEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\server\DataPacketReceiveEvent;

class EventListener implements Listener
{
    /** @var Config */
    public $config;
    private $protectedBlock;

    /** @var TaskScheduler */
    public $scheduler;

    public function __construct(KitPlugin $kitPlugin)
    {
        $this->config = $kitPlugin->getConfig();
        $this->protectedBlock = new Config($kitPlugin->getDataFolder() . 'pb.yml', Config::YAML);
        $this->scheduler = $kitPlugin->getScheduler();
    }

    public function getConfig() : Config{
        return $this->config;
    }

    public function getScheduler() : TaskScheduler
    {
        return $this->scheduler;
    }

    public function getServer() : Server{
        return Server::getInstance();
    }


    

    public function onDamage(EntityDamageEvent $e)
    {
        $this->getServer()->getLogger()->info($e->getFinalDamage());
    }
}
