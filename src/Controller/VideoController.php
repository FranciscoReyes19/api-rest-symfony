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

use Knp\Component\Pager\PaginatorInterface;

class VideoController extends AbstractController
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
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/VideoController.php',
        ]);
    }

    public function create(Request $request, JwtAuth $jwt_auth, $id = null){
    	
    	$data = [
			'status' => 'error',
			'code' => 400,
			'message' => 'EL VIDEO NO HA PODIDO CREARSE'
		];

		// RECOGER EL TOKEN
			$token = $request->headers->get('Authorization',null);

		// COMPROBAR SI ES CORRECTO
			$authCheck = $jwt_auth->checkToken($token);

			if ($authCheck) {
				// RECOGER DATOS POR POST
				$json = $request->get('json',null);

				$params = json_decode($json);

				// RECOGER EL OBJETO DEL USUARIO IDENTIFICADO
				$identity = $jwt_auth->checkToken($token,true); 	

				// COMPROBAR Y VALIDAR DATOS
				if(!empty($json)){
					$user_id       = ($identity->sub != null) ? $identity->sub : null;
					$title         = (!empty($params->title)) ? $params->title : null;
					$description   = (!empty($params->description)) ? $params->description : null;
					$url           = (!empty($params->url)) ? $params->url : null;

					if (!empty($user_id) && !empty($title)) {
						// GUARDAR EL NUEVO VIDEO FAVORITO EN LA BASE DE DATOS
						$em = $this->getDoctrine()->getManager();
						$user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
							'id' => $user_id
						]);

						if ($id == null) {
							//CREAR Y GUARDAR OBJETO
							$video = new Video();
							$video->setUser($user);
							$video->setTitle($title);
							$video->setDescription($description);
							$video->setUrl($url);
							$video->setStatus('normal');

							$createdAt = new \Datetime('now');
							$updatedAt = new \Datetime('now');

							$video->setCreatedAt($createdAt);
							$video->setUpdatedAt($updatedAt);

							//GUARDAR EN LA DB
							$em->persist($video);
							$em->flush();

							$data = [
								'status' => 'success',
								'code' => 200,
								'message' => 'EL VIDEO SE HA GUARDADO',
								'video' => $video
							];
						}else{
							//ACTUALIZAR VIDEO
							$video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
								'id' => $id,
								'user' => $identity->sub
							]);

							if ($video && is_object($video)) {

								$video->setTitle($title);
								$video->setDescription($description);
								$video->setUrl($url);
								$updatedAt = new \Datetime('now');
								$video->setUpdatedAt($updatedAt);

								$em->persist($video);
								$em->flush();
								
								$data = [
									'status' => 'success',
									'code' => 200,
									'message' => 'EL VIDEO SE HA ACTUALIZADO',
									'video' => $video
								];
							}

						}

					}
				}
			}

		//DEVOLVER UNA RESPUESTA
		return $this->resjson($data);

    }

    public function videos(Request $request, JwtAuth $jwt_auth, PaginatorInterface $paginator){
    	$data = array(
    		'status' => 'error',
    		'code' => 404,
    		'message' => "No se pueden listar los videos en este momento"
    	);

    	//RECOGER LA CABECERA DE AUTENTICACION
    	$token = $request->headers->get('Authorization');

    	//COMPROBAR EL TOKEN
    	$authCheck = $jwt_auth->checkToken($token);

    	//SI ES VALIDO
    	if ($authCheck) {
	    	//CONSEGUIR LA IDENTIDAD DEL USUARIO
	    	$identity = $jwt_auth->checkToken($token,true);

	    	$em = $this->getDoctrine()->getManager();

	    	//CONFIGURAR EL BUNDLE DE PAGINACION  --DONE--

	    	//HACER UNA CONSULTA PARA PAGINAR
	    	$dql = "SELECT v FROM App\Entity\Video v WHERE v.user = ($identity->sub) ORDER BY v.id DESC";

	    	$query = $em->createQuery($dql);

	    	//RECOGER EL PARAMENTRO PAGE DE LA URL
	    	$page = $request->query->getInt('page', 1);
	    	$items_per_page = 5;

	    	//INVOCAR PAGINACION
	    	$pagination = $paginator->paginate($query, $page, $items_per_page);
	    	$total = $pagination->getTotalItemCount();

	    	//PREPARAR ARRAY DE DATOS PARA DEVOLVER
		    $data = array(
	    		'status' => 'success',
	    		'code' => 200,
	    		'message' => "CORRECTO",
	    		'total_items_count' => $total,
	    		'page_current' => $page,
	    		'items_per_page' => $items_per_page,
	    		'total_pages' => ceil($total / $items_per_page),
	    		'videos' => $pagination,
	    		'user_id' => $identity->sub
	    	);


	    	// SI FALLA DEVOLVER ARRAY DEFAULT
    	}else{
    		$data = array(
    		'status' => 'error',
    		'code' => 404,
    		'message' => "ERROR EN LA AUTORIZACION"
    	);

    	}


    	return $this->json($data);
    }

    public function video(Request $request, JwtAuth $jwt_auth, $id = null){

		//DEFAULT
			$data = array(
	    		'status' => 'error',
	    		'code' => 404,
	    		'message' => "NO SE HA REALIZADO LA CONSULTA DEL VIDEO",
	    	);    	

    	//SACAR EL TOKEN Y COMPROBAR SI ES CORRECTO
    	$token = $request->headers->get('Authorization');
    	$authCheck = $jwt_auth->checkToken($token);

    	if ($authCheck) {

	    	//SACAR LA IDENTIDAD DEL USUARIO
	    	$identity = $jwt_auth->checkToken($token,true);

	    	//SACAR EL OBJETO DEL VIDEO CON BASE AL ID
	    	$video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
	    		'id' => $id,
	    		'user' => $identity->sub

	    	]);
	    	//COMPROBAR SI EL VIDEO EXISTE Y ES PROPIEDAD DEL USUARIO IDENTIFICADO
	    	if (($video) && is_object($video)) {
	    		
	    		//DEVOLVER UNA RESPUESTA
	    		$data = array(
		    		'status' => 'success',
		    		'code' => 200,
		    		'video' => $video
		    	);
	    	}

    	}

    	return $this->json($data);
    }

    public function delete(Request $request, JwtAuth $jwt_auth, $id = null){
    	//DEFAULT RESPONSE
    	$data = array(
		    		'status' => 'error',
		    		'code' => 400,
		    		'message' => 'Something went wrong buddy'
		    	);

    	$token = $request->headers->get('Authorization');
    	$authCheck = $jwt_auth->checkToken($token);

    	if ($authCheck) {
    		$identity = $jwt_auth->checkToken($token, true);

    		$doctrine = $this->getDoctrine();
    		$em = $doctrine->getManager();

    		//is´t allowed user_id on findOneBy, appearly just accept "user" as valid user-identity
    		$video = $doctrine->getRepository(Video::class)->findOneBy([
    			'id' => $id,
    			'user' => $identity->sub
    		]);

    		if ($video && is_object($video)) {
    			$em->remove($video);
    			$em->flush();
	    		
	    		$data = array(
			    		'status' => 'success',
			    		'code' => 200,
			    		'message' => 'Video was removed succesfully'
			    	);
    		}

    	}

    	return $this->json($data);
    }
}
