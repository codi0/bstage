<?php

/**
 * BSTAGE.RUN() EVENTS
 *
 * app.init
 * app.updating
 * app.request
 * app.response
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

function bstage($name=null, $callback=null) {
	//static vars
	static $appCache = [], $lastRun = 'app';
	//is callback?
	if($name && !is_scalar($name)) {
		$callback = $name;
		$name = null;
	}
	//set name
	$name = $lastRun = $name ?: $lastRun;
	//bootstrap app?
	if(!isset($appCache[$name])) {
		//set constants
		if(!defined('BSTAGE_MIN_PHP')) define('BSTAGE_MIN_PHP', '5.6.0');
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
		//set name
		$opts['name'] = $name;
		//setup services
		$opts['services'] = array_merge([
			'cache' => function(array $opts, $app) {
				return new \Bstage\Cache\File(array_merge([
					'dir' => $app->meta('base_dir') . '/cache',
				], $opts));
			},
			'captcha' => function(array $opts, $app) {
				return new \Bstage\Security\Captcha(array_merge([
					'crypt' => $app->crypt,
					'session' => $app->session,
				], $opts));
			},
			'composer' => function(array $opts, $app) {
				return new \Bstage\App\Composer($opts);
			},
			'config' => function(array $opts, $app) {
				return new \Bstage\Container\Config(array_merge([
					'dir' => $app->meta('base_dir') . '/config',
					'fileNames' => [ 'core', 'secrets' ],
				], $opts));
			},
			'cookies' => function(array $opts, $app) {
				return new \Bstage\Http\CookieHandler(array_merge([
					'crypt' => $app->crypt,
					'signKey' => isset($opts['signKey']) ? $opts['signKey'] : $app->secret('cookie_sign'),
					'encryptKey' => isset($opts['encryptKey']) ? $opts['encryptKey'] : $app->secret('cookie_encrypt'),
				], $opts));
			},
			'crypt' => function(array $opts, $app) {
				return new \Bstage\Security\Crypt($opts);
			},
			'csrf' => function(array $opts, $app) {
				return new \Bstage\Security\Csrf(array_merge([
					'crypt' => $app->crypt,
					'session' => $app->session,
					'injectHead' => (bool) $app->config('csrf.head'),
				], $opts));
			},
			'db' => function(array $opts, $app) {
				return \Bstage\Db\Pdo::create(array_merge([
					'logger' => $app->loggerFactory->create('sqlErrors'),
					'debug' => $app->meta('debug'),
				], $opts));
			},
			'dom' => function(array $opts, $app) {
				return 	new \Bstage\View\Dom\Document($opts);
			},
			'errorHandler' => function(array $opts, $app) {
				return new \Bstage\Debug\ErrorHandler(array_merge([
					'logger' => $app->loggerFactory->create('phpErrors'),
					'debug' => $app->meta('debug'),
					'events' => $app->events,
				], $opts));
			},
			'escaper' => function(array $opts, $app) {
				return new \Bstage\Security\Escaper($opts);
			},
			'events' => function(array $opts, $app) {
				return new \Bstage\Event\Dispatcher(array_merge([
					'context' => $app,
				], $opts));
			},
			'formFactory' => function(array $opts, $app) {
				return new \Bstage\View\Form\FormFactory(array_merge([
					'html' => $app->html,
					'input' => $app->input,
					'orm' => $app->orm,
				], $opts));
			},
			'html' => function(array $opts, $app) {
				return new \Bstage\View\Html(array_merge([
					'captcha' => $app->captcha,
					'createUrl' => function($path) use($app) {
						return $app->url($path);
					},
				], $opts));
			},
			'httpClient' => function(array $opts, $app) {
				return new \Bstage\Http\Client($opts);
			},
			'httpFactory' => function(array $opts, $app) {
				return new \Bstage\Http\Factory($opts);
			},
			'httpMiddleware' => function(array $opts, $app) {
				return new \Bstage\Http\RequestHandler($opts);
			},
			'input' => function(array $opts, $app) {
				return new \Bstage\Http\Input(array_merge([
					'validator' => $app->validator,
				], $opts));
			},
			'jsend' => function(array $opts, $app) {
				return new \Bstage\View\Jsend(array_merge([
					'events' => $app->events,					
				], $opts));
			},
			'jwt' => function(array $opts, $app) {
				return new \Bstage\Security\Jwt(array_merge([
					'crypt' => $app->crypt,
				], $opts));
			},
			'loggerFactory' => function(array $opts, $app) {
				return new \Bstage\Container\Factory(array_merge([
					'classFormats' => [
						'Bstage\\Debug\\Logger\\{name}',
						'Bstage\\Debug\\Logger',
					],
					'defaultOpts' => [
						'filePath' => $app->meta('base_dir') . '/logs/{name}.log',
					],
				], $opts));
			},
			'mail' => function(array $opts, $app) {
				return new \Bstage\View\Mail(array_merge([
					'events' => $app->events,
					'fromEmail' => $app->config->get('site.email'),
					'fromName' => $app->config->get('site.name'),
				], $opts));
			},
			'opwr' => function(array $opts, $app) {
				return new \Bstage\Protocol\Opwr(array_merge([
					'crypt' => $app->crypt,
					'events' => $app->events,
					'httpClient' => $app->httpClient,
					'endpoint' => $app->meta('base_url'),
					'signKeys' => isset($opts['signKeys']) ? $opts['signKeys'] : $app->secret('opwr_sign', [ 'type' => 'rsa' ]),
					'encryptKeys' => isset($opts['encryptKeys']) ? $opts['encryptKeys'] : $app->secret('opwr_encrypt', [ 'type' => 'rsa' ]),
				], $opts));
			},
			'orm' => function(array $opts, $app) {
				return new \Bstage\Orm\Orm(array_merge([
					'app' => $app,
					'db' => $app->db,
					'validator' => $app->validator,
				], $opts));
			},
			'router' => function(array $opts, $app) {
				return new \Bstage\Http\Route\Dispatcher(array_merge([
					'httpFactory' => $app->httpFactory,
					'context' => $app,
				], $opts));
			},
			'session' => function(array $opts, $app) {
				return new \Bstage\Http\Session(array_merge([
					'lifetime' => 3600,
					'cookies' => $app->cookies,
					'events' => $app->events,
				], $opts));
			},
			'shortcodes' => function(array $opts, $app) {
				return new \Bstage\View\Shortcode\ShortcodeManager(array_merge([
					'app' => $app,
				], $opts));
			},
			'templates' => function(array $opts, $app) {
				return new \Bstage\View\Template\Engine(array_merge([
					'data' => [
						'auth' => $app->auth,
						'meta' => [
							'name' => $app->config('site.name'),
							'email' => $app->config('site.email'),
							'pathinfo' => trim($app->meta('path_info'), '/'),
						],
					],
					'callbacks' => [
						'url' => function($value=null, $query=null, $merge=false) use($app) {
							return $app->url($value, $query, $merge);
						},
						'html' => function($tag, $val) use($app) {
							$args = func_get_args();
							$tag = array_shift($args);
							return $app->html->$tag(...$args);
						},
					],
					'paths' => array_map(function($value) {
						return $value . '/templates';
					}, $app->meta('inc_paths')),
					'theme' => $app->config->get('site.theme'),
					'layout' => $app->config->get('site.layout') ?: 'base',
					'csrf' => $app->csrf,
					'events' => $app->events,
					'escaper' => $app->escaper,
					'shortcodes' => $app->shortcodes,
				], $opts));
			},
			'validator' => function(array $opts, $app) {
				return new \Bstage\Security\Validator(array_merge([
					'db' => $app->db,
					'captcha' => $app->captcha,
					'crypt' => $app->crypt,
				], $opts));
			},
		], isset($opts['services']) ? $opts['services'] : []);
		//create app
		$app = new \Bstage\App\Kernel($opts);
		//Event: add debug bar
		$app->events->add('app.response', function($event) use($app) {
			//is web scope?
			if($scope = $app->meta('scope')) {
				if($scope !== 'web') {
					return;
				}
			}
			//is html response?
			if($event->response->getMediaType() !== 'html') {
				return;
			}
			//get output
			$output = $event->response->getContents();
			//show debug vars?
			if($app->meta('debug') && $event->request->getAttribute('primary')) {
				//get queries
				$queries = $app->db->getQueries();
				//get debug bar
				$debug = $app->errorHandler->debugBar($queries);
				//add before </footer> or </body>
				if(preg_match('/<\/(footer|body)>/i', $output)) {
					$output = preg_replace('/<\/(footer|body)>/i', $debug. '</$1>', $output, 1);
				} else {
					$output .= "\n" . trim($debug);
				}
			}
			//update response body
			$event->response->withContents($output);
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