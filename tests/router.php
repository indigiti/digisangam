<?php
declare(strict_types=1);

$uri=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(str_starts_with($uri,'/api/v1')){
    $rest=substr($uri,strlen('/api/v1'));
    $_GET['path']=trim($rest,'/');
    require dirname(__DIR__).'/public/api/index.php';
    return true;
}
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error'=>'TEST_ROUTE_NOT_FOUND','uri'=>$uri]);
return true;
