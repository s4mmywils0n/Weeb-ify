<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
  
</body>
</html>

<?php 

//1. so basically fetch all the api from tyhe anime api and put it on the db at least like 500 but for the trending fetch it from the api live 
// and if they select it then add it to the db

//so start with fetching the anime into the db if the db has no data in it

//then test with it a bit more then test with the file db then try making it a bit more secure and stricter before working around it on local host 

// try setting up the db too whenever you get the time to do it cy sent you the slim video on disc but if anything still try on railway if u can
                             

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$ch = curl_init("https://api.jikan.moe/v4/top/anime");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$anime_ = curl_exec($ch);
$httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if($anime_ === FALSE)
    {
        http_response_code(502);
        echo json_encode(['error' => 'cUrl Failed', 'detail'=> $curlErr]);
        exit;
    }

 echo "Url is wrong or cannot connect for some reason";Project

$response = json_decode($anime_, true); 

//Next time use $a= $response[data][num];

echo json_encode([
'status' => 'ok',
'anime_id' => $response['data'][0]["mal_id"]??null,
'url' => $response['data'][0]["url"] ?? null ,
'title' => $response['data'][0]["title"] ?? null,
], JSON_PRETTY_PRINT);

?>
