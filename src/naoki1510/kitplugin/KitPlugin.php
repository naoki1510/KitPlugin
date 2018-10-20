<?php

namespace naoki1510\kitplugin;

/** @todo remove not to use. */
use naoki1510\kitplugin\EventListener;
use naoki1510\kitplugin\KitPlugin;
use naoki1510\kitplugin\mainweapons\BowWeapon;
use naoki1510\kitplugin\mainweapons\SnowBallWeapon;
use naoki1510\kitplugin\subweapons\Bom;
use naoki1510\kitplugin\subweapons\PotionWeapon;
use naoki1510\kitplugin\subweapons\Shield;
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
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Armor;
use pocketmine\item\Bow;
use pocketmine\item\Item;
use pocketmine\item\Sword;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\level\Explosion;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;


class KitPlugin extends PluginBase implements Listener
{
    /** @var Config */
    public $kit;
    public $playerdata;

    /** @var Weapon[] */
    //private $listeners;

    /** @var string[] */
    private $cue = [];

    public function onEnable()
    {
		// 起動時のメッセージ
        $this->getLogger()->info("§eKitPlugin was loaded.");
        $this->saveDefaultConfig();
		//コンフィグ作成
        $this->playerdata = new Config($this->getDataFolder() . 'PlayerData.yml', Config::YAML);
        $this->saveResource('kit.json');
        $this->kit = new Config($this->getDataFolder() . 'kit.json', Config::JSON);
		// イベントリスナー登録
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
    }

    public function onDisable()
    {
        // $this->playerdata->save();
    }
    
    /* 購入処理系 */

    public function onPlayerTap(PlayerInteractEvent $e)
    {
        $player = $e->getPlayer();
        // スニークしてる時はパス
        if($player->isSneaking()) return;

        $block = $e->getBlock();
        switch ($block->getId()) {
            // 看板のID
            case Block::WALL_SIGN:
            case Block::SIGN_POST:
                $sign = $block->getLevel()->getTile($block->asPosition());
                // 看板の取得に失敗した時
                if (!$sign instanceof Sign) return;
                // 1行目がKitじゃない時
                if(preg_match('/^(§[0-9a-fklmnor])*\[kit\]$/iu', trim($sign->getLine(0))) != 1) return;
                // 看板の文字の再読み込み
                $this->reloadSign($sign);
                // キット名の取得
                preg_match('/^(§[0-9a-fklmnor])*(.*)$/u', trim($sign->getLine(1)), $m);
                $kit = $m[2];
		        // Kit名が存在するか
                if (!$this->kit->exists($kit)){
                    $player->sendMessage('キットが見つかりません');
                    continue;
                }
                // すでにその職の時はパス
                if ($this->playerdata->getNested($player->getName() . '.now', '') === $kit){
                    $this->setKit($player, $kit);
                    continue;
                } 
                // ランク、コストをConfigから取得
                $rank = $this->kit->getNested($kit . '.rank', 0);
                $cost = $this->kit->getNested($kit . '.cost', 0);
                // 購入済みか、もしくはランク０
                if ($this->isPurchased($player, $kit) || $rank === 0){
                    // キット情報の設定
                    $this->setKit($player, $kit);
                    $player->sendMessage($kit . 'になりました');
                }else{
                    // Kit購入
                    $this->buyKit($player, $kit);
                }
                
                break;

            case Block::EMERALD_BLOCK:
                // アイテム付与
                $this->giveItems($player);
                break;

            default:
                return;
                break;
        }
        // ブロック配置の防止
        $e->setCancelled();
    }

    public function onSignChange(SignChangeEvent $e){
        $this->reloadSign($e);
    }

    /** @param SignChangeEvent|Sign $sign */
    public function reloadSign($sign)
    {
        try{
            if (preg_match('/^(§[0-9a-fklmnor])*\[kit\]$/iu', trim($sign->getLine(0))) == 1) {
                preg_match('/^(§[0-9a-fklmnor])*(.*)$/u', trim($sign->getLine(1)), $m);
                $kit = $m[2];
			    // Kit名が存在するか
                if ($this->kit->exists($kit)) {
                    $rank = $this->kit->getNested($kit . '.rank', 0);
                    $cost = $this->kit->getNested($kit . '.cost', 0);
                    $rankcolor = '§' . [7, 6, 'f', 'e', 'b'][$rank];

                    $sign->setLine(0, '§a[Kit]');
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

    /** キット購入 */
    public function buyKit(Player $player, string $kit) : bool{
        if (!$this->kit->exists($kit)) return false;
        $rank = $this->kit->getNested($kit . '.rank', 0);
        $cost = $this->kit->getNested($kit . '.cost', 0);
        // キットの条件を満たしているか
        $required = $this->kit->getNested($kit . '.required', []);
        $lack = [];
        foreach ($required as $kit => $level) {
            if ($level > $this->getLevel($player, $kit)) {
                $lack[$kit] = $level;
            }
        }
        if (!empty($lack)) {
            $player->sendMessage($kit . 'を購入できません。');
            foreach ($lack as $kit => $level) {
                if($level < 2){
                    $player->sendMessage($kit . 'が解放されていません。');
                }else{
                    $player->sendMessage($kit . 'のレベルが' . $level . 'に達していません。');
                }
            }
            return false;
        }
        // お金が足りるか
        if ((EconomyAPI::getInstance()->myMoney($player) ?? 0) < $cost) {
            $player->sendMessage('お金が足りません。');
            return false;
        }
        // キューが空の時
        if (empty($this->cue[$player->getName()])) {
            $this->cue[$player->getName()] = $kit;
            //購入確認フォーム
            $pk = new ModalFormRequestPacket();
            $pk->formId = 229028;
            $form['title'] = $kit . 'を購入しますか？';
            $form['type'] = 'modal';
            $form['content'] = $kit . PHP_EOL . '§6$' . $cost . "\n§rRank§l: " . ['§fNormal', '§6Bronze', '§fSilver', '§eGold', '§bPlatinum'][$rank];
            $form['button1'] = 'Yes';
            $form['button2'] = 'No';
            $pk->formData = json_encode($form);
            $player->dataPacket($pk);
            return true;
        }
        return false;
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
        if ($pk->formId !== 229028) return;
        
        $data = json_decode($pk->formData, true);
        if($data === null) return;
        switch ($data) {
            case 0: 
                $player->sendMessage("購入をキャンセルしました。");
                break;

            case 1:
                $kit = $this->cue[$player->getName()];
                $rank = $this->kit->getNested($kit . '.rank', 0);
                $cost = $this->kit->getNested($kit . '.cost', 0);

                if (EconomyAPI::getInstance()->reduceMoney($player, $cost) === 1) {
                    $player->sendMessage($kit . "を購入しました。");
                    // $purchased = $this->playerdata->getNested($player->getName() . '.level');
                    // array_push($purchased, $kit);
                    // $this->playerdata->setNested($player->getName() . '.level.' . $kit, 1);
                    $this->purchase($player, $kit);
                    $this->setKit($player, $kit);
                } else {
                    $player->sendMessage('お金が足りません。');
                }
                break;
        }
        $this->cue[$player->getName()] = null;
    }

    /**
     * アイテムを与える
     */
    public function giveItems(Player $player, string $kit = null) : bool
    {
        $kit = $kit ?? $this->playerdata->getNested($player->getName() . '.now');
        if (!$this->kit->exists($kit)) return false;

        $data = $this->kit->get($kit);
        $items = [];
        // アイテム
        foreach ($data['items'] as $itemInfo) {
            try{
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
                // 1スタックの量を超える時
                while ($count > $item->getMaxStackSize()) {
                    array_push($items, clone $item->setCount($item->getMaxStackSize()));
                    $count -= $item->getMaxStackSize();
                }
                array_push($items, $item->setCount($count));
            }catch(\InvalidArgumentException $e){
                $this->getLogger()->warning($e->getMessage());
            }
        }
        //アイテムをセット
        $player->getInventory()->setContents($items);
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
                try{
                    $slot = $item->getArmorSlot();
                    $player->getArmorInventory()->setItem($slot, $item);
                }catch(\BadMethodCallException $e){
                    // getArmorSlotに失敗した時
                    $this->getLogger()->warning($e->getMessage());
                }
            } catch (\InvalidArgumentException $e) {
                // アイテムの取得に失敗した時
                $this->getLogger()->warning($e->getMessage());
            }
        }
        // エフェクト
        $player->removeAllEffects();
        foreach ($data['effects'] ?? [] as $slot => $effectInfo) {
            $effect = Effect::getEffect($effectInfo['id'] ?? 1);
            $player->addEffect(new EffectInstance($effect, $effectInfo['duration'] ?? 2147483647, $effectInfo['amplification'] ?? $effectInfo['amp'] ?? 0, $effectInfo['visible'] ?? false));
        }
        return true;
    }

    /** 
     * キットを設定 
     */
    public function setKit(Player $player, string $kit) : bool
    {
        if (!$this->kit->exists($kit)) return false;
        $this->playerdata->setNested($player->getName() . '.now', $kit);
        $this->playerdata->save();
        // アイテム付与
        return $this->giveItems($player, $kit);
    }
    
    /**
     * 購入されているか
     */
    public function isPurchased(Player $player, string $kit) {
        return !empty($this->getLevel($player, $kit));
    }
    
    /** 
     * 購入処理
     */
    public function purchase(Player $player, string $kit) {
        if (!$this->isPurchased($player, $kit)) {
            $this->playerdata->setNested($player->getName() . '.level.' . $kit, 1);
        }
    }
    
    /** 
     * レベルを取得 
     * @return int|null
     */
    public function getLevel(Player $player, string $kit) : int{
        $purchased = $this->playerdata->getNested($player->getName() . '.level', []);
        foreach ($purchased as $pkit => $level) {
            if ($kit === $pkit) {
                return $level ?: null;
            }
        }
        return null;
    }
    
    /** レベルアップ */
    public function addLevel(Player $player, string $kit = null, int $level = 1) {
        $kit = $kit ?? $this->playerdata->getNested($player->getName() . '.now');
        if (!$this->isPurchased($player, $kit)) return false;
        
        $lv = $this->getLevel($player, $kit) + $level;
        $this->setLevel($player, $kit, $lv);
        return true;
    }
    
    /** レベルを設定 */
    public function setLevel(Player $player, ?string $kit = null, int $level) {
        $kit = $kit ?? $this->playerdata->getNested($player->getName() . '.now');
        $this->playerdata->setNested($player->getName() . '.level.' . $kit, $level);
    }
    
    /** 経験値を取得 */
    public function getExp(Player $player, ?string $kit = null) : int{
        $kit = $kit ?? $this->playerdata->getNested($player->getName() . '.now');
        $this->playerdata->getNested($player->getName() . '.exp.' . $kit, 0);
    }
    
    /** 経験値を設定 **/
    public function setExp(Player $player, ?string $kit = null, int $exp = 0) {
        $kit = $kit ?? $this->playerdata->getNested($player->getName() . '.now');
        $lv = $this->playerdata->getNested($player->getName() . '.level.' . $kit, 0);
        $need = 1000 + $lv * 200;
        if ($need < $exp) {
            $this->addLevel($player);
            $this->setExp($player, $kit, $lv - $need);
            
        }
    }
    
    /** 経験値を追加 */
    public function addExp(Player $player, ?string $kit = null, int $exp = 0) {
        $kit = $kit ?? $this->playerdata->getNested($player->getName() . '.now');
        $exp = $this->playerdata->getNested($player->getName() . '.exp.' . $kit, 0) + $exp;
        $this->setExp($exp);
    }

    /** ショップ内での発射禁止 */
    public function onLaunchProjectile(PlayerItemUseEvent $e){
        if(!in_array($e->getPlayer()->getLevel()->getName(), $this->getConfig()->get('shopworlds', []))) return;
        $e->setCancelled();
        $e->getPlayer()->sendMessage("ここではアイテムは使えません。" . $e->getPlayer()->getLevel()->getName());
    }

    /** ショップでのドロップ禁止 */
    public function onDrop(PlayerDropItemEvent $e){
        if (in_array($e->getPlayer()->getLevel()->getName(), $this->getConfig()->get('shopworlds', []))) $e->setCancelled();
    }

    /** リスポーン時にアイテム配布 */
    public function onRespawn(PlayerRespawnEvent $e){
        $player = $e->getPlayer();
        $this->giveItems($player);
    }

    /** ワールド間テレポートした時 */
    public function onTeleportWorld(EntityLevelChangeEvent $e)
    {
        //Playerなどイベント関連情報を取得
        /** @var Player $player */
        $player = $e->getEntity();
        if (!$player instanceof Player) return;

        $target = $e->getTarget();
        $origin = $e->getOrigin();
        
        if (\in_array($target->getName(), $this->getConfig()->get('gameworlds', []))) {
            // moving into GameWorld
            $this->giveItems($player);
        }
    }
    
    /** 経験値加算 */
    public function onDeath(PlayerDeathEvent $e) {
        $victim = $e->getPlayer();
        $lastDamage = $victim->getLastDamageCause();
        if ($lastDamage instanceof EntityDamageByEntityEvent) {
            $killer = $lastDamage->getDamager();
            $this->addExp($killer, null, 200);
        }
    }
}