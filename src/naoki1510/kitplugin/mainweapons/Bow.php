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

class Shield implements Listener
{
    public function __construct()
    {
        
    }

    public function onHit(ProjectileHitEntityEvent $e){

    }
}
