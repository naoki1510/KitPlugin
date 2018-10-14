<?php

namespace naoki1510\kitplugin\mainweapons;

use naoki1510\kitplugin\tasks\RestoreItemTask;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\Listener;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\scheduler\TaskScheduler;
use naoki1510\kitplugin\Weapon;

class BowWeapon extends Weapon// implements Listener
{
    /** @var Int */
    public $maxCount = 32;
    public $weaponId = 261;
    public $itemId = 262;
    public $delay = 5 * 20;

    /** @var Array */
    public $reloading;
    
    public function onHit(ProjectileHitEntityEvent $e){
        $entity = $e->getEntity();
        if ($entity instanceof Arrow) {
            if (($shooter = $entity->getOwningEntity()) instanceof Player) {
                $distance = $e->getEntityHit()->distance($shooter);
                $damage = 1 + $distance / 16;
                $entity->setBaseDamage($damage);
            }
        }
    }

    public function onPlayerTap(PlayerInteractEvent $e)
    {
        //$this->getServer()->getLogger()->info($e->getEventName() . " was Called.");
        $player = $e->getPlayer();
        /** @var Item $hand */
        $hand = $player->getInventory()->getItemInHand();
        switch ($hand->getId()) {
            case Item::fromString('Bow')->getId():

                $item = Item::fromString('Arrow')->setCount($this->maxCount);

                $this->reload($player, $item);

                $e->setCancelled();
                break;
        }
    }
}
