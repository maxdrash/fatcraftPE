<?php

namespace fatutils\scores;

use fatcraft\loadbalancer\LoadBalancer;
use fatutils\FatUtils;
use fatutils\gamedata\GameDataManager;
use fatutils\players\PlayersManager;
use fatutils\shop\ShopManager;
use fatutils\teams\Team;
use fatutils\teams\TeamsManager;
use libasynql\DirectQueryMysqlTask;
use pocketmine\utils\UUID;
use SalmonDE\StatsPE\CustomEntries;

class ScoresManager
{
	const OPTION_KEY_REWARDS_ROOT = "rewards";
	const OPTION_KEY_MAX_FATSILVER_REWARD = "fatSilver";
	const OPTION_KEY_MAX_FATGOLD_REWARD = "fatGold";
	const OPTION_KEY_MAX_XP_REWARD = "xp";

	private $m_RewardedPlayers = [];
	private $m_Scoreboards = [];

	//--> REWARDS
	private $m_MaxFatsilverReward = null;
	private $m_MaxFatgoldReward = null;
	private $m_MaxXpReward = null;

    protected static $m_Instance = null;

    public static function getInstance():ScoresManager
	{
		if (is_null(self::$m_Instance))
			self::$m_Instance = new ScoresManager();

		return self::$m_Instance;
	}

    private function __construct()
    {
        $this->initialize();
    }

    private function initialize()
    {
        $this->initDatabase();
    }

    private function initDatabase()
    {
        LoadBalancer::getInstance()->connectMainThreadMysql()->query("CREATE TABLE IF NOT EXISTS `scores` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `game` int(11) NOT NULL,
            `player` varchar(36) NOT NULL,
            `position` int(11) NOT NULL,
            `data` text DEFAULT NULL,
            `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        )");
    }

    public function recordScore(String $p_PlayerUUID, int $p_Position, $data = array())
    {
    	var_dump("REGISTER SCORE", $p_PlayerUUID, $p_Position, $data);

    	$l_JsonData = json_encode($data);
    	FatUtils::getInstance()->getLogger()->info("Registering score for $p_PlayerUUID at pos $p_Position. ($l_JsonData)");

        if (GameDataManager::getInstance()->getGameId() != 0)
        {
            FatUtils::getInstance()->getServer()->getScheduler()->scheduleAsyncTask(
                new DirectQueryMysqlTask(LoadBalancer::getInstance()->getCredentials(),
                    "INSERT INTO scores (game, player, position, data) VALUES (?, ?, ?, ?)", [
                    ["i", GameDataManager::getInstance()->getGameId()],
                    ["s", $p_PlayerUUID],
                    ["i", $p_Position],
                    ["s", $l_JsonData]
                ]
            ));
        }
    }

	public function getMaxFatsilverReward()
	{
		return $this->m_MaxFatsilverReward;
	}

	public function setMaxFatsilverReward(int $p_MaxFatsilverReward)
	{
		$this->m_MaxFatsilverReward = $p_MaxFatsilverReward;
	}

	public function getMaxFatgoldReward()
	{
		return $this->m_MaxFatgoldReward;
	}

	public function setMaxFatgoldReward(int $p_MaxFatgoldReward)
	{
		$this->m_MaxFatgoldReward = $p_MaxFatgoldReward;
	}

	public function getMaxXpReward()
	{
		return $this->m_MaxXpReward;
	}

	public function setMaxXpReward(int $p_MaxXpReward)
	{
		$this->m_MaxXpReward = $p_MaxXpReward;
	}

	public function addScoreboard(Scoreboard &$p_Scoreboard, int $p_Weight = 1):ScoresManager
	{
		if (array_search($p_Scoreboard, $this->m_Scoreboards) == false)
			$this->m_Scoreboards[] = $p_Scoreboard;

		$p_Scoreboard->setWeight($p_Weight);

		return $this;
	}

	public function getMergedPlayersScore(): PlayerScoreboard
	{
		$l_GeneratedScoreboard = new PlayerScoreboard();
		foreach ($this->m_Scoreboards as $l_Scoreboard)
		{
			if ($l_Scoreboard instanceof PlayerScoreboard)
			{
				$l_Scores = $l_Scoreboard->getScores();
				foreach ($l_Scores as $l_RawUUID => $l_Score)
				{
					$l_PlayerUUID = UUID::fromString($l_RawUUID);
					$l_GeneratedScoreboard->addUuidScore($l_PlayerUUID, $l_Score * $l_Scoreboard->getWeight());
				}
			} else if ($l_Scoreboard instanceof TeamScoreboard)
			{
				$l_Scores = $l_Scoreboard->getScores();
				foreach ($l_Scores as $l_TeamName => $l_Score)
				{
					$l_Team = TeamsManager::getInstance()->getTeamByName($l_TeamName);
					if ($l_Team instanceof Team)
					{
						foreach ($l_Team->getPlayerUUIDs() as $l_PlayerUUID)
							$l_GeneratedScoreboard->addUuidScore($l_PlayerUUID, $l_Score * $l_Scoreboard->getWeight());
					}
				}
			}
		}

		return $l_GeneratedScoreboard;
	}

	public function giveRewardToPlayer(UUID $p_PlayerUuid, float $p_RewardRatio)
	{
		if (array_search($p_PlayerUuid->toString(), $this->m_RewardedPlayers) === false)
		{
			$l_Player = PlayersManager::getInstance()->getPlayerFromUUID($p_PlayerUuid);
			$l_FatPlayer = PlayersManager::getInstance()->getFatPlayerByUUID($p_PlayerUuid);

			$l_Rewards = FatUtils::getInstance()->getTemplateConfig()->get(ScoresManager::OPTION_KEY_REWARDS_ROOT);
			$l_FatsilverReward = (is_null($this->m_MaxFatsilverReward) ? (array_key_exists(ScoresManager::OPTION_KEY_MAX_FATSILVER_REWARD, $l_Rewards) ? $l_Rewards[ScoresManager::OPTION_KEY_MAX_FATSILVER_REWARD] : 0) : $this->m_MaxFatsilverReward);
			$l_FatgoldReward = (is_null($this->m_MaxFatgoldReward) ? (array_key_exists(ScoresManager::OPTION_KEY_MAX_FATGOLD_REWARD, $l_Rewards) ? $l_Rewards[ScoresManager::OPTION_KEY_MAX_FATGOLD_REWARD] : 0) : $this->m_MaxFatgoldReward);
			$l_XpReward = (is_null($this->m_MaxXpReward) ? (array_key_exists(ScoresManager::OPTION_KEY_MAX_XP_REWARD, $l_Rewards) ? $l_Rewards[ScoresManager::OPTION_KEY_MAX_XP_REWARD] : 0) : $this->m_MaxXpReward);

			$l_FatsilverReward = round($l_FatsilverReward * $p_RewardRatio);
			$l_FatgoldReward = round($l_FatgoldReward * $p_RewardRatio);
			$l_XpReward = round($l_XpReward * $p_RewardRatio);

			$this->recordScore($p_PlayerUuid, round($p_RewardRatio * 100), [
				"fatSilver" => $l_FatsilverReward,
				"fatGold" => $l_FatgoldReward,
				"xp" => $l_XpReward,
			]);

			if ($l_Player != null && $l_Player->isOnline() && $l_FatPlayer != null)
			{
				if ($l_FatsilverReward > 0)
					$l_FatPlayer->addFatsilver($l_FatsilverReward);
				if ($l_FatgoldReward > 0)
					$l_FatPlayer->addFatgold($l_FatgoldReward);
				CustomEntries::getInstance()->modIntEntry("XP", $l_Player, $l_XpReward);

				echo $p_PlayerUuid->toString() . "has been rewarded with \n";
			}

			$this->m_RewardedPlayers[] = $p_PlayerUuid->toString();
		} else
			echo $p_PlayerUuid->toString() . "has been skipped from scoring cause was already in.\n";
	}

	public function giveRewardToPlayers(array $p_PlayersRatio)
	{
		foreach ($p_PlayersRatio as $l_RawUuid => $l_PlayerRatio)
			$this->giveRewardToPlayer(UUID::fromString($l_RawUuid), $l_PlayerRatio);
	}
}
