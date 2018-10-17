<?php

namespace naoki1510\kitplugin;

use naoki1510\kitplugin\KitPlugin;
use naoki1510\kitplugin\tasks\BlockRecoveryTask;
use naoki1510\kitplugin\tasks\RestoreItemTask;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\Item;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\utils\Config;



class EventListener implements Listener
{
    /** @var Config */
    public $kit;
    public $playerdata;
    public $config;
    public $overlapping;

    /** @var Array */
    public $reloading;

    /** @var string[] */
    public $levels = [];

    /** @var TaskScheduler */
    public $scheduler;

    public function __construct(KitPlugin $kitPlugin)
    {
        $this->config = $kitPlugin->getConfig();
        $this->scheduler = $kitPlugin->getScheduler();
        $this->levels = $kitPlugin->getConfig()->get('gameworlds', []);
        $this->kit = $kitPlugin->kit;
        $this->playerdata = $kitPlugin->playerdata;
        $this->overlapping = new Config($kitPlugin->getDataFolder() . 'ov.yml', Config::YAML);
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

    /* Bow and Arrow */
    public function onHit(ProjectileHitEntityEvent $e)
    {
        $entity = $e->getEntity();
        if ($entity instanceof Arrow) {
            if (($shooter = $entity->getOwningEntity()) instanceof Player) {
                $distance = $e->getEntityHit()->distance($shooter);
                $damage = 2 + $distance / 8;
                $entity->setBaseDamage($damage);
            }
        }
    }

    /* Snowball */
    public function onDamage(EntityDamageByChildEntityEvent $e)
    {
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

    /* Shield */
    public function onPlace(BlockPlaceEvent $e)
    {
        //Playerなどイベント関連情報を取得
        $player = $e->getPlayer();
        if (!in_array($player->getLevel()->getName(), $this->levels)) return;
        $block = $e->getBlock();
        $id = $block->getId();
        if ($id === Item::fromString('stained_glass_pane')->getId()) {
            $changedPos = [];
            for ($x = -2; $x < 3; $x++) {
                for ($y = 0; $y < 3; $y++) {
                    if ($player->yaw % 180 < 45 || $player->yaw % 180 > 135) {
                        $pos = $block->asPosition()->add($x, $y);
                    } else {
                        $pos = $block->asPosition()->add(0, $y, $x);
                    }
                    $rblock = $player->getLevel()->getBlock($pos);
                    if ($rblock->getId() === 0) {
                        $player->getLevel()->setBlock($pos, $block);
                        array_push($changedPos, $pos);
                    } else if ($rblock->getId() === Item::fromString('stained_glass_pane')->getId()) {
                        array_push($changedPos, $pos);
                        $this->overlapping->set(implode(":", [$pos->x, $pos->y, $pos->z]), Server::getInstance()->getTick() + 6 * 20 - 1);
                    }
                }
            }
            $hand = $player->getInventory()->getItemInHand();
            $player->getInventory()->setItemInHand($hand->setCount($hand->getCount() - 1));
            $e->setCancelled();
            $this->scheduler->scheduleDelayedTask(
                new BlockRecoveryTask($changedPos, $player->getLevel(), $this->overlapping),
                6 * 20
            );
        }
    }
}
