<?php

namespace naoki1510\kitplugin\subweapons;

use pocketmine\entity\projectile\Arrow;
use pocketmine\event\Listener;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\Player;

class Bow implements Listener
{
    public function __construct()
    {
        
    }

    public function onHit(ProjectileHitEntityEvent $e){
        $entity = $e->getEntity();
        if ($entity instanceof Arrow) {
            if (($shooter = $entity->getOwningEntity() instanceof Player) {
                $distance = $e->getHitEntity()->distance($shooter);
                $entity->setBaseDamage(4 + $distance / 8);
            }
        }
    }
}
