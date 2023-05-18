<?php

use DI\Container;
use Slim\Views\Twig;
use Slim\Factory\AppFactory;
use Slim\Views\TwigMiddleware;
use Slim\Middleware\MethodOverrideMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();
AppFactory::setContainer($container);

$container->set('db', function () {
    $db = new \PDO("sqlite:" . __DIR__ . '/../database/database.sqlite');
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(\PDO::ATTR_TIMEOUT, 5000);
    $db->exec("PRAGMA journal_mode = WAL");
    return $db;
});

$app = AppFactory::create();

$app->addBodyParsingMiddleware();

$twig = Twig::create(__DIR__ . '/../twig', ['cache' => false]);

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

$app->get('/users', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);

    $query_params = $request->getQueryParams();
    $format = Null;
    if (array_key_exists('format', $query_params)){
        $format = $query_params["format"];
    };
    if(empty($query_params)) {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'users.html', [
            'users' => $users
        ]);
        die;
    } elseif($format == "json"){
        $json = json_encode($users);
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
        die;
    } elseif($format == "text"){
        $body = $response->getBody();
        $string = serialize($users); 
        $body->write($string);
        return $response->withHeader('Content-Type', 'text/plain');
        die;
    }
});

$app->get('/users-by-header', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);

    $accept = $request->getHeaderLine('Accept');
    if (strstr($accept, 'application/json')) {
        $json = json_encode($users);
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    } elseif(strstr($accept, 'text/plain')){
        $body = $response->getBody();
        $string = serialize($users); 
        $body->write($string);
        return $response->withHeader('Content-Type', 'text/plain');
    }
    return $response->withStatus(404);
});

$app->get('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];

    $db = $this->get('db');
    $sth = $db->prepare('SELECT * FROM users WHERE id=:id LIMIT 1');
    $sth->bindValue(':id', $id);
    $sth->execute();

    $user = $sth->fetch(\PDO::FETCH_OBJ);
    dump($user);
    if ($user){
        $view = Twig::fromRequest($request);
        return $view->render($response, 'user.html', [
            'user' => $user,
            'id' => $args['id']
        ]);
    }
    else{
        return $response->withStatus(404);
    }
})->setName('user');

$app->post('/users', function (Request $request, Response $response, $args) {
    try
    {
        $db = $this->get('db');
        $parsedBody = $request->getParsedBody();

        $first_name = $parsedBody["first_name"];
        $last_name = $parsedBody["last_name"];
        $email = $parsedBody["email"];
        $sth = $db->prepare("INSERT INTO users (first_name, last_name, email) VALUES (?,?,?)");
        $sth->execute([$first_name, $last_name, $email]);


        $user = array(
            'first_name'=>$first_name,
            'last_name'=>$last_name,
            'email'=>$email
        );
        $jsonUser = json_encode($user);
        $response->getBody()->write($jsonUser);
        $response = $response->withHeader('Content-type', 'application/json');
        
        return $response->withStatus(201);
    }
    catch(Throwable $ex)
    {
        return $response->withStatus($ex->getCode());
    }
});

$app->patch('/users/{id}', function (Request $request, Response $response, $args) {
    try
    {
        $id = $args['id'];
        $db = $this->get('db');

        $parsedBody = $request->getParsedBody();

        $first_name = $parsedBody["first_name"];
        $last_name = $parsedBody["last_name"];
        $email = $parsedBody["email"];

        $sth = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
        $sth->execute([$first_name, $last_name, $email, $id]);

        $user = array(
            'first_name'=>$first_name,
            'last_name'=>$last_name,
            'email'=>$email
        );
        $jsonUser = json_encode($user);
        $response->getBody()->write($jsonUser);
        $response = $response->withHeader('Content-type', 'application/json');
        
        return $response->withStatus(200);
    }
    catch(Throwable $ex)
    {
        return $response->withStatus($ex->getCode());
    }
});

$app->put('/users/{id}', function (Request $request, Response $response, $args) {
    try
    {
        $id = $args['id'];
        $db = $this->get('db');

        $parsedBody = $request->getParsedBody();

        $first_name = $parsedBody["first_name"];
        $last_name = $parsedBody["last_name"];
        $email = $parsedBody["email"];

        $sth = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
        $sth->execute([$first_name, $last_name, $email, $id]);
        $redirect = "http://localhost:8888/users";
        header("Location: $redirect");
    }
    catch(Throwable $ex)
    {
        return $response->withStatus($ex->getCode());
    }
});

$app->delete('/users/{id}', function (Request $request, Response $response, $args) {
    try
    {
        $id = $args['id'];
        $db = $this->get('db');
        
        $sth = $db->prepare('DELETE FROM users WHERE id=:id');
        $sth->bindValue(':id', $id);
        $sth->execute();
        return $response->withStatus(204);
    }
    catch(Throwable $ex)
    {
        return $response->withStatus($ex->getCode());
    }
});

$app->get('/users_download_report', function (Request $request, Response $response, $args) {
    $root = dirname(__FILE__);
    $file = $root.'/'.'sample.pdf';
    $datee = date ('Y-m-d').'.pdf';
    if (ob_get_level()) {
        ob_end_clean();
      }
      header('Content-Description: File Transfer');
      header('Content-Type: application/octet-stream');
      header('Content-Disposition: attachment; filename=user_report_'.$datee);
      header('Content-Transfer-Encoding: binary');
      header('Expires: 0');
      header('Cache-Control: must-revalidate'); 
      header('Pragma: public');
      header('Content-Length: ' . filesize($file));
      readfile($file);
      exit;
});

$app->get('/products', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM products");
    $sth->execute();
    $products = $sth->fetchAll(\PDO::FETCH_OBJ);

    $view = Twig::fromRequest($request);
    return $view->render($response, 'products.html', [
        'products' => $products
    ]);
});

$app->post('/add-cart', function (Request $request, Response $response, $args) {
    $parsedBody = $request->getParsedBody();

    $id = $parsedBody["itemId"];
    $basket = array();
    if(isset($_COOKIE['basket']))
        $basket = json_decode($_COOKIE['basket'], true);
    $basket[] = $id;
    setcookie("basket", json_encode($basket));
    $redirect = "http://localhost:8888/products";
    header("Location: $redirect");
});

$app->get('/cart', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM products");
    $sth->execute();

    $products = $sth->fetchAll(\PDO::FETCH_OBJ);
    $basket = array();
    if(isset($_COOKIE['basket']))
        $basket = json_decode($_COOKIE['basket'], true);
    $newBasket = array();
    $per = array_count_values($basket);
    foreach($per as $key=>$value){
        $product = $products[$key-1];
        if($product->name && $product->price && $product->image){
            $newProduct = (object) [
                'key' => $key,
                'name' => $product->name,
                'price' => $product->price,
                'image' => $product->image,
                'count' => $value,
              ];
        }
        $newBasket[] = $newProduct;
    }
    dump($newBasket);
    $view = Twig::fromRequest($request);
    return $view->render($response, 'cart.html', [
        'products' => $newBasket
    ]);
});

$app->post('/remove-cart', function (Request $request, Response $response, $args) {
    $parsedBody = $request->getParsedBody();
    $arrayId = $parsedBody["key"];
    $basket = array();
    if(isset($_COOKIE['basket']))
        $basket = json_decode($_COOKIE['basket'], true);
    $key = array_search($arrayId, $basket);
    unset($basket[$key]);
    setcookie("basket", json_encode($basket));
    $redirect = "http://localhost:8888/cart";
    header("Location: $redirect");
});

$methodOverrideMiddleware = new MethodOverrideMiddleware();
$app->add($methodOverrideMiddleware);

$app->add(TwigMiddleware::create($app, $twig));
$app->run();