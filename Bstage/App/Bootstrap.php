<?php

/**
 * BSTAGE.RUN() EVENTS
 *
 * app.init
 * app.updating
 * app.middleware
 * app.shutdown
 *
 * BSTAGE LIBRARY EVENTS
 *
 * template.select | runs if template rendered
 * template.head | runs if template rendered
 * template.footer | runs if template rendered
 * template.output | runs if template rendered
 * jsend.output | runs if jsend rendered
 * error.handle | runs if uncaught exception found
 * mail.send | runs if email sent
 * protocol.verify | runs if protocol executed
 *
**/

function bstage($name, $callback=null) {
	//app cache
	static $appCache = [];
	//bootstrap app?
	if(!isset($appCache[$name])) {
		//set constants
		if(!defined('BSTAGE_MIN_PHP')) define('BSTAGE_MIN_PHP', '5.6.0');
		if(!defined('BSTAGE_START_TIME')) define('BSTAGE_START_TIME', microtime(true));
		if(!defined('BSTAGE_START_MEM')) define('BSTAGE_START_MEM', memory_get_usage());
		//min php version found?
		if(version_compare(PHP_VERSION, BSTAGE_MIN_PHP, '<')) {
			exit('To use the Bstage framework, please ask your web host to upgrade to at least php ' . BSTAGE_MIN_PHP);
		}
		//load app kernel?
		if(!class_exists('Bstage\App\Kernel', false)) {
			require(__DIR__ . '/Kernel.php');
		}
		//app setup opts?
		if(is_array($callback) && !is_callable($callback)) {
			$opts = $callback;
			$callback = null;
		} else {
			$opts = [];
		}
		//setup registry
		$opts['registry'] = array_merge([
			'api' => function($app, array $opts) {
				return new \Bstage\Protocol\Openwrite(array_merge([
					'crypt' => $app->crypt,
					'events' => $app->events,
					'httpClient' => $app->httpClient,
					'endpoint' => $app->meta('base_url'),
					'signKeys' => isset($opts['signKeys']) ? $opts['signKeys'] : $app->secret('http_sign', [ 'type' => 'rsa' ]),
					'encryptKeys' => isset($opts['encryptKeys']) ? $opts['encryptKeys'] : $app->secret('http_encrypt', [ 'type' => 'rsa' ]),
					'createUrl' => function($path, $query=[], $merge=false) use($app) {
						return $app->url($path, $query, $merge);
					},
				], $opts));
			},
			'cache' => function($app, array $opts) {
				return new \Bstage\Cache\File(array_merge([
					'dir' => $app->meta('base_dir') . '/cache',
				], $opts));
			},
			'captcha' => function($app, array $opts) {
				return new \Bstage\Security\Captcha(array_merge([
					'crypt' => $app->crypt,
					'session' => $app->session,
				], $opts));
			},
			'config' => function($app, array $opts) {
				return new \Bstage\Container\Config(array_merge([
					'path' => $app->meta('base_dir') . '/config',
				], $opts));
			},
			'cookie' => function($app, array $opts) {
				return new \Bstage\Http\Cookie(array_merge([
					'crypt' => $app->crypt,
					'signKey' => isset($opts['signKey']) ? $opts['signKey'] : $app->secret('cookie_sign'),
					'encryptKey' => isset($opts['encryptKey']) ? $opts['encryptKey'] : $app->secret('cookie_encrypt'),
				], $opts));
			},
			'crypt' => function($app, array $opts) {
				return new \Bstage\Security\Crypt($opts);
			},
			'csrf' => function($app, array $opts) {
				return new \Bstage\Security\Csrf(array_merge([
					'crypt' => $app->crypt,
					'session' => $app->session,
				], $opts));
			},
			'db' => function($app, array $opts) {
				return \Bstage\Db\Pdo::create(array_merge([
					'schemaFile' => $app->meta('base_dir') . '/config/db/schema.sql',
					'logger' => $app->loggers->sqlErrors,
					'debug' => $app->meta('debug'),
				], $opts));
			},
			'dom' => function($app, array $opts) {
				return 	new \Bstage\Dom\Dom($opts);
			},
			'errors' => function($app, array $opts) {
				return new \Bstage\Debug\Error(array_merge([
					'logger' => $app->loggers->phpErrors,
					'debug' => $app->meta('debug'),
					'events' => $app->events,
				], $opts));
			},
			'events' => function($app, array $opts) {
				return new \Bstage\Event\Dispatcher(array_merge([
					'context' => $app,
				], $opts));
			},
			'forms' => function($app, array $opts) {
				return new \Bstage\Output\Form(array_merge([
					'html' => $app->html,
					'input' => $app->input,
				], $opts));
			},
			'html' => function($app, array $opts) {
				return new \Bstage\Output\Html(array_merge([
					'captcha' => $app->captcha,
				], $opts));
			},
			'httpClient' => function($app, array $opts) {
				return new \Bstage\Http\Client($opts);
			},
			'httpFactory' => function($app, array $opts) {
				return new \Bstage\Http\Factory($opts);
			},
			'httpMiddleware' => function($app, array $opts) {
				return new \Bstage\Http\RequestHandler($opts);
			},
			'input' => function($app, array $opts) {
				return new \Bstage\Http\Input(array_merge([
					'validator' => clone $app->validator,
				], $opts));
			},
			'jsend' => function($app, array $opts) {
				return new \Bstage\Output\Jsend(array_merge([
					'events' => $app->events,					
				], $opts));
			},
			'jwt' => function($app, array $opts) {
				return new \Bstage\Security\Jwt(array_merge([
					'crypt' => $app->crypt,
				], $opts));
			},
			'loggers' => function($app, array $opts) {
				return new \Bstage\Debug\Logger(array_merge([
					'file' => $app->meta('base_dir') . '/logs/phpErrors.log',
				], $opts));
			},
			'mail' => function($app, array $opts) {
				return new \Bstage\Output\Mail(array_merge([
					'events' => $app->events,
					'fromEmail' => $app->config->get('site.email'),
					'fromName' => $app->config->get('site.name'),
				], $opts));
			},
			'models' => function($app, array $opts) {
				return new \Bstage\Model\ModelManager(array_merge([
					'app' => $app,
					'db' => $app->db,
					'config' => $app->config,
				], $opts));
			},
			'router' => function($app, array $opts) {
				return new \Bstage\Route\Dispatcher(array_merge([
					'httpFactory' => $app->httpFactory,
					'context' => $app,
				], $opts));
			},
			'session' => function($app, array $opts) {
				return new \Bstage\Session\Cookie(array_merge([
					'lifetime' => 1800,
					'cookie' => $app->cookie,
				], $opts));
			},
			'templates' => function($app, array $opts) {
				return new \Bstage\Output\Template(array_merge([
					'dir' => $app->meta('base_dir') . '/templates',
					'events' => $app->events,
					'callbacks' => [
						'url' => function($value) use($app) {
							return $app->url($value);
						},
						'asset' => function($value) use($app) {
							return $app->url('assets/' . $value);
						},
					]
				], $opts));
			},
			'validator' => function($app, array $opts) {
				return new \Bstage\Security\Validator(array_merge([
					'db' => $app->db,
					'captcha' => $app->captcha,
				], $opts));
			},
		], isset($opts['registry']) ? $opts['registry'] : []);
		//create app
		$app = new \Bstage\App\Kernel($opts);
		//create database schema
		$app->events->add('app.updating', function($event) use($app) {
			$app->db->createSchema();
		});
		//filter response output
		$app->httpMiddleware->add(function($request, $next) use($app) {
			//get response
			$response = $next($request);
			//is primary HTML response?
			if($response->isSub || $response->type !== 'html') {
				return $response;
			}
			//get response stream
			$stream = $response->getBody();
			//get HTML content
			$content = $stream->rewind()->getContents();
			//csrf protection
			$content = $app->csrf->injectToken($content);
			//show debug vars?
			if($app->meta('debug')) {
				//debug vars
				$time = number_format(microtime(true) - BSTAGE_START_TIME, 5);
				$mem = number_format((memory_get_usage() - BSTAGE_START_MEM) / 1024, 0);
				$peak = number_format(memory_get_peak_usage() / 1024, 0);
				$queries = $app->db->getQueries();
				//debug data
				$debug  = '<div id="debug">' . "\n";
				$debug .= '<p>Time: ' . $time . 's | Mem: ' . $mem . 'kb | Peak: ' . $peak . 'kb | Queries: ' . count($queries) . '</p>' . "\n";
				$debug .= '<p>' . implode('<br>', $queries) . '</p>' . "\n";
				$debug .= '</div>' . "\n";
				//add before </body>
				if(stripos($content, '</body>') !== false) {
					$content = preg_replace('/<\/body>/i', $debug. '</body>', $content);
				} else {
					$content .= "\n" . trim($debug);
				}
			}
			//replace HTML content
			$stream->rewind()->write($content);
			//return
			return $response;
		});
		//cache app
		$appCache[$name] = $app;
	}
	//execute callback?
	if($callback && is_callable($callback)) {
		call_user_func($callback, $appCache[$name]);
	}
	//return
	return $appCache[$name];
}