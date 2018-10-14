<?php

namespace naoki1510\kitplugin\mainweapons;

use naoki1510\kitplugin\tasks\RestoreItemTask;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\event\player\PlayerItemUseEvent;
use naoki1510\kitplugin\Weapon;

class SnowBallWeapon extends Weapon
{
    /** @var Int */
    public $maxCount = 48;
    public $weaponId = 332;
    public $delay = 4 * 20;

    /** @var Array */
    public $reloading;
    
    public function onDamage(EntityDamageByChildEntityEvent $e){
        $entity = $e->getChild();
        if ($entity instanceof Snowball) {
            if (($shooter = $entity->getOwningEntity()) instanceof Player) {
                $distance = $e->getEntity()->distance($shooter);
                $damage = 9 - 2 * sqrt($distance + 4);
                if ($damage >= 0) {
                    $e->setBaseDamage($damage);
                } else {
                    $e->setCancelled();
                }
            }
        }
    }

    public function onItemUse(PlayerItemUseEvent $e)
    {
        // Reload Items
        $player = $e->getPlayer();
        /** @var Item $hand */
        $hand = $player->getInventory()->getItemInHand();
        switch ($hand->getId()) {
            case Item::fromString('Snowball')->getId():

                $item = Item::fromString('snowball')->setCount(48);

                $count = 0;
                foreach ($player->getInventory()->getContents() as $invitem) {
                    if ($invitem->getId() === $item->getId() && $invitem->getDamage() === $item->getDamage()) {
                        $count += $invitem->getCount();
                    }
                }
                if ($count <= 4) {
                    $this->reload($player, $item);
                }
                break;
        }
    }
}
