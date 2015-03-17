<?php

use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();

$app['debug'] = true;

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__ . '/../views',
));

$app->register(new Silex\Provider\TranslationServiceProvider(), array(
	'translator.messages' => array(),
));

$app->register(new Silex\Provider\FormServiceProvider());

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
	'db.options' => array(
		'driver'   => 'pdo_mysql',
		'host'     => 'localhost',
		'dbname'   => 'rash',
		'user'     => 'rash',
		'password' => 'DoNotGuessThisPassword',
		'charset'  => 'utf8'
	),
));

define('QUOTES_PER_PAGE', 30);

function displayQuotes($ordering = 'latest', $page = 1) {
	global $app;

	switch($ordering) {
		case 'best':
			$orderSql = 'ORDER BY rating DESC';
			break;

		case 'worst':
			$orderSql = 'ORDER BY rating ASC';
			break;

		case 'latest':
			$orderSql = 'ORDER BY id DESC';
			break;

		default:
			$app->abort(404, 'Not supported ordering mode.');
	}

	// fetch quotes
	$sql = 'SELECT * FROM quotes WHERE accepted = 1 ' . $orderSql . ' LIMIT %d, %d'; // LIMIT skip, count

	$skip = QUOTES_PER_PAGE * ($page - 1);
	$count = QUOTES_PER_PAGE;

	$rows = $app['db']->fetchAll(sprintf($sql, $skip, $count));

	// get count
	$sql = 'SELECT COUNT(*) AS `count` FROM quotes ' . $orderSql;
	$count = $app['db']->fetchAssoc($sql)['count'];

	return $app['twig']->render('list.twig', [
		'quotes' => $rows,
		'quote_count' => $count,
		'quote_pages' => ceil($count / QUOTES_PER_PAGE),
		'quote_cur_page' => $page,
        'quote_ordering' => $ordering
	]);
}

// TEST ROUTE
$app->match('/add', function(Request $req) use ($app) {

	$form = $app['form.factory']->createBuilder('form', [])
		->add('quote', 'textarea')
		->getForm();

	$form->handleRequest($req);

	if ($form->isValid()) {
		$data = $form->getData();
		$data['submitip'] = $_SERVER['REMOTE_ADDR'];
		$data['date'] = time();
		$app['db']->insert('quotes', $data);
		$quote_id = $app['db']->lastInsertId();
		return $app->redirect('/list/latest');
	}

	return $app['twig']->render('add.twig', ['form' => $form->createView()]);
});

$app->get('/', function() use ($app) {
	return $app->redirect('/list/latest');
});

$app->get('/random', function() use ($app) {
	$id = $app['db']->fetchAssoc('SELECT id FROM quotes WHERE accepted = 1 ORDER BY RAND() LIMIT 1')['id'];
	return $app->redirect('/view/' . $id);
});

$app->get('/view/{id}', function($id) use ($app) {
	$row = $app['db']->fetchAssoc('SELECT * FROM quotes WHERE accepted = 1 AND id = ? LIMIT 1', [$id]);

	if (!$row) {
		$app->abort(404, 'No such quote found!');
	}

	return $app['twig']->render('view.twig', [
		'row' => $row
	]);
});

$app->get('/list/{ordering}', function($ordering) use ($app) {
	return displayQuotes($ordering);
})->bind('list');

$app->get('/list/{ordering}/{page}', function($ordering, $page) use ($app) {
	return displayQuotes($ordering, $page);
})->bind('list_page');

$app->run();

