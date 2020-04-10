<?php
namespace App\Services;

use Firebase\JWT\JWT;
use App\Entity\User;

class JwtAuth{

	public $manager;
	public $key;

	public function __construct($manager){
		$this->manager = $manager;
		$this->key = 'soy_unaclave_secreta_para_JWT23432432';
	}

	public function signup($email, $password, $gettoken = null){
		//COMPROBAR SI EL USUARIO EXISTE
		$user = $this->manager->getRepository(User::class)->findOneBy([
			'email' => $email,
			'password' => $password
		]);

		//SI EXISTE, GENERAR EL TOKEN DE JWT
		$signup = false;

		if (is_object($user)) {
			$signup = true;
		}

		if ($signup) {

			$token = [
				'sub' => $user->getId(),
				'name' => $user->getName(),
				'surname' => $user->getSurname(),
				'email' => $user->getEmail(),
				'iat' => time(),
				'exp' => time() + (7 * 24 * 60 * 60)
			];

			//COMPROBAR EL FLAG gettoken, HACER CONDICIONAL
			$jwt = JWT::encode($token,$this->key, 'HS256');
			
			if (!empty($gettoken)) {
			
				$data = $jwt;
			
			}else{
				$decoded = JWT::decode($jwt,$this->key, ['HS256']);
				$data = $decoded;

			}


		}else{
			$data = [
				'status' => 'error',
				'message' => 'LOGIN INCORRECTO'
			];
		}
		//DEVOLVER LOS DATOS

		return $data;
	}

	public function checkToken($token_jwt, $identity = false){
		
		$auth = false;

		try {
			$decoded = JWT::decode($token_jwt,$this->key, ['HS256']);
		} catch (\UnexpectedValueException $e) {
			$auth = false;
			
		}catch (\DomainException $e){
			$auth = false;
		}

		if ( isset($decoded) && !empty($decoded) && is_object($decoded) && isset($decoded->sub) ) {
			$auth = true;
		}

		if ($identity != false ) {
			
			return $decoded;

		}else{

			return $auth;
			
		}

	}
}