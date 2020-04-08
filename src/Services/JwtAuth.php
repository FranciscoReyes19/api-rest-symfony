<?php
namespace App\Services;

use Firebase\JWT\JWT;
use App\Entity\User;

class JwtAuth{

	public $manager;

	public function __contruct($manager){
		$this->manager = $manager;
	}

	public function signup(){
		return "Hola mundo desde el servicio";
	}
}