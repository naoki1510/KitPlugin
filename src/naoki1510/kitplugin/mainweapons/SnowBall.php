<?php

namespace naoki1510\kitplugin\subweapons;

use pocketmine\entity\projectile\SnowBall;
use pocketmine\event\Listener;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\Player;

class SnowBall implements Listener
{
    public function __construct()
    {
        
    }

    public function onHit(ProjectileHitEntityEvent $e){
        $entity = $e->getEntity();
        if ($entity instanceof SnowBall) {
            if (($shooter = $entity->getOwningEntity() instanceof Player) {
                $distance = $e->getHitEntity()->distance($shooter);
                $damage = 6 - sqrt($distance + 4);
                if ($damage <= 0) {
                    $entity->setBaseDamage($damage);
                }else {
                    $e->setCancelled();
                }
            }
        }
    }
}
