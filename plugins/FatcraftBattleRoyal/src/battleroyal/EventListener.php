<?php

namespace battleroyal;

use fatcraft\loadbalancer\LoadBalancer;
use fatutils\game\GameManager;
use fatutils\players\PlayersManager;
use fatutils\scores\ScoresManager;
use fatutils\tools\schedulers\DelayedExec;
use fatutils\tools\Sidebar;
use fatutils\tools\WorldUtils;
use fatutils\spawns\SpawnManager;
use battleroyal\BattleRoyal;
use libasynql\result\MysqlResult;
use pocketmine\entity\Effect;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;

class EventListener implements Listener
{

    public function __construct()
    {
    }

    public function playerSneakEvent(PlayerToggleSneakEvent $e)
    {
        $player = $e->getPlayer();

        if ($player->getInventory()->getItem($player->getInventory()->getHeldItemIndex())->getId() == ItemIds::BOW
            && !$player->isSneaking())
            $player->addEffect(Effect::getEffect(Effect::SLOWNESS)->setAmplifier(5)->setDuration(INT32_MAX));
        else {
            $player->removeEffect(Effect::SLOWNESS);
        }
    }

    /**
     * @param PlayerDeathEvent $e
     */
    public function playerDeathEvent(PlayerDeathEvent $e)
    {
        $p = $e->getEntity();

        if (!GameManager::getInstance()->isWaiting()) {
            PlayersManager::getInstance()->getFatPlayer($p)->setOutOfGame(true);

            WorldUtils::addStrike($p->getLocation());
            $l_PlayerLeft = PlayersManager::getInstance()->getInGamePlayerLeft();

            ScoresManager::getInstance()->giveRewardToPlayer($p->getUniqueId(), ((GameManager::getInstance()->getPlayerNbrAtStart() - $l_PlayerLeft) / GameManager::getInstance()->getPlayerNbrAtStart()));

            foreach (BattleRoyal::getInstance()->getServer()->getOnlinePlayers() as $l_Player) {
                $l_Player->sendMessage($e->getDeathMessage());
                if ($l_PlayerLeft > 1)
                    $l_Player->sendMessage("Il reste " . TextFormat::YELLOW . PlayersManager::getInstance()->getInGamePlayerLeft() . TextFormat::RESET . " survivants !");
            }

            if ($l_PlayerLeft <= 1 && !GameManager::getInstance()->isGameFinished())
                BattleRoyal::getInstance()->endGame();

            $e->getPlayer()->sendMessage("You died as pos " . (BattleRoyal::getInstance()->maxPlayer - PlayersManager::getInstance()->getInGamePlayerLeft() + 1) . ".\n");
            $p->setGamemode(3);

            Sidebar::getInstance()->update();
        }
    }

//    /**
//     * @param EntityDamageEvent $e
//     */
//    public function onEntityDamageEvent(EntityDamageEvent $e)
//    {
//        if (GameManager::getInstance()->getSecondSinceStart() < 30)
//            $e->setCancelled(true);
//    }

    /**
     * @param PlayerJoinEvent $e
     */
    public function onPlayerJoin(PlayerJoinEvent $e)
    {
        $p_Player = $e->getPlayer();

        if (GameManager::getInstance()->isPlaying()) {
            if ($p_Player->isOp()) {
                $p_Player->setGamemode(3);
                PlayersManager::getInstance()->getFatPlayer($p_Player)->setOutOfGame();
                return;
            } else {
                LoadBalancer::getInstance()->balancePlayer($p_Player, LoadBalancer::TEMPLATE_TYPE_LOBBY);
                return;
            }
        }

        $p = $e->getPlayer();
        $p->setGamemode(Player::ADVENTURE);
        $p->getInventory()->clearAll();

        $task = new GiveSledgeHammer(BattleRoyal::getInstance(), $p->getUniqueId()->toString());
        BattleRoyal::getInstance()->getServer()->getScheduler()->scheduleDelayedTask($task, 1);

        BattleRoyal::getInstance()->handlePlayerConnection($p);
    }

    public function onPlayerRespawn(PlayerRespawnEvent $p_Event)
    {
        if (GameManager::getInstance()->isWaiting()) {
            $spawn = SpawnManager::getInstance()->getRandomEmptySpawn();
            $position = \pocketmine\level\Position::fromObject($spawn->getLocation()->add(-0.5, 0.1, -0.5), $spawn->getLocation()->getLevel());
            $p_Event->setRespawnPosition($position);
            BattleRoyal::getInstance()->getLogger()->info("Player " . $p_Event->getPlayer()->getName() . " respawn at " . $position->__toString());
        } else {
            new DelayedExec(function () use ($p_Event) {
                $p_Event->getPlayer()->setGamemode(3);
                $p_Event->getPlayer()->teleport(BattleRoyal::getInstance()->getCurrentCenterLoc());
            }, 5);
        }
    }

    public function onBlockBreakEvent(BlockBreakEvent $e)
    {
        $array = [];
        $e->setDrops($array);
    }

    public function onPlayerHit(EntityDamageEvent $e)
    {
        if (GameManager::getInstance()->isWaiting() && $e->getEntity() instanceof Player)
            $e->setCancelled();
    }

    public function onChunkUnload(\pocketmine\event\level\ChunkUnloadEvent $p_event)
    {
        $p_event->setCancelled();
    }

    public function onPlayerExhaust(PlayerExhaustEvent $p_Event)
    {
        if (GameManager::getInstance()->isWaiting())
            $p_Event->setCancelled(true);
    }
}


class GiveSledgeHammer extends PluginTask
{
    private $m_uuid = "";
    public function __construct(Plugin $p_owner, String $p_uuid)
    {
        parent::__construct($p_owner);
        $this->m_uuid = $p_uuid;
    }

    public function onRun(int $currentTick)
    {
        $result = MysqlResult::executeQuery(LoadBalancer::getInstance()->connectMainThreadMysql(),
            "SELECT * FROM scores WHERE player = ? && `position` = 100 && serverType = 'battleRoyal'", [
                ["s", $this->m_uuid]
            ]);
        if (($result instanceof \libasynql\result\MysqlSelectResult) and count($result->rows) >= 1)
            BattleRoyal::getInstance()->giveRightPickaxe($this->m_uuid, 1);
        else
            BattleRoyal::getInstance()->giveRightPickaxe($this->m_uuid, 0);
    }

    public function cancel() {
        $this->getHandler()->cancel();
    }
}