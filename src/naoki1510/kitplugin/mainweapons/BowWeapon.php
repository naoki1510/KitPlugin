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

class BowWeapon implements Listener
{
    /** @var TaskScheduler */
    private $scheduler;

    /** @var Array */
    public $reloading;

    public function __construct(TaskScheduler $scheduler)
    {  
        $this->scheduler = $scheduler;
    }

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

                $item = Item::fromString('Arrow')->setCount(32);

                $count = 0;
                foreach ($player->getInventory()->getContents() as $invitem) {
                    if ($invitem->getId() === $item->getId() && $invitem->getDamage() === $item->getDamage()) {
                        $count += $invitem->getCount();
                    }
                }
                if($count <= $item->getCount() && (empty($this->reloading[$player->getName()]) || ($this->reloading[$player->getName()]) < Server::getInstance()->getTick() - 5 * 20)){
                    $this->scheduler->scheduleDelayedTask(new RestoreItemTask(
                        $item,
                        $player
                    ), 5 * 20);

                    $player->sendMessage('Reloading...');
                    $this->reloading[$player->getName()] = Server::getInstance()->getTick();
                }

                $e->setCancelled();
                break;
        }
    }

    public function reload($player){

    }
}
