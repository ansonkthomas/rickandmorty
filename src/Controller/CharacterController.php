<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpClient\HttpClient;

use Curl;

class CharacterController extends AbstractController
{

    const API_URL_CHARACTER = 'https://rickandmortyapi.com/api/character/';
    const API_URL_EPISODE = 'https://rickandmortyapi.com/api/episode/';

    /**
     * Fetch the character list
     *
     * @Route("/characters", name="list_characters")
     */
    public function listCharacters(Request $request): Response
    {
        $parameters = '';
        $formValues = array('name' => '', 'status' => '', 'gender' => '', 'current_page' => 1);
        $requestQuery = $request->query;
        $paramsAndFormValues = $this->formatParameters($requestQuery, $parameters, $formValues);
        $formValues = $paramsAndFormValues['form_values'];

        //Request to fetch the character list
        $client = HttpClient::create();
        $response = $client->request('GET', self::API_URL_CHARACTER . '?' . $paramsAndFormValues['parameters']);
        if ($response->getStatusCode() == 200) {
            $content = $response->toArray();
            //Set the previous and next page numbers for paginastion
            $formValues['prev'] = ($content['info']['prev'] != '') ? $formValues['current_page'] - 1 : '';
            $formValues['next'] = ($content['info']['next'] != '') ? $formValues['current_page'] + 1 : '';
        } else {
            $content["results"] = array();
        }

        return $this->render('character/characters.html.twig', [
            'characters' => $content["results"],
            'form_values' => $formValues
        ]);
    }

    /**
     * Format the search parameters from submitted form to a string to make the API call
     */
    private function formatParameters($requestQuery, $parameters, $formValues) {
        if ($requestQuery->has('name') || $requestQuery->has('status') || $requestQuery->has('gender')) {
            if ($requestQuery->get('name') != '') {
                $parameters = $parameters . '&name=' . $requestQuery->get('name');
                $formValues['name'] = $requestQuery->get('name');
            }
            if ($requestQuery->get('status') != '') {
                $parameters = $parameters . '&status=' . $requestQuery->get('status');
                $formValues['status'] = $requestQuery->get('status');
            }
            if ($requestQuery->get('gender') != '') {
                $parameters =  $parameters . '&gender=' . $requestQuery->get('gender');
                $formValues['gender'] = $requestQuery->get('gender');
            }
            if ($requestQuery->get('page') != '') {
                $parameters =  $parameters . '&page=' . $requestQuery->get('page');
                $formValues['current_page'] = $requestQuery->get('page');
            }
        }

        return array(
            'parameters' => $parameters,
            'form_values' => $formValues
        );
    }

    /**
     * Fetch the character details
     *
     * @Route("/characters/{id}", name="show_character", requirements={"number"="\d+"}, methods={"GET"})
     */
    public function showCharacter(int $id): Response
    {
        $character = array();
        $episodeList = array();

        //Request to fetch the character details
        $client = HttpClient::create();
        $response = $client->request('GET', self::API_URL_CHARACTER . $id);
        if ($response->getStatusCode() == 200) {
            $character = $response->toArray();
            if (array_key_exists("episode", $character)) {
                //Create a list of episode ids from multiple episode urls to make a single API call to fetch the episode names
                $episodeIdArray = array();
                foreach ($character["episode"] as $episode) {
                    //Regular expression to fetch the episode id from the episode url
                    preg_match_all('!\d+!', $episode, $matches);
                    $episodeIdArray[] = $matches[0][0];
                }
                //Make a comma separated string of episode ids
                $episodeIdString = implode(",", $episodeIdArray);
                //Fetch the episode details
                $response = $client->request('GET', self::API_URL_EPISODE . $episodeIdString);
                $episodeList = $response->toArray();
            }
        }

        return $this->render('character/show_character.html.twig', [
            'character' => $character,
            'episode_list' => $episodeList
        ]);
    }

    /**
     * 404 response in case of an invalid url. Method defined in framework.yaml
     */
    public function invalidUrl() {
        exit("Invalid URL");
    }
}
