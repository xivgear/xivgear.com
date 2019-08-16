<?php


namespace App\Services\Lodestone;

use App\Entity\LodestoneCharacter;
use App\Entity\LodestoneCharacterLodestoneClass;
use App\Entity\LodestoneClass;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lodestone\Api;

class CharacterService extends AbstractService
{
    /**
     * @var GearSetService
     */
    private $gearSetService;

    /**
     * CharacterService constructor.
     * @param EntityManagerInterface $em
     * @param GearSetService $gearSetService
     */
    public function __construct(EntityManagerInterface $em, GearSetService $gearSetService)
    {
        parent::__construct($em);
        $this->gearSetService = $gearSetService;
    }

    /**
     * @param $lodestone_id
     * @return LodestoneCharacter
     * @throws Exception
     */
    public function get($lodestone_id): LodestoneCharacter
    {
        $character = $this->getRepository(LodestoneCharacter::class)->findOneBy(['lodestone_id' => $lodestone_id]);
        if (!$character) {
            $character = $this->create($lodestone_id);
        }
        return $character;
    }

    /**
     * @param $lodestone_id
     * @return LodestoneCharacter
     * @throws Exception
     */
    public function create($lodestone_id): LodestoneCharacter
    {
        $character = new LodestoneCharacter();
        $character->setLodestoneId($lodestone_id);
        $character->setJustCreated(true);

        $character = $this->update($character);

        return $character;
    }

    /**
     * @param LodestoneCharacter $character
     * @param null $data
     * @return LodestoneCharacter
     * @throws Exception
     */
    public function update(LodestoneCharacter $character, $data=null)
    {
        if (!$data) {
            $api = new Api();
            $data = $api->getCharacter($character->getLodestoneId());
        }

        $character->setName($data->Name);
        $character->setServer($data->Server);
        $character->setAvatarUrl($data->Avatar);
        $character->setPortraitUrl($data->Portrait);

        foreach ($data->ClassJobs as $classJob) {
            $class = $this->getRepository(LodestoneClass::class)->findOneBy(['lodestone_id' => $classJob->JobID]);
            if (!$class)
                continue;

            $map = null;
            if ($character->getId())
                $map = $this->getRepository(LodestoneCharacterLodestoneClass::class)->findOneBy(['lodestone_character' => $character, 'lodestone_class' => $class]);

            if (!$map) {
                $map = new LodestoneCharacterLodestoneClass();
                $map->setLodestoneCharacter($character);
                $map->setLodestoneClass($class);
            }
            $map->setLevel($classJob->Level);
            $map->setExperience($classJob->ExpLevel);
            $map->setExperienceTotal($classJob->ExpLevelMax);
            $this->em->persist($map);

            $character->addLodestoneClassMapping($map);
        }

        $gearset = $this->gearSetService->createOrUpdate($data, $character);
        $character->addGearSet($gearset);
        $this->em->persist($character);
        $this->em->flush();

        return $character;
    }

    public function setUpdateFailure(int $lodestone_id)
    {
        $character = $this->getRepository(LodestoneCharacter::class)->findOneBy(['lodestone_id' => $lodestone_id]);
        $character->setUpdateFailed(true);
        $this->em->persist($character);
        $this->em->flush();
    }
}