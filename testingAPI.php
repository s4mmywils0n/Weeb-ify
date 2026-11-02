
<?php 

//1. so basically fetch all the api from tyhe anime api and put it on the db at least like 500 but for the trending fetch it from the api live 
// and if they select it then add it to the db

//so start with fetching the anime into the db if the db has no data in it

//then test with it a bit more then test with the file db then try making it a bit more secure and stricter before working around it on local host 

// try setting up the db too whenever you get the time to do it cy sent you the slim video on disc but if anything still try on railway if u can
                             

header("Access-Control-Allow-Origin: *");
header("Content-Type: text/html; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$ch = curl_init("https://kitsu.io/api/edge/anime?page[limit]=1" );
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$anime_ = curl_exec($ch);
$httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
unset($ch);

if($anime_ === FALSE)
    {
        http_response_code(502);
        echo json_encode(['error' => 'cUrl Failed', 'detail'=> $curlErr]);
        exit;
    }

$response = json_decode($anime_, true); 
$response = $response['data'][0]??null;
$slug = ['attributes']['slug']??null;
$image = $response['attributes']['posterImage']['large'] ?? null;
//Next time use $a= $response[data][num];

    echo "<img src=\"" . htmlspecialchars($image). "\" style=\"max-width:300px\"><br><br>";


    
echo json_encode([
'status' => 'ok ',
'anime_id' => $response["id"]??null,
'slug' => $slug,
"kitsu_url" => $slug ? "https://kitsu.io/anime/{$slug}" : null,
'title' => $response["attributes"]["canonicalTitle"] ?? null,
'sypnosis' => $response["attributes"]["synopsis"] ?? null,

], JSON_PRETTY_PRINT);

?>

