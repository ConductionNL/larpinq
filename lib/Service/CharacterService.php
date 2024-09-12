<?php

namespace OCA\LarpingApp\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use OCP\IURLGenerator;
use Symfony\Component\Uid\Uuid;

class CharacterService
{
    public $abilities;
	public $skills;
	public $conditions;
	public $events;

	public const BASE_OBJECT = [
		'database'   => 'objects',
		'collection' => 'json',
	];

	public function getAbilities(){
		if(!$this->abilities){
			$this->abilities = $this->abilityMapper->findAll();
		}
		return $this->abilities;
	}

}
