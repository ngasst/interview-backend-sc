<?php

namespace App\Http\Controllers\v1;

use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class JokeController extends Controller
{
    /**
     * Fetches a random joke from the official-joke-api.appspot.com and returns it as JSON.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchRandomJoke(): JsonResponse
    {
        $jokes = [
            ['setup' => 'Why don’t scientists trust atoms?', 'punchline' => 'Because they make up everything.'],
            ['setup' => 'How do you organize a space party?', 'punchline' => 'You planet.'],
            ['setup' => 'What’s an astronaut’s favorite part of a computer?', 'punchline' => 'The space bar.'],
            ['setup' => 'Why did the scarecrow win an award?', 'punchline' => 'Because he was outstanding in his field.'],
            ['setup' => 'Why don’t skeletons fight each other?', 'punchline' => 'They don’t have the guts.'],
        ];
    
        try {
            $response = Http::get('https://official-joke-api.appspot.com/random_joke');
    
            if ($response->successful()) {
                // Directly use the response from the API if successful
                $joke = $response->json();
            } else {
                throw new \Exception('Failed to fetch joke from API.');
            }
        } catch (RequestException $e) {
            // If an SSL certificate problem occurs, catch the error and fetch the joke via curl, bypassing SSL
            if (strpos($e->getMessage(), 'cURL error 60') !== false) {
                $joke = $this->fetchJokeWithCurl();
            } else {
                // If the error is not SSL related, we could rethrow it, but as this is a relatively low stakes API and we assume the person requestign a joke really needs it, we'll give one from this limited sampling
                // throw $e;
                $joke = $jokes[array_rand($jokes)];
            }
        } catch (\Exception $e) {
            // Handle other exceptions or errors
            return response()->json(['error' => 'An unexpected error occurred.', 'details' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        
        return response()->json($joke);
    }
    
    private function fetchJokeWithCurl() {
        $url = 'https://official-joke-api.appspot.com/random_joke';
    
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Only for local/testing, not production
    
        $response = curl_exec($curl);
        if (curl_error($curl)) {
            // Handle error or fallback
            return ['error' => curl_error($curl)];
        }
        curl_close($curl);
    
        return json_decode($response, true); // Decode JSON response into an associative array
    }
}