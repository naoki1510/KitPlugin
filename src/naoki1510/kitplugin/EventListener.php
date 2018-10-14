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

    public function reload(Player $player, Item $item, $delay = null, $force = false)
    {
        $count = 0;
        $delay = $delay ?? 0;
        $kit = $this->playerdata->getNested($player->getName() . '.now');
        if (!$this->kit->exists($kit)) return false;

        $data = $this->kit->get($kit);
        $max = 0;

        foreach ($data['items'] as $itemInfo) {
            try {
                $ritem = Item::fromString($itemInfo['name']);
                if ($ritem->getId() === $item->getId()) {
                    $max += $itemInfo['count'] ?? 1;
                }
            } catch (\InvalidArgumentException $e) {
                $this->getLogger()->warning($e->getMessage());
            }
        }
        foreach ($player->getInventory()->getContents() as $slot => $invitem) {
            if ($invitem->getId() === $item->getId() && $invitem->getDamage() === $item->getDamage()) {
                $count += $invitem->getCount();
                if ($force) $player->getInventory()->setItem($slot, Item::get(0));
            }
        }
        if ($count < $max || $force) {
            $this->scheduler->scheduleDelayedTask(new RestoreItemTask(
                $item,
                $player
            ), $delay);
            if (empty($this->reloading[$player->getName()]) || $this->reloading[$player->getName()] < Server::getInstance()->getTick()) {
                $player->sendMessage('Reloading...');
                $this->reloading[$player->getName()] = Server::getInstance()->getTick() + $delay;
            }
        }
    }

    /* Bow and Arrow */
    public function onHit(ProjectileHitEntityEvent $e)
    {
        $entity = $e->getEntity();
        if ($entity instanceof Arrow) {
            if (($shooter = $entity->getOwningEntity()) instanceof Player) {
                $distance = $e->getEntityHit()->distance($shooter);
                $damage = 2 + $distance / 16;
                $entity->setBaseDamage($damage);
            }
        }
    }

    public function onPlayerTap(PlayerInteractEvent $e)
    {
        $player = $e->getPlayer();
        /** @var Item $hand */
        $hand = $player->getInventory()->getItemInHand();
        switch ($hand->getId()) {
            case Item::BOW:
                $kit = $this->playerdata->getNested($player->getName() . '.now');
                if (!$this->kit->exists($kit)) return false;

                $data = $this->kit->get($kit);
                $count = 0;

                foreach ($data['items'] as $itemInfo) {
                    try {
                        $item = Item::fromString($itemInfo['name']);
                        if ($item->getId() === Item::ARROW) {
                            $count += $itemInfo['count'] ?? 1;
                        }
                    } catch (\InvalidArgumentException $e) {
                        $this->getLogger()->warning($e->getMessage());
                    }
                }
                $item = Item::fromString('Arrow')->setCount($count);

                $this->reload($player, $item, 8 * 20);

                $e->setCancelled();
                break;
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

    public function onItemUse(PlayerItemUseEvent $e)
    {
        // Reload Items
        $player = $e->getPlayer();
        /** @var Item $hand */
        $hand = $player->getInventory()->getItemInHand();
        switch ($hand->getId()) {
            case Item::SNOWBALL:
                $kit = $this->playerdata->getNested($player->getName() . '.now');
                if (!$this->kit->exists($kit)) return false;

                $data = $this->kit->get($kit);
                $count = 0;

                foreach ($data['items'] as $itemInfo) {
                    try {
                        $item = Item::fromString($itemInfo['name']);
                        if ($item->getId() === Item::SNOWBALL) {
                            $count += $itemInfo['count'] ?? 1;
                        }
                    } catch (\InvalidArgumentException $e) {
                        $this->getLogger()->warning($e->getMessage());
                    }
                }
                $item = Item::fromString('snowball')->setCount($count);

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
                        $this->overlapping->set(implode(":", [$pos->x, $pos->y, $pos->z]), Server::getInstance()->getTick() + 10 * 20);
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
            $this->reload($player, $block->getItem(), 20 * 10);
        }
    }
}
