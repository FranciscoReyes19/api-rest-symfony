<?php
namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;

use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;

class UserController extends AbstractController
{
	private function resjson($data){

		// SERIALIZAR DATOS CON SERVICIO DE SERIALIZER
		$json = $this->get('serializer')->serialize($data,'json');

		// RESPONSE CON HTTPFUNDATION
		$response = new Response();

		// ASIGNAR CONTENIDO A LA RESPUESTA
		$response->setContent($json);

		// INDICAR FORMATO DE RESPUESTA
		$response->headers->set('Content-type','application/json');

		//DEVOLVER LA RESPUESTA
		return $response;

	}

    public function index()
    {
    	$user_repo = $this->getDoctrine()->getRepository(User::class);
    	$video_repo = $this->getDoctrine()->getRepository(Video::class);

    	/**
    	 * Obtener todos los usuarios
    	 */
    	$users = $user_repo->findAll();
    	$user = $user_repo->find(1);

    	$videos = $video_repo->findAll();
    	$video = $video_repo->find(1);

    	$data = [
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/UserController.php',
        ];
    	/*
    	foreach ($users as $user) {
    		echo "<h1>{$user->getName()} {$user->getSurname()}</h1>";

    		foreach($user->getVideos() as $video){
    			echo "<p>{$video->getTitle()} - {$video->getUser()->getEmail()}</p>";
    		}
    	}

    	die();
    	*/
    	//var_dump($user);
    	//die();
        return $this->resjson($data);
    }

	public function create(Request $request){
		//RECOGER LOS DATOS POR POST
		$json = $request->get('json', null);

		//DECODIFICAR EL JSON
		$params = json_decode($json);
		//var_dump($params);
		//die();

		//RESPUESTA POR DEFECTO
		$data = [
			'status' => 'error',
			'code' => 200,
			'message' => 'EL USUARIO NO SE HA CREADO.'
		];

		//COMPROBAR Y VALIDAR DATOS
		if($json != null){

			$name         = (!empty($params->name)) ? $params->name : null;
			$surname      = (!empty($params->surname)) ? $params->surname : null;
			$email        = (!empty($params->email)) ? $params->email : null;
			$password     = (!empty($params->password)) ? $params->password : null;
			
			$validator = Validation::createValidator();
			$validate_email = $validator->validate($email, [
				new Email()
			]);
			
			if(!empty($email) && count($validate_email) == 0 && (!empty($password)) && (!empty($name)) && (!empty($surname)) ){

				$data = [
					'status' => 'success',
					'code' => 200,
					'message' => 'VALIDACION CORRECTA.'
				];

				//SI LA VALIDACION ES CORRECTA, CREAR EL OBJETO DEL USUARIO
				$user = new User();
				$user->setName($name);
				$user->setSurname($surname);
				$user->setEmail($email);
				$user->setRole('ROLE_USER');
				$user->setCreatedAt(new \Datetime('now'));

				//CIFRAR CONTRASEÑA
				$pwd = hash('sha256',$password);
				$user->setPassword($pwd);

				//COMPROBAR SI EL USUARIO EXISTE (DUPLICADOS)
				$doctrine = $this->getDoctrine();
				$em = $doctrine->getManager();

				$user_repo = $doctrine->getRepository(User::class);
				$isset_user = $user_repo->findBy(array(
					'email' => $email
				));

				//SI NO EXISTE, GUARDAR EN LA BASE DE DATOS
				if(count($isset_user) == 0){
					
					//PREPARA PARA GUARDAR 
					$em->persist($user);
					//GUARADAR USUARIO YA EN LA DB
					$em->flush();

					$data = [
						'status' => 'success',
						'code' => 200,
						'message' => 'USUARIO CREADO CORRECTAMENTE.',
						'user' => $user
					];
				}else{
					$data = [
						'status' => 'error',
						'code' => 400,
						'message' => 'EL USUARIO YA EXISTE.'
					];
				}

				//HACER RESPUESTA EN JSON

			}

		}

		//return $this->resjson($data);
		return $this->resjson($data);

	}

	public function login(Request $request, JwtAuth $jwt_auth){
		//RECIBIR LOS DATOS POR POST
		$json = $request->get('json',null);
		$params = json_decode($json);

		//ARRAY POR DEFECTO PARA DEVOLVER
		$data = [
					'status' => 'error',
					'code' => 400,
					'message' => 'NO SE PUDO LOGEAR O IDENTIFICAR.'
				];

		//COMPROBAR Y VALIDAR DATOS
		if($json != null){
			$email = (!empty($params->email)) ? $params->email : null;
			$password = (!empty($params->password)) ? $params->password : null;
			$gettoken = (!empty($params->gettoken)) ? $params->gettoken : null;

			$validator = Validation::createValidator();
			$validate_email = $validator->validate($email, [
				new Email()
			]);

			if( !empty($email) && !empty($password) && count($validate_email) == 0  ){
				//CIFRAR LA CONTRASEÑA
				$pwd = hash('sha256',$password);

				//SI TODO ES VALIDO, LLAMAREMOS A UN SERVICIO PARA IDENTIFICAR AL USUARIO (JWT, TOKEN O UN OBJETO)

				if ($gettoken) {
					//DEVOLVERA EL TOKEN DEL USUARIO
					$signup = $jwt_auth->signup($email,$pwd,$gettoken);
				}else{
					//DEVOLVERA EL OBJETO DEL USUARIO
					$signup = $jwt_auth->signup($email,$pwd);
				}

				return new JsonResponse($signup);
			
			}
		}

		// SI NOS DEVULVE BIEN LOS DATOS, RESPUESTA
		return $this->resjson($data);
	}

	public function edit(Request $request, JwtAuth $jwt_auth){

		//RESPUESTA POR DEFECTO
		$data = [
			'status' => 'error',
			'code' => 400,
			'message' => 'NO SE HA REALIZADO LA ACTUALIZACION',
		];
		
		//Recoger la cabecera de autentificacion
		$token = $request->headers->get('Authorization');

		//CREAR UN METODO PARA CONPROBAR SI EL TOKEN ES CORRECTO
		$authCheckToken = $jwt_auth->checkToken($token);

		//SI ES CORRECTO, HACER LA ACTUALIZACION DEL USUARIO
		if ($authCheckToken) {
		//ACTUALIZAR AL USUARIO

			//CONSEGUIR ENTITY MANAGER
			$em = $this->getDoctrine()->getManager();


			//CONSEGUIR LOS DATOS DEL USUARIO IDENTIFICADO
			$identity = $jwt_auth->checkToken( $token, true);
			
			//CONSEGUIR EL USUARIO A ACTUALIZAR COMPLETO
			$user_repo = $this->getDoctrine()->getRepository(User::class);

			$user = $user_repo->findOneBy([
				'id' => $identity->sub
			]);


			//RECOGER LOS DATOS A ACTUALIZAR POR POST
			$json = $request->get('json', null);
			$params = json_decode($json);

			//COMPROBAR Y VALIDAR LOS DATOS
			if($json != null || !empty($json)){

				$name         = (!empty($params->name)) ? $params->name : null;
				$surname      = (!empty($params->surname)) ? $params->surname : null;
				$email        = (!empty($params->email)) ? $params->email : null;
				
				$validator = Validation::createValidator();
				$validate_email = $validator->validate($email, [
					new Email()
				]);
				
				if(!empty($email) && count($validate_email) == 0 && (!empty($name)) && (!empty($surname)) ){
					//ASIGNAR NUEVOS DATOS AL OBJETO DEL USUARIO
					$user->setName($name);
					$user->setSurname($surname);
					$user->setEmail($email);

					//COMPROBAR DUPLICADOS
					$isset_user = $user_repo->findBy([
						'email' => $email
					]);

					if (count($isset_user) == 0 || $identity->email == $email) {
						//GUARDAR CAMBIOS EN LA BASE DE DATOS
						$em->persist($user);
						$em->flush();

						$data = [
							'status' => 'success',
							'code' => 200,
							'message' => 'USUARIO ACTUALIZADO',
							'user' => $user
						];

					}else{
						$data = [
							'status' => 'error',
							'code' => 400,
							'message' => 'USUARIO DUPLICADO O EMAIL INCORRECTO',
						];
					}

				}
			}

		}else{
			$data = [
							'status' => 'error',
							'code' => 400,
							'message' => 'ERROR DE AUTORIZACION',
						];
		}

		return $this->resjson($data);

	}


}
