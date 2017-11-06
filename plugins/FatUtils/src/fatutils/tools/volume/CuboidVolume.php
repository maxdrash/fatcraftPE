<?php
/**
 * Created by IntelliJ IDEA.
 * User: Nyhven
 * Date: 03/11/2017
 * Time: 10:32
 */

namespace fatutils\tools\volume;

use fatutils\tools\LoopedExec;
use fatutils\tools\MathUtils;
use fatutils\tools\particles\ParticleBuilder;
use pocketmine\entity\Entity;
use pocketmine\event\TimingsHandler;
use pocketmine\level\particle\Particle;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

class CuboidVolume
{
	private static $m_IdAutoIncrement = 0;
	private $m_Id;

	private $m_CollisionCallbacks = [];
	private $m_EnterVolumeCallbacks = [];
	private $m_LeaveVolumeCallbacks = [];

	/** @var Entity[] */
	private $m_EntitiesInside = [];

	/** @var LoopedExec */
	private static $m_CollisionTask = null;

	/** @var CuboidVolume[] */
	private static $m_CollidableVolumes = [];
	private $m_Timings = null;

	/** @var Position */
	private $m_Pos1;
	/** @var Position */
	private $m_Pos2;

	private $m_MinX = 0;
	private $m_MinY = 0;
	private $m_MinZ = 0;
	private $m_MaxX = 0;
	private $m_MaxY = 0;
	private $m_MaxZ = 0;

	public static function createRelativeVolume(Position $p_RelativPos, float $p_RelPos1X = 0, float $p_RelPos1Y = 0, float $p_RelPos1Z = 0, float $p_RelPos2X = 0, float $p_RelPos2Y = 0, float $p_RelPos2Z = 0):CuboidVolume
	{
		$l_Pos1 = Position::fromObject($p_RelativPos->add($p_RelPos1X, $p_RelPos1Y, $p_RelPos1Z), $p_RelativPos->getLevel());
		$l_Pos2 = Position::fromObject($p_RelativPos->add($p_RelPos2X, $p_RelPos2Y, $p_RelPos2Z), $p_RelativPos->getLevel());

		return new CuboidVolume($l_Pos1, $l_Pos2);
	}

	public function __construct(Position $p_Pos1, Position $p_Pos2)
	{
		$this->m_Id = self::$m_IdAutoIncrement++;

		$this->m_Pos1 = $p_Pos1;
		$this->m_Pos2 = $p_Pos2;

		$this->m_MinX = ($p_Pos1->getX() < $p_Pos2->getX()) ? $p_Pos1->getX() : $p_Pos2->getX();
		$this->m_MinY = ($p_Pos1->getY() < $p_Pos2->getY()) ? $p_Pos1->getY() : $p_Pos2->getY();
		$this->m_MinZ = ($p_Pos1->getZ() < $p_Pos2->getZ()) ? $p_Pos1->getZ() : $p_Pos2->getZ();
		$this->m_MaxX = ($p_Pos1->getX() < $p_Pos2->getX()) ? $p_Pos2->getX() : $p_Pos1->getX();
		$this->m_MaxY = ($p_Pos1->getY() < $p_Pos2->getY()) ? $p_Pos2->getY() : $p_Pos1->getY();
		$this->m_MaxZ = ($p_Pos1->getZ() < $p_Pos2->getZ()) ? $p_Pos2->getZ() : $p_Pos1->getZ();
	}

	/**
	 * @param callable(Entity) $p_CollisionCallback
	 */
	public function addCollisionListener(Callable $p_CollisionCallback)
	{
		$this->m_CollisionCallbacks[] = $p_CollisionCallback;
		$this->initScheduler();
	}

	/**
	 * @param callable(Entity) $p_EnteringCallback
	 */
	public function addEnteringListener(Callable $p_EnteringCallback)
	{
		$this->m_EnterVolumeCallbacks[] = $p_EnteringCallback;
		$this->initScheduler();
	}

	/**
	 * @param callable(Entity) $p_CollisionCallback
	 */
	public function addLeavingListener(Callable $p_LeavingCallback)
	{
		$this->m_LeaveVolumeCallbacks[] = $p_LeavingCallback;
		$this->initScheduler();
	}

	/**
	 * @param int $p_Quantity
	 * @return Position[]
	 */
	public function getRandomLocations(int $p_Quantity = 1): array
	{
		$l_Ret = [];

		for ($i = 0; $i < $p_Quantity; $i++)
			$l_Ret[] = new Position(MathUtils::frand($this->getMinX(), $this->getMaxX(), 2), MathUtils::frand($this->getMinY(), $this->getMaxY(), 2), MathUtils::frand($this->getMinZ(), $this->getMaxZ(), 2), $this->getPos1()->getLevel());

		return $l_Ret;
	}

	public function initScheduler()
	{
		if (array_key_exists($this->m_Id, self::$m_CollidableVolumes) == false)
		{
			self::$m_CollidableVolumes[$this->m_Id] = $this;
		}

		if (is_null(self::$m_CollisionTask))
		{
			self::$m_CollisionTask = new LoopedExec(function () {

				if (is_null($this->m_Timings))
					$this->m_Timings = new TimingsHandler("VolumesCollide");

				$this->m_Timings->startTiming();
				foreach (self::$m_CollidableVolumes as $l_Volume)
				{
					if ($l_Volume instanceof CuboidVolume)
					{
						foreach ($l_Volume->getPos1()->getLevel()->getEntities() as $l_Entity)
						{
							$l_WasIn = array_key_exists($l_Entity->getId(), $l_Volume->m_EntitiesInside);

							if ($l_Volume->isIn($l_Entity))
							{
								if (!$l_WasIn)
								{
									$l_Volume->m_EntitiesInside[$l_Entity->getId()] = $l_Entity;

									// ENTERING
									foreach ($l_Volume->m_EnterVolumeCallbacks as $l_EnterVolumeCallback)
									{
										if (is_callable($l_EnterVolumeCallback))
											$l_EnterVolumeCallback($l_Entity);
									}
								}

								// COLLISIONS
								foreach ($l_Volume->m_CollisionCallbacks as $l_CollisionCallback)
								{
									if (is_callable($l_CollisionCallback))
										$l_CollisionCallback($l_Entity);
								}
							} else if ($l_WasIn)
							{
								unset($l_Volume->m_EntitiesInside[$l_Entity->getId()]);

								// LEAVING
								foreach ($l_Volume->m_LeaveVolumeCallbacks as $l_LeaveVolumeCallback)
								{
									if (is_callable($l_LeaveVolumeCallback))
										$l_LeaveVolumeCallback($l_Entity);
								}
							}
						}
					}
				}
				$this->m_Timings->stopTiming();
			});
		}
	}

	public function removeListeners()
	{
		if (array_key_exists($this->m_Id, self::$m_CollidableVolumes))
		{
			if (count(self::$m_CollidableVolumes) == 1)
			{
				self::$m_CollisionTask->cancel();
				self::$m_CollisionTask = null;
			}

			$this->m_CollisionCallbacks = [];
			$this->m_EnterVolumeCallbacks = [];
			$this->m_LeaveVolumeCallbacks = [];

			unset(self::$m_CollidableVolumes[$this->m_Id]);
		}
	}

	public function isIn(Vector3 $p_Position)
	{
		return ($p_Position->x >= $this->m_MinX) && ($p_Position->x < $this->m_MaxX) && ($p_Position->y >= $this->m_MinY) && ($p_Position->y < $this->m_MaxY) && ($p_Position->z >= $this->m_MinZ) && ($p_Position->z < $this->m_MaxZ);
	}

	/**
	 * @return Entity[]
	 */
	public function getEntityInside(): array
	{
		$l_Ret = [];

		foreach ($this->getPos1()->getLevel()->getEntities() as $l_Entity)
			if ($this->isIn($l_Entity))
				$lret[] = $l_Entity;

		return $l_Ret;
	}

	public function display()
	{
		$l_Edges = ParticleBuilder::fromParticleId(Particle::TYPE_REDSTONE);

		$l_Edges->play(Position::fromObject($this->getPos1(), $this->getPos1()->getLevel()));
		$l_Edges->play(Position::fromObject($this->getPos2(), $this->getPos2()->getLevel()));

		$l_RandomParticle = ParticleBuilder::fromParticleId(Particle::TYPE_DUST)->setColor(10, 10, 210);
		foreach ($this->getRandomLocations(5) as $l_Position)
			$l_RandomParticle->play($l_Position);
	}

	/**
	 * @return int
	 */
	public function getId(): int
	{
		return $this->m_Id;
	}

	/**
	 * @return Position
	 */
	public function getPos1(): Position
	{
		return $this->m_Pos1;
	}

	/**
	 * @return Position
	 */
	public function getPos2(): Position
	{
		return $this->m_Pos2;
	}

	/**
	 * @return float
	 */
	public function getMinX(): float
	{
		return $this->m_MinX;
	}

	/**
	 * @return float
	 */
	public function getMinY(): float
	{
		return $this->m_MinY;
	}

	/**
	 * @return float
	 */
	public function getMinZ(): float
	{
		return $this->m_MinZ;
	}

	/**
	 * @return float
	 */
	public function getMaxX(): float
	{
		return $this->m_MaxX;
	}

	/**
	 * @return float
	 */
	public function getMaxY(): float
	{
		return $this->m_MaxY;
	}

	/**
	 * @return float
	 */
	public function getMaxZ(): float
	{
		return $this->m_MaxZ;
	}
}