<?php

namespace naoki1510\kitplugin;

use naoki1510\kitplugin\KitPlugin;
use naoki1510\kitplugin\mainweapons\BowWeapon;
use naoki1510\kitplugin\mainweapons\SnowBallWeapon;
use naoki1510\kitplugin\subweapons\Bom;
use naoki1510\kitplugin\subweapons\PotionWeapon;
use naoki1510\kitplugin\subweapons\Shield;
use naoki1510\kitplugin\tasks\BlockRecoveryTask;
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\Armor;
use pocketmine\item\Bow;
use pocketmine\item\Item;
use pocketmine\item\Sword;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\level\Explosion;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;


class KitPlugin extends PluginBase implements Listener
{
    /** @var Config */
    private $gachalist;
    private $playerdata;
    private $inventories;

    /** @var Weapon[] */
    private $listeners;

    public function onEnable()
    {
		// 起動時のメッセージ
        $this->getLogger()->info("§eKitPlugin was loaded.");

        $this->saveDefaultConfig();
		//PlayerData.yml作成
        $this->playerdata = new Config($this->getDataFolder() . 'PlayerData.yml', Config::YAML);
        $this->saveResource('gacha.yml');
        $this->gachalist = new Config($this->getDataFolder() . 'gacha.yml', Config::YAML);
        $this->inventories = new Config($this->getDataFolder() . 'inventories.yml', Config::YAML);

		// イベントリスナー登録
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->listeners = [
            new SnowBallWeapon($this->getScheduler(), $this->getConfig()->get('gameworlds', [])),
            new BowWeapon($this->getScheduler(), $this->getConfig()->get('gameworlds', [])),

            new Bom($this->getScheduler(), $this->getConfig()->get('gameworlds', [])),
            new Shield(
                $this->getScheduler(),
                $this->getConfig()->get('gameworlds', []),
                new Config($this->getDataFolder() . 'cache.yml', Config::YAML)
            ),
            new PotionWeapon($this->getScheduler(), $this->getConfig()->get('gameworlds', [])),

        ];
       
        foreach($this->listeners as $listener){
            if($listener instanceof Listener)
            $this->getServer()->getPluginManager()->registerEvents($listener, $this);
        }

    }
    
    /**
     * ガチャを引く。
     * @param Player $player
     * @param string $type
     */
     
    public function gacha(Player $player, string $type){
        if (!$this->gachalist->exists($type)) {
            $player->sendMessage('that type was not found.');
            return;
        }

        $rand = rand(0, 99);
        if(is_array($this->gachalist->get($type, null))){
            $list = $this->gachalist->get($type);

            foreach ($list as $percent => $items) {
                if($rand >= $percent) continue;

                $itemName = array_rand($items);
                $item = Item::fromString($itemName);
                $item->setCount($items[$itemName]);
                if (!$item instanceof Item) return false;
                
                while (true) {
                    if (rand(0, 7) !== 0) break;
                    switch (true) {
                        case $item instanceof Sword:
                            $enchantId = [9, 12, 13][rand(0, 2)];
                            break;

                        case $item instanceof Bow:
                            $enchantId = rand(19, 22);
                            break;

                        case $item instanceof Armor:
                            $enchantId = rand(0, 5);
                            break;

                        default:
                            break 2;
                    }

                    $enchLevel = 1;
                    while(true){
                        if (rand(0, 3) !== 0) break;
                        $enchLevel++;
                    }
                    $ench = Enchantment::getEnchantment($enchantId);
                    if($ench instanceof Enchantment){
                        $item->addEnchantment(new EnchantmentInstance($ench, $enchLevel));
                    }else{
                        var_dump($enchantId, $enchLevel);
                    }
                    
                }

                if ($player->getInventory()->canAddItem($item)) {
                    if (EconomyAPI::getInstance()->reduceMoney($player, $this->getConfig()->getNested('cost.'. $type, 3000)) === 1) {
                        $player->getInventory()->addItem($item);
                        return true;
                    } else {
                        $player->sendMessage('お金が足りません。');
                    }
                } else {
                    $player->sendMessage('インベントリに空きがありません。');
                }
            }
        }else{
            $player->sendMessage('データが壊れています。');
        }
        return false;
    }

    public function onPlayerTap(PlayerInteractEvent $e)
    {
        //$this->getServer()->getLogger()->info($e->getEventName() . " was Called.");
        $player = $e->getPlayer();
        if($player->isSneaking()) return;
        $block = $e->getBlock();
        /** @var Item $hand */
        $hand = $player->getInventory()->getItemInHand();
        switch ($block->getId()) {
            case Item::fromString('Diamond Block')->getId():
                $this->gacha($player, 'main');
                break;
            case Item::fromString('Gold Block')->getId():
                $this->gacha($player, 'sub');
                break;
            case Item::fromString('Iron Block')->getId():
                $this->gacha($player, 'armor');
                break;

            case Item::fromString('Diamond Ore')->getId():
                $this->setGameInventory($player, $hand);
                break;
            case Item::fromString('Coal Ore')->getId():
                $player->getInventory()->setItemInHand(Item::get(Item::AIR));
                break;

            default:
                return;
                break;
        }
        $e->setCancelled();
    }

    /**
     * ワールド間テレポートした時
     */
    public function onTeleportWorld(EntityLevelChangeEvent $e)
    {
        //Playerなどイベント関連情報を取得
        /** @var Player $player */
        $player = $e->getEntity();
        if (!$player instanceof Player) return;
        
        $target = $e->getTarget();
        $origin = $e->getOrigin();
        if (\in_array($origin->getName(), $this->getConfig()->get('shopworlds', []))) {
            // moving from Shopworld
            foreach ($player->getArmorInventory()->getContents() as $slot => $item) {
                $this->setGameInventory($player, $item, false);
            }
            $this->inventories->setNested($player->getName() . 'inventory.shop', $player->getInventory()->getContents());
            $this->inventories->setNested($player->getName() . 'armor.shop', $player->getArmorInventory()->getContents());
        }

        if (\in_array($target->getName(), $this->getConfig()->get('gameworlds', []))) {
            // moving into GameWorld
            $this->giveItems($player);
        } elseif (\in_array($target->getName(), $this->getConfig()->get('shopworlds', []))) {
            // moving into ShopWorld
            $items = $this->getSavedInventory($player->getName() . 'inventory.shop');
            $player->getInventory()->setContents($items);
            $items = $this->getSavedInventory($player->getName() . 'armor.shop');
            $player->getArmorInventory()->setContents($items);
        } 
        $this->inventories->save();
    }

    /**
     * インベントリを復元
     * @param string $key
     * @param Item[] $items
     * 
     * @return Item[]
     */
    private function getSavedInventory(string $key){

        $items = [];
        foreach ($this->inventories->getNested($key, []) as $key => $item) {
            // if this was saved before server restarting, $item has been serialized.
            if ($item instanceof Item) {
                $items[$key] = $item;
            } elseif (is_string($item)) {
                if (($item = unserialize($item)) instanceof Item) {
                    $items[$key] = $item;
                }
            }
        }
        return $items;
    }

    /**
     * 武器等を振り分け
     * @param Item $item
     */
    private function setGameInventory(Player $player, Item $item, bool $message = true){
        switch ($item->getId()) {
            case Item::fromString('Bow')->getId():
            case Item::fromString('Stone Sword')->getId():
            case Item::fromString('Iron Sword')->getId():
            case Item::fromString('Diamond Sword')->getId():
            case Item::fromString('Snowball')->getId():
                $this->inventories->setNested($player->getName() . '.game.main', $item);
                if ($message)  $player->sendMessage($item->getName() . ' がメイン武器に設定されました。');
                break;

            case Item::fromString('TNT')->getId():
            case Item::fromString('Stained Glass Pane')->getId():
            case Item::fromString('Splash Potion')->getId():
                $this->inventories->setNested($player->getName() . '.game.sub', $item->setCount(1));
                if ($message) $player->sendMessage($item->getName() . ' がサブ武器に設定されました。');
                break;

            case Item::fromString('leather helmet')->getId():
            case Item::fromString('chain helmet')->getId():
            case Item::fromString('iron helmet')->getId():
            case Item::fromString('Diamond helmet')->getId():
                $this->inventories->setNested($player->getName() . '.game.helmet', $item);
                if ($message) $player->sendMessage($item->getName() . ' が装備に設定されました。');
                break;
                
            case Item::fromString('leather chestplate')->getId():
            case Item::fromString('chain chestplate')->getId():
            case Item::fromString('iron chestplate')->getId():
            case Item::fromString('Diamond chestplate')->getId():
            case Item::fromString('Elytra')->getId():
                $this->inventories->setNested($player->getName() . '.game.chestplate', $item);
                if ($message) $player->sendMessage($item->getName() . ' が装備に設定されました。');
                break;
                
            case Item::fromString('leather leggings')->getId():
            case Item::fromString('chain leggings')->getId():
            case Item::fromString('iron leggings')->getId():
            case Item::fromString('Diamond leggings')->getId():
                $this->inventories->setNested($player->getName() . '.game.leggings', $item);
                if ($message) $player->sendMessage($item->getName() . ' が装備に設定されました。');
                break;
                
            case Item::fromString('leather boots')->getId():
            case Item::fromString('chain boots')->getId():
            case Item::fromString('iron boots')->getId():
            case Item::fromString('Diamond boots')->getId():
                $this->inventories->setNested($player->getName() . '.game.boots', $item);
                if ($message) $player->sendMessage($item->getName() . ' が装備に設定されました。');
                break;
            
            default:
                if ($message) $player->sendMessage($item->getName() . ' は登録できませんでした。');
                break;
        }
        $this->inventories->save();
    }

    public function giveItems(Player $player)
    {
        $items = $this->getSavedInventory($player->getName() . '.game');
        $inv = [];
        $player->getArmorInventory()->setContents([]);
        foreach ($items as $slot => $item) {
            $flag = true;
            switch (strtolower($slot)) {
                case 'helmet':
                    $player->getArmorInventory()->setHelmet($item);
                    break;

                case 'chestplate':
                    $player->getArmorInventory()->setChestplate($item);
                    break;

                case 'leggings':
                    $player->getArmorInventory()->setLeggings($item);
                    break;

                case 'boots':
                    $player->getArmorInventory()->setBoots($item);
                    break;

                default:
                    foreach ($this->listeners as $weapon) {
                        if($weapon->weaponId === $item->getId()){
                            $weapon->reload($player, Item::get($weapon->itemId)->setCount($weapon->maxCount), 10, true);
                        }
                    }
                    array_push($inv, $item);
                    break;
            }
        }

        $player->getInventory()->setContents($inv);
        return;
    }

    /**
     * いらねぇ
     * @param Item[]|string[] $items
     * 
     * @return Item[]
     */
    private function unserializeItems(array $items){
        $pditems = [];
        foreach ($items as $item) {
            if ($item instanceof Item) {
                array_push($pditems, $item);
            } elseif (is_string($item)) {
                if (unserialize($item) instanceof Item) {
                    array_push($pditems, unserialize($item));
                }
            }
        }
        return $pditems;
    }

    /** ショップ内での発射禁止 */
    public function onLaunchProjectile(PlayerItemUseEvent $e){
        if(in_array($e->getPlayer()->getLevel()->getName(), $this->getConfig()->get('shopworlds', []))) $e->setCancelled();
        //$e->getPlayer()->sendMessage("ここではアイテムは使えません。" . $e->getPlayer()->getLevel()->getName());
    }

    /** リスポーン時にアイテム配布 */
    public function onRespawn(PlayerRespawnEvent $e){
        $player = $e->getPlayer();
        $this->giveItems($player);
    }
}