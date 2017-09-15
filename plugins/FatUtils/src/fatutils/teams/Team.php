<?php
/**
 * Created by IntelliJ IDEA.
 * User: Nyhven
 * Date: 14/09/2017
 * Time: 19:01
 */

namespace fatutils\teams;


use fatutils\FatUtils;
use fatutils\players\PlayersManager;
use fatutils\spawns\Spawn;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;

class Team
{
    private $m_Name = "NoName";
    private $m_Prefix = TextFormat::RESET . TextFormat::GRAY . "◀N▶" . TextFormat::RESET . TextFormat::WHITE;

    /* array of Player's uuid as binary */
    private $m_Players = [];
    private $m_MaxPlayer = 10;

    private $m_Spawn = null;

    public function addPlayer(Player $p_Player)
    {
        $this->m_Players[] = $p_Player->getUniqueId()->toBinary();
    }

    public function addPlayers(array $p_Players)
    {
        foreach ($p_Players as $l_Player)
        {
            if ($l_Player instanceof Player)
                $this->addPlayer($l_Player);
        }
    }

    public function getAlivePlayerLeft(): int
    {
        $i = 0;
        foreach ($this->getOnlinePlayers() as $l_Player)
        {
            $l_FatPlayer = PlayersManager::getInstance()->getFatPlayer($l_Player);
            if (!$l_FatPlayer->hasLost())
                $i++;
        }
        return $i;
    }


    public function getAlivePlayers(): array
    {
        $l_Ret = [];
        foreach ($this->getOnlinePlayers() as $l_Player)
        {
            $l_FatPlayer = PlayersManager::getInstance()->getFatPlayer($l_Player);
            if (!$l_FatPlayer->hasLost())
                $l_Ret[] = $l_FatPlayer;
        }
        return $l_Ret;
    }

    public function isPlayerInTeam(Player $p_Player)
    {
        return array_key_exists($p_Player->getUniqueId()->toBinary(), $this->m_Players);
    }

    public function getPlaceLeft():int
    {
        return $this->getMaxPlayer() - $this->getPlayerCount();
    }

    /**
     * @param null $m_Spawn
     */
    public function setSpawn($m_Spawn)
    {
        $this->m_Spawn = $m_Spawn;
    }

    /**
     * @param string $m_Name
     */
    public function setName(string $m_Name)
    {
        $this->m_Name = $m_Name;
    }

    /**
     * @param string $m_Prefix
     */
    public function setPrefix(string $m_Prefix)
    {
        $this->m_Prefix = $m_Prefix;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->m_Name;
    }

    public function getPlayerCount():int
    {
        return count($this->m_Players);
    }

    /**
     * @return int
     */
    public function getMaxPlayer(): int
    {
        return $this->m_MaxPlayer;
    }

    /**
     * @param int $m_MaxPlayer
     */
    public function setMaxPlayer(int $m_MaxPlayer)
    {
        $this->m_MaxPlayer = $m_MaxPlayer;
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->m_Prefix;
    }

    /**
     * @return array
     */
    public function getOnlinePlayers(): array
    {
        $l_Players = [];

        foreach ($this->m_Players as $l_PlayerRawUUID)
        {
            $l_PlayerUUID = UUID::fromBinary($l_PlayerRawUUID);
            if ($l_PlayerUUID instanceof UUID)
            {
                $l_Player = PlayersManager::getInstance()->getPlayerFromUUID($l_PlayerUUID);
                if (!is_null($l_Player))
                    $l_Players[] = $l_Player;
            }
        }

        return $l_Players;
    }

    public function getSpawn(): Spawn
    {
        return $this->m_Spawn;
    }
}