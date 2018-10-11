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

class SnowBallWeapon implements Listener
{
    /** @var TaskScheduler */
    private $scheduler;

    /** @var Array */
    public $reloading;

    public function __construct(TaskScheduler $scheduler)
    {
        $this->scheduler = $scheduler;
    }
    
    public function onDamage(EntityDamageByChildEntityEvent $e){
        $entity = $e->getChild();
        if ($entity instanceof Snowball) {
            if (($shooter = $entity->getOwningEntity()) instanceof Player) {
                $distance = $e->getEntity()->distance($shooter);
                $damage = 12 - sqrt($distance + 4);
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
            case Item::fromString('Snow')->getId():

                $item = Item::fromString('snowball')->setCount(48);

                $count = 0;
                foreach ($player->getInventory()->getContents() as $invitem) {
                    if ($invitem->getId() === $item->getId() && $invitem->getDamage() === $item->getDamage()) {
                        $count += $invitem->getCount();
                    }
                }
                if ($count < $item->getCount()) {
                    if(empty($this->reloading[$player->getName()]) || $this->reloading[$player->getName()] < Server::getInstance()->getTick()){
                        $this->scheduler->scheduleDelayedTask(new RestoreItemTask(
                            $item,
                            $player
                        ), 4 * 20);

                        $player->sendMessage('Reloading...');
                        $this->reloading[$player->getName()] = Server::getInstance()->getTick() + 20 * 4;
                    }
                    var_dump($this->reloading[$player->getName()], Server::getInstance()->getTick());
                    
                }else{
                    $player->sendMessage('You don\'t have to reload');
                }

                $e->setCancelled();
                break;
        }
    }
}
