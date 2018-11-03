<?php

namespace naoki1510\kitplugin;

/** @todo remove not to use. */
use naoki1510\kitplugin\EventListener;
use naoki1510\kitplugin\KitPlugin;
use naoki1510\kitplugin\tasks\BlockRecoveryTask;
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\Listener;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Armor;
use pocketmine\item\Bow;
use pocketmine\item\Item;
use pocketmine\item\Sword;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\lang\Language;
use pocketmine\level\Explosion;
use pocketmine\level\Level;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;


class KitPlugin extends PluginBase implements Listener
{
    public const FORM_BUY = INT32_MAX - 1511;
    public const FORM_CHANGE = INT32_MAX - 1512;
    public const FORM_CANT_BUY = INT32_MAX - 1513;

    /** @var Config */
    public $kit;
    public $data;

    /** @var LevelAPI */
    public $levelapi;

    /** @var string[] */
    private $cue = [];

    public function onEnable()
    {
		// 起動時のメッセージ
        $this->getLogger()->info("§eKitPlugin was loaded.");
        $this->saveDefaultConfig();
		//コンフィグ作成
        $this->data = new Config($this->getDataFolder() . 'data.yml', Config::YAML);
        $this->saveResource('kit.json');
        $this->kit = new Config($this->getDataFolder() . 'kit.json', Config::JSON);
        if(empty($this->kit->getAll())){
            $this->getLogger()->warning('kit is empty. Is there any error in the kit.json file?');
        }
		// イベントリスナー登録
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        // EventListenerは武器関係
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->levelapi = new LevelAPI($this);
    }

    public function onDisable()
    {
        //$this->data->save();
    }
    
    /* 購入処理系 */

    public function onPlayerTap(PlayerInteractEvent $e)
    {
        $player = $e->getPlayer();
        // スニークしてる時は無効
        if($player->isSneaking()) return;
        // ブロック取得
        $block = $e->getBlock();
        switch ($block->getId()) {
            // 看板
            case Block::WALL_SIGN:
            case Block::SIGN_POST:
                $sign = $block->getLevel()->getTile($block->asPosition());
                if (!$sign instanceof Sign) return;
                if(preg_match('/^.*\[.*kit.*\].*$/iu', trim($sign->getLine(0))) != 1) return;
                $this->reloadSign($sign);
                preg_match('/^(§[0-9a-fklmnor])*(.*)$/u', trim($sign->getLine(1)), $m);
                $kit = $m[2];
                if (!$this->kit->exists($kit)){
                    $player->sendMessage('キットが見つかりません。');
                    continue;
                }
                if(empty($this->cue[$player->getName()])){
                    $this->cue[$player->getName()] = $kit;
                    $this->sendForm($player, $kit);
                }
                break;
            //エメラルドブロック
            case Block::EMERALD_BLOCK:
                // アイテム付与
                $this->giveItems($player);
                break;

            default:
                // @todo エメラルドで回復
                $handItem = $player->getInventory()->getItemInHand();
                switch ($handItem->getId()) {
                    case Item::EMERALD:

                        break;
                }
                return;
                break;
        }
        // ブロック配置の防止
        $e->setCancelled();
        
    }

    public function onSignChange(SignChangeEvent $e){
        $this->reloadSign($e);
    }

    /** 
     * @param SignChangeEvent|Sign $sign 
     * 
     * @todo メッセージを変更可能に
     */
    public function reloadSign($sign)
    {
        try{
            if (preg_match('/^.*\[.*kit.*\].*$/iu', trim($sign->getLine(0))) == 1) {
                preg_match('/^(§[0-9a-fklmnor])*(.*)$/u', trim($sign->getLine(1)), $m);
                $kit = $m[2];
			    // Kit名が存在するか
                if ($this->kit->exists($kit)) {
                    $rank = $this->kit->getNested($kit . '.rank', 0);
                    $cost = $this->kit->getNested($kit . '.cost', 0);
                    $rankcolor = '§' . [7, 6, 'f', 'e', 'b'][$rank];
                    // lineの変更
                    $sign->setLine(0, '§l[§aKit§r§l]');
                    $sign->setLine(1, '§l' . $rankcolor . $kit);
                    $sign->setLine(2, '§c$' . $cost);
                    $sign->setLine(3, $rankcolor . ['Normal', 'Bronze', 'Silver', 'Gold', 'Platinum'][$rank]);
                }
            }
        }catch(\BadMethodCallException $e){
            // $signから文字を変更できなかった時
            $this->getLogger()->warning($e->getMessage());
        }
        
    }

    /** 
     * パケット受信
     * 今回はフォーム
     */
    public function onRecievePacket(DataPacketReceiveEvent $ev)
    {
        $pk = $ev->getPacket();
        $player = $ev->getPlayer();
        
        if (!$pk instanceof ModalFormResponsePacket) return;
        
        $data = json_decode($pk->formData, true);
        switch ($pk->formId) {
            case self::FORM_BUY:
                if($data === null) continue;
                if($data === 1) continue;
                $kit = $this->cue[$player->getName()];
                if($this->isPurchased($player, $kit)) continue;
                $rank = $this->kit->getNested($kit . '.rank', 0);
                $cost = $this->kit->getNested($kit . '.cost', 0);

                if (EconomyAPI::getInstance()->reduceMoney($player, $cost) === 1) {
                    if ($this->kit->exists($kit))
                    $this->setKit($player, $kit);
                    $this->purchase($player, $kit);
                    $player->sendMessage($kit . "を購入しました。");
                } else {
                    $player->sendMessage('お金が足りません。');
                }
                break;

            case self::FORM_CHANGE:
                if ($data === null) continue;
                if ($data === 1) continue;
                $kit = $this->cue[$player->getName()];
                if($this->kit->exists($kit))
                $this->setKit($player, $kit);
                break;

            case self::FORM_CANT_BUY:
            break;

            default:
                return;
                break;
        }

        //var_dump(isset($kit) ? $kit : null);
    }

    /**
     * アイテムを与える
     */
    public function setItems(Player $player, string $kit = null) : bool
    {
        $kit = $kit ?? $this->getKit($player);
        if (!$this->kit->exists($kit)) return false;

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->removeAllEffects();
        return $this->giveItems($player, $kit);
    }

    /**
     * @todo コード最適化
     */
    public function giveItems(Player $player, string $kit = null) : bool
    {
        $kit = $kit ?? $this->getKit($player);
        if (!$this->kit->exists($kit)) return false;

        $data = $this->kit->get($kit);
        $items = [];
        // アイテム
        foreach ($data['items'] as $itemInfo) {
            try {
                $item = Item::fromString($itemInfo['name']);
                $count = $itemInfo['count'] ?? 1;

                /** @var Item $item */
                if (isset($itemInfo['enchantments'])) {
                    $enchantments = $itemInfo['enchantments'];
                    foreach ($enchantments as $enchdata) {
                        $ench = Enchantment::getEnchantment($enchdata['id'] ?? 0);
                        $item->addEnchantment(new EnchantmentInstance($ench, $enchdata['level'] ?? 1));
                    }
                }

                foreach ($player->getInventory()->getContents() as $invitem) {
                    if ($invitem->getId() === $item->getId() && $invitem->getDamage() === $item->getDamage()) {
                        $count -= $invitem->getCount();
                    }
                }

                while ($count > 0) {
                    $player->getInventory()->addItem(clone $item->setCount(min($item->getMaxStackSize(), $count)));
                    $count -= $item->getMaxStackSize();
                }
            } catch (\InvalidArgumentException $e) {
                $this->getLogger()->warning($e->getMessage());
            }
        }
        // 装備
        foreach ($data['armor'] as $slot => $armorInfo) {
            try {
                $item = Item::fromString($armorInfo['name']);
                /** @var Item $item */
                if (isset($armorInfo['enchantment'])) {
                    $enchantments = $armorInfo['enchantment'];
                    foreach ($enchantments as $enchdata) {
                        $ench = Enchantment::getEnchantment($enchdata['id'] ?? 0);
                        $item->addEnchantment(new EnchantmentInstance($ench, $enchdata['level'] ?? 1));
                    }
                }
                try {
                    $slot = $item->getArmorSlot();
                    if ($player->getArmorInventory()->getItem($slot) instanceof Armor && $item instanceof Armor) {
                        $armor = $player->getArmorInventory()->getItem($slot);
                        if ($armor->getEnchantability() < $item->getEnchantability()) {
                            continue;
                        }
                    }
                    $player->getArmorInventory()->setItem($slot, $item);
                } catch (\BadMethodCallException $e) {
                    // getArmorSlotに失敗した時
                    $this->getLogger()->warning($e->getMessage());
                }
            } catch (\InvalidArgumentException $e) {
                // アイテムの取得に失敗した時
                $this->getLogger()->warning($e->getMessage());
            }
        }
        // エフェクト
        foreach ($data['effects'] ?? [] as $effectInfo) {
            $effect = Effect::getEffect($effectInfo['id'] ?? 1);
            $player->addEffect(new EffectInstance($effect, $effectInfo['duration'] ?? 2147483647, $effectInfo['amplification'] ?? $effectInfo['amp'] ?? 0, $effectInfo['visible'] ?? false));
        }
        return true;
    }

    public function takeItems(Player $player, string $kit = null) : bool
    {
        $kit = $kit ?? $this->getKit($player);
        if (!$this->kit->exists($kit)) return false;

        $data = $this->kit->get($kit);
        $items = [];
        // アイテム
        foreach ($data['items'] as $itemInfo) {
            try {
                $item = Item::fromString($itemInfo['name']);
                $count = $itemInfo['count'] ?? 1;

                /** @var Item $item */
                if (isset($itemInfo['enchantments'])) {
                    $enchantments = $itemInfo['enchantments'];
                    foreach ($enchantments as $enchdata) {
                        $ench = Enchantment::getEnchantment($enchdata['id'] ?? 0);
                        $item->addEnchantment(new EnchantmentInstance($ench, $enchdata['level'] ?? 1));
                    }
                }
                $player->getInventory()->removeItem($item->setCount($count));

                continue;
                // このしたいらない
                foreach ($player->getInventory()->getContents() as $slot => $invitem) {
                    if ($item->equals($invitem)) {
                        if($count >= $invitem->getCount()){
                            $player->getInventory()->removeItem($slot);
                            $count -= $invitem->getCount();
                        }else{
                            $player->getInventory()->setItem($slot, $item->setCount($invitem->getCount() - $count));
                            $count = 0;
                        }
                    }
                }
            } catch (\InvalidArgumentException $e) {
                $this->getLogger()->warning($e->getMessage());
            }
        }
        // 装備
        foreach ($data['armor'] as $armorInfo) {
            try {
                $item = Item::fromString($armorInfo['name']);
                /** @var Item $item */
                if (isset($armorInfo['enchantment'])) {
                    $enchantments = $armorInfo['enchantment'];
                    foreach ($enchantments as $enchdata) {
                        $ench = Enchantment::getEnchantment($enchdata['id'] ?? 0);
                        $item->addEnchantment(new EnchantmentInstance($ench, $enchdata['level'] ?? 1));
                    }
                }
                try {
                    $slot = $item->getArmorSlot();
                    $player->getArmorInventory()->removeItem($player->getArmorInventory()->getItem($slot));
                    
                } catch (\BadMethodCallException $e) {
                    // getArmorSlotに失敗した時
                    $this->getLogger()->warning($e->getMessage());
                }
            } catch (\InvalidArgumentException $e) {
                // アイテムの取得に失敗した時
                $this->getLogger()->warning($e->getMessage());
            }
        }
        // エフェクト
        foreach ($data['effects'] ?? [] as $effectInfo) {
            $player->removeEffect($effectInfo['id'] ?? 1);
        }
        return true;
    }

    /**
     * @todo custom_formで使える形にする
     */
    public function sendForm(Player $player, string $kit)
    {
        // 買えるかどうかのフラグ
        $canBuy = true;
        $info[] = 'Kit : ' . TextFormat::BOLD . TextFormat::AQUA . $kit;
        if(EconomyAPI::getInstance()->myMoney($player) < $this->getCost($kit)){
            $title = 'お金が足りません';
            $info[] = 'Cost: ' . TextFormat::RED . EconomyAPI::getInstance()->getMonetaryUnit() . $this->getCost($kit) . TextFormat::RESET . 
            ' (you  have: ' . TextFormat::RED . EconomyAPI::getInstance()->getMonetaryUnit() . EconomyAPI::getInstance()->myMoney($player) . TextFormat::RESET . ')';
            $canBuy = false;
        }else{
            $info[] = 'Cost: ' . TextFormat::AQUA . EconomyAPI::getInstance()->getMonetaryUnit() . $this->getCost($kit) . TextFormat::RESET .
            ' (you have: ' . TextFormat::AQUA . EconomyAPI::getInstance()->getMonetaryUnit() . EconomyAPI::getInstance()->myMoney($player) . TextFormat::RESET . ')';
        }
        $info[] = 'Rank : ' . $this->getRank($kit, 'string');
        $info[] = '--- ' . TextFormat::GREEN . 'Items' . TextFormat::RESET . ' ---';
        foreach ($this->kit->getNested($kit . '.items', []) as $item) {
            $info[] = Item::fromString($item['name'])->getName() . ' : ' . TextFormat::AQUA . ($item['count'] ?? 0);
            if (isset($item['enchantments'])) {
                foreach ($item['enchantments'] as $enchant) {
                    $info[] = "  " . Enchantment::getEnchantment($enchant['id'] ?? 0)->getName() . ': ' . TextFormat::AQUA . $enchant['level'] ?? 1;
                    //var_dump((new Language('eng')));
                }
            }
        }
        $info[] = '--- ' . TextFormat::GREEN . 'Armor' . TextFormat::RESET . ' ---';
        foreach ($this->kit->getNested($kit . '.armor', []) as $slot => $armor) {
            $info[] = $slot . ': ' . TextFormat::AQUA . Item::fromString($armor['name'])->getName();
            if (isset($armor['enchantments'])) {
                foreach ($armor['enchantments'] as $enchant) {
                    $info[] = "  " . Enchantment::getEnchantment($enchant['id'] ?? 0)->getName() . ': ' . TextFormat::AQUA . $enchant['level'] ?? 1;
                }
            }
        }
        if ($this->kit->getNested($kit . '.effects', null)) {
            $info[] = '--- ' . TextFormat::GREEN . 'Effects' . TextFormat::RESET . ' ---';
            foreach ($this->kit->getNested($kit . '.effects', []) as $effect) {
                $info[] = Effect::getEffect($effect['id'])->getName() . ' : ' . TextFormat::AQUA . ($effect['amplification'] ?? $effect['amp'] ?? 0) + 1;
            }
        } 
        if ($this->kit->getNested($kit . '.required', null)) {
            $info[] = '--- ' . TextFormat::GREEN . 'Required' . TextFormat::RESET . ' ---';
            foreach ($this->kit->getNested($kit . '.required', []) as $kitname => $level) {
                if ($level > $this->levelapi->getLevel($player, $kitname)) {
                    $info[] = $kitname . TextFormat::RESET . ' : ' . TextFormat::RED . $level . TextFormat::RESET . 
                    ' (now: ' . TextFormat::RED . $this->levelapi->getLevel($player, $kitname) . TextFormat::RESET . ')';
                    $canBuy = false;
                    $title = 'レベルが足りません';
                } else {
                    $info[] = $kitname . TextFormat::RESET . ' : ' . TextFormat::AQUA . $level . TextFormat::RESET .
                    ' (now: ' . TextFormat::AQUA . $this->levelapi->getLevel($player, $kitname) . TextFormat::RESET . ')';
                }
            }
        }
        $pk = new ModalFormRequestPacket();
        $form['type'] = 'form';
        $form['content'] = implode(TextFormat::RESET . PHP_EOL, $info). PHP_EOL . PHP_EOL;
        if($this->isPurchased($player, $kit) or $this->isTryWorld($player->getLevel())){
            $pk->formId = self::FORM_CHANGE;
            $title = 'Kitを変更しますか？';
            $form['buttons'][] = ['text' => 'Change'];
            $form['buttons'][] = ['text' => 'Cancel'];
        } elseif ($canBuy) {
            $pk->formId = self::FORM_BUY;
            $form['buttons'][] = ['text' => 'Buy'];
            $form['buttons'][] = ['text' => 'Cancel'];
        } else {
            $pk->formId = self::FORM_CANT_BUY;
            $form['buttons'][] = ['text' => 'OK'];
        }
        $form['title'] = TextFormat::RED . TextFormat::BOLD . (isset($title) ? $title : $kit . 'を購入しますか？');
        $pk->formData = json_encode($form);
        //var_dump($pk->formData);
        $player->sendDataPacket($pk);
        //$player->sendMessage(Enchantment::getEnchantment(1)->getName());
    }

    /** 
     * キットを設定 
     */
    public function setKit(Player $player, string $kit) : void
    {
        if (!$this->kit->exists($kit)) return;
        $this->takeItems($player, $this->getKit($player));
        if($this->isTryWorld($player->getLevel())){
            $this->data->setNested($player->getName() . '.try', $kit);
        }else{
            $this->data->setNested($player->getName() . '.now', $kit);
        }
        $this->data->save();
        $this->levelapi->sendExp($player, $kit);
        $this->giveItems($player);
    }

    /**
     * 購入されているか
     */
    public function isPurchased(Player $player, string $kit) : bool
    {
        return !empty($this->data->getNested($player->getName() . '.' . $kit));
    }

    /** 
     * 購入処理
     */
    public function purchase(Player $player, string $kit)
    {
        if (!$this->isPurchased($player, $kit)) {
            $this->data->setNested($player->getName() . '.' . $kit, ['level' => 1, 'exp' => 0]);
        }
    }

    public function getKit(Player $player)
    {
        if($this->isTryWorld($player->getLevel())){
            return $this->data->getNested($player->getName() . '.try');
        }else{
            return $this->data->getNested($player->getName() . '.now');
        }
    }

    public function getCost(string $kit) : int
    {
        return intval($this->kit->getNested($kit . '.cost', 0));
    }

    /** 
     * @param string $type string|int
     */
    public function getRank(string $kit, string $type = 'int') : string
    {
        $rank = $this->kit->getNested($kit . '.rank', 0);
        if (strtolower($type) === 'int') return $rank;
        return [TextFormat::GRAY, TextFormat::GOLD, TextFormat::WHITE, TextFormat::YELLOW, TextFormat::AQUA][$rank] . ['Normal', 'Bronze', 'Silver', 'Gold', 'Platinum'][$rank];
    }

    public function isTryWorld(Level $level) : bool{
        return in_array($level->getName(), $this->getConfig()->get('world_try', ['ffa']));
    }

    /** @todo EventListenerに移動 */
    /** ショップ内での発射禁止 */
    public function onUseItem(PlayerItemUseEvent $e){
        if(!in_array($e->getPlayer()->getLevel()->getName(), $this->getConfig()->get('shopworlds', []))) return;
        $e->setCancelled();
        //$e->getPlayer()->sendMessage("ここではアイテムは使えません。" . $e->getPlayer()->getLevel()->getName());
    }

    /** ショップでのドロップ禁止 */
    public function onDrop(PlayerDropItemEvent $e){
        if (in_array($e->getPlayer()->getLevel()->getName(), $this->getConfig()->get('shopworlds', []))) $e->setCancelled();
    }

    /** リスポーン時にアイテム配布 */
    public function onRespawn(PlayerRespawnEvent $e){
        $player = $e->getPlayer();
        $this->setItems($player);
        $this->levelapi->sendExp($player);
    }

    public function onMove(PlayerMoveEvent $e){
        if(!empty($this->cue[$e->getPlayer()->getName()])){
            $this->cue[$e->getPlayer()->getName()] = null;
        }
    }
    
    /** 経験値加算 */
    public function onDeath(PlayerDeathEvent $e) {
        $e->setKeepExperience(true);
        $victim = $e->getPlayer();
        $lastDamage = $victim->getLastDamageCause();
        if ($lastDamage instanceof EntityDamageByEntityEvent) {
            $killer = $lastDamage->getDamager();
            if($killer instanceof Player){
                $this->addExp($killer, null, 200);
            }
        }
    }

    /**
     * 互換性維持
     */
    public function addExp(Player $player, ? string $kit = null, int $exp = 0){
        if($this->isTryWorld($player->getLevel())) return;
        $this->levelapi->addExp($player, $kit ?? $this->getKit($player), $exp);
    }
}