<?php

namespace naoki1510\kitplugin\subweapons;

use naoki1510\kitplugin\tasks\BlockRecoveryTask;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\item\Item;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\utils\Config;
use pocketmine\Server;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\Player;

class Bow implements Listener
{
    public function __construct()
    {
        
    }

    public function onHit(ProjectileHitEntityEvent $e){
        $entity = $e->getEntity();
        if (($shooter = $entity->getOwningEntity() instanceof Player) {
            $distance = $e->getHitEntity()->distance($shooter);
            $entity->setBaseDamage(4 + $distance / 8);
        }
    }
}
