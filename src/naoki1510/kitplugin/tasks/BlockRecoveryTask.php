<?php

namespace naoki1510\kitplugin\tasks;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;

class BlockRecoveryTask extends Task
{
    /** @var Position[] */
    public $pos;

    /** @var Config */
    private $protectedBlock;

    /** @var Level */
    public $level;

    public function __construct(Array $recoveryPos, Level $level, Config $pb)
    {
        $this->pos = $recoveryPos;
        $this->level = $level;
        $this->protectedBlock = $pb;
    }

    public function onRun(Int $currentTick)
    {
        foreach ($this->pos as $pos) {
            if($this->protectedBlock->exists(implode(":", [$pos->x, $pos->y, $pos->z]))){
                if($this->protectedBlock->get(implode(":", [$pos->x, $pos->y, $pos->z])) <= $currentTick){
                    $this->level->setBlock($pos, Item::fromString(Block::AIR)->getBlock());
                }
            }else{
                $this->level->setBlock($pos, Item::fromString(Block::AIR)->getBlock());
            }
        }
    }


}
