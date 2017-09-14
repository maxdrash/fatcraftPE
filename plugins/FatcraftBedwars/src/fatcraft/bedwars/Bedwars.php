<?php

namespace fatcraft\bedwars;

use fatcraft\loadbalancer\LoadBalancer;
use fatutils\loot\ChestsManager;
use fatutils\FatUtils;
use fatutils\players\FatPlayer;
use fatutils\players\PlayersManager;
use fatutils\tools\bossBarAPI\BossBarAPI;
use fatutils\tools\Sidebar;
use fatutils\tools\Timer;
use fatutils\tools\WorldUtils;
use fatutils\game\GameManager;
use fatutils\spawns\SpawnManager;
use fatutils\tools\MathUtils;
use fatutils\tools\BossbarTimer;
use pocketmine\entity\Effect;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\level\Location;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class Bedwars extends PluginBase
{
    private $m_BedwarsConfig;
    private static $m_Instance;
    private $m_WaitingTimer;
    private $m_PlayTimer;

    public static function getInstance(): Bedwars
    {
        return self::$m_Instance;
    }

    public function onLoad()
    {
        self::$m_Instance = $this;
    }

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);

        FatUtils::getInstance()->setTemplateConfig($this->getConfig());
        $this->m_BedwarsConfig = new BedwarsConfig($this->getConfig());
        $this->initialize();
    }

    private function initialize()
    {
        SpawnManager::getInstance()->blockSpawns();
        LoadBalancer::getInstance()->setServerState(LoadBalancer::SERVER_STATE_OPEN);
        PlayersManager::getInstance()->displayHealth();
        WorldUtils::stopWorldsTime();

        Sidebar::getInstance()
            ->addLine(TextFormat::GOLD . TextFormat::BOLD . "HungerGame")
            ->addWhiteSpace()
            ->addMutableLine(function ()
            {
                return TextFormat::AQUA . "Joueur en vie: " . TextFormat::RESET . TextFormat::BOLD . PlayersManager::getInstance()->getAlivePlayerLeft();
            });
    }

    public function handlePlayerConnection(Player $p_Player)
    {
        if (GameManager::getInstance()->isWaiting())
        {
            foreach (SpawnManager::getInstance()->getSpawns() as $l_Slot)
            {
                if ($l_Slot instanceof Location)
                {
                    $l_NearbyEntities = $l_Slot->getLevel()
                        ->getNearbyEntities(WorldUtils::getRadiusBB($l_Slot, doubleval(1)));

                    if (count($l_NearbyEntities) == 0)
                    {
                        echo $l_Slot . " available !\n";
                        $p_Player->teleport($l_Slot);
                        break;
                    } else
                        echo $l_Slot . " not available\n";
                }
            }

            echo "onlinePlayers: " . count($this->getServer()->getOnlinePlayers()) >= PlayersManager::getInstance()->getMinPlayer() . "\n";
            if (count($this->getServer()->getOnlinePlayers()) >= PlayersManager::getInstance()->getMaxPlayer())
            {
                echo "MAX PLAYER REACH !\n";
                if ($this->m_WaitingTimer instanceof Timer)
                    $this->m_WaitingTimer->cancel();
                $this->startGame();
            }
            else if (count($this->getServer()->getOnlinePlayers()) >= PlayersManager::getInstance()->getMinPlayer())
            {
                if (is_null($this->m_WaitingTimer))
                {
                    echo "MIN PLAYER REACH !\n";
                    $this->m_WaitingTimer = (new BossbarTimer(GameManager::getInstance()->getWaitingTickDuration()))
                        ->setTitle("Debut dans")
                        ->addStopCallback(function ()
                        {
                            $this->startGame();
                        })
                        ->start();
                }
            }

        } else
        {
            $p_Player->setGamemode(3);
            $p_Player->sendMessage(TextFormat::YELLOW . "You've been automatically set to SPECTATOR");
            $this->getServer()->getLogger()->info($p_Player->getName() . " has been set to SPECTATOR");
        }

        Sidebar::getInstance()->update();
    }

    //---------------------
    // UTILS
    //---------------------
    public function startGame()
    {
        LoadBalancer::getInstance()->setServerState(LoadBalancer::SERVER_STATE_CLOSED);
        ChestsManager::getInstance()->fillChests();

        foreach ($this->getServer()->getOnlinePlayers() as $l_Player)
        {
            PlayersManager::getInstance()->getFatPlayer($l_Player)->setPlaying();
            if ($this->getHungerGameConfig()->isSkyWars())
            {
                $l_Player->getInventory()->addItem(ItemFactory::get(ItemIds::STONE_PICKAXE));
                $l_Player->setGamemode(Player::SURVIVAL);
            }
            else
            {
                $l_Player->setGamemode(Player::ADVENTURE);
            }
            $l_Player->addTitle(TextFormat::GREEN . "GO !");
            $l_Player->addEffect(Effect::getEffect(Effect::DAMAGE_RESISTANCE)->setAmplifier(10)->setDuration(30 * 20));
        }

        $this->m_PlayTimer = (new BossbarTimer(GameManager::getInstance()->getPlayingTickDuration()))
            ->setTitle("Fin de la partie dans")
            ->addStopCallback(function () {
                if (PlayersManager::getInstance()->getAlivePlayerLeft() <= 1)
                    $this->endGame();
                else
                {
                    $l_ArenaLoc = Location::fromObject($this->getHungerGameConfig()->getDeathArenaLoc());

                    foreach (FatUtils::getInstance()->getServer()->getOnlinePlayers() as $l_Player)
                    {
                        $l_Player->addSubTitle(TextFormat::DARK_AQUA . TextFormat::BOLD . "Timer terminé, match à mort dans l'arène !");
                        $l_Player->teleport(WorldUtils::getRandomizedLocation($l_ArenaLoc, 3, 0, 3));
                        $l_Player->sendTip("Vous êtes invulnérable pendant 5 secondes");
                        $l_Player->addEffect(Effect::getEffect(Effect::DAMAGE_RESISTANCE)->setAmplifier(10)->setDuration(5 * 20));
                    }
                }
            })
            ->start();

        GameManager::getInstance()->startGame();
        SpawnManager::getInstance()->unblockSpawns();
    }

    public function endGame()
    {
        if ($this->m_PlayTimer instanceof Timer)
            $this->m_PlayTimer->cancel();

        $winners = PlayersManager::getInstance()->getAlivePlayers();
        $winnerName = "";
        if (count($winners) > 0)
        {
            $winner = $winners[0];
            if ($winner instanceof FatPlayer)
                $winnerName = $winner->getPlayer()->getName();
        }
        foreach (FatUtils::getInstance()->getServer()->getOnlinePlayers() as $l_Player)
            $l_Player->addTitle(TextFormat::DARK_AQUA . TextFormat::BOLD . "Partie terminée", TextFormat::GREEN . TextFormat::BOLD . "le vainqueur est " . $winnerName, 30, 80, 30);

        (new BossbarTimer(150))
            ->setTitle("Retour au lobby")
            ->addStopCallback(function ()
            {
                $this->getServer()->shutdown();
            })
            ->start();

        GameManager::getInstance()->endGame();
    }

    //---------------------
    // GETTERS
    //---------------------
    /**
     * @return mixed
     */
    public function getHungerGameConfig(): BedwarsConfig
    {
        return $this->m_BedwarsConfig;
    }
}