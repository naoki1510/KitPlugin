<?php

namespace naoki1510\kitplugin;

use naoki1510\kitplugin\KitPlugin;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class LevelAPI{
    /** @var KitPlugin */
    public $plugin;

    /** @var Config */
    public $data;

    public  function __construct(KitPlugin $plugin) {
        $this->plugin = $plugin;
        $this->data = new Config($plugin->getDataFolder() . 'data.yml', Config::YAML);
    }

    /** 
     * レベルを取得 
     * @return int|null
     */
    public function getLevel(Player $player, ? string $kit = null) : int
    {
        $kit = $kit ?? $this->plugin->getKit($player);
        return intval($this->data->getNested($player->getName() . '.' . $kit . '.level', 0));
    }

    /** レベルアップ */
    public function addLevel(Player $player, string $kit, int $level = 1)
    {
        $lv = $this->getLevel($player, $kit) + $level;
        $this->setLevel($player, $kit, $lv);
        $player->sendMessage('[KitPlugin] ' . TextFormat::BOLD . $kit . 'のレベルが' . $lv . 'に上がりました!');
        return true;
    }

    /** レベルを設定 */
    public function setLevel(Player $player, string $kit, int $level)
    {
        $this->data->setNested($player->getName() . '.' . $kit . '.level', $level);
        $this->sendExp($player);
        $this->data->save();
    }

    /** 経験値を取得 */
    public function getExp(Player $player, ? string $kit = null) : int
    {
        $kit = $kit ?? $this->plugin->getKit($player);
        return $this->data->getNested($player->getName() . '.' . $kit . '.exp', 0);
    }

    /** 経験値を設定 */
    public function setExp(Player $player, string $kit, int $exp = 0)
    {
        $lv = $this->getLevel($player, $kit);
        $need = 100 + $lv * 300;
        $player->sendMessage('[KitPlugin ]' . TextFormat::BOLD . 'あと' . max($need - $exp, 0) . 'で次のレベルです。');
        $this->data->setNested($player->getName() . '.' . $kit . '.exp', $exp);
        if ($need <= $exp) {
            $this->addLevel($player, $kit);
            $this->setExp($player, $kit, $exp - $need);
        }
        $this->sendExp($player);
        $this->data->save();
    }

    /** 経験値を追加 */
    public function addExp(Player $player, ? string $kit = null, int $exp = 0)
    {
        $kit = $kit ?? $this->plugin->getKit($player);
        $player->sendMessage(TextFormat::BOLD . '[KitPlugin] ' . TextFormat::BOLD . '経験値を' . $exp . 'ゲットしました。');
        $exp = $this->getExp($player, $kit) + $exp;
        $this->setExp($player, $kit, $exp);
    }

    public function sendExp(Player $player)
    {
        $kit = $this->plugin->getKit($player);
        $lv = $this->getLevel($player, $kit);
        $player->setXpLevel($lv);
        $exp = $this->getExp($player, $kit);
        $need = 100 + $lv * 300;
        $player->setXpProgress(max(min($exp / $need, 1), 0));
    }
}