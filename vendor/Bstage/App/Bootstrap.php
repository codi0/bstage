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
 * template.theme | runs if template rendered
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
		//set name
		$opts['name'] = $name;
		//setup services
		$opts['services'] = array_merge([
			'api' => function(array $opts, $app) {
				return new \Bstage\Protocol\Opwr(array_merge([
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
					'fileNames' => [ 'core', 'orm', 'secrets' ],
				], $opts));
			},
			'cookie' => function(array $opts, $app) {
				return new \Bstage\Http\Cookie(array_merge([
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
				], $opts));
			},
			'db' => function(array $opts, $app) {
				return \Bstage\Db\Pdo::create(array_merge([
					'schemaFile' => $app->meta('base_dir') . '/config/db/schema.sql',
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
			'orm' => function(array $opts, $app) {
				return new \Bstage\Orm\Orm(array_merge([
					'app' => $app,
					'db' => $app->db,
					'config' => $app->config,
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
				return new \Bstage\Http\Session\Cookie(array_merge([
					'lifetime' => 3600,
					'cookie' => $app->cookie,
				], $opts));
			},
			'shortcodes' => function(array $opts, $app) {
				return new \Bstage\View\Shortcode\ShortcodeManager(array_merge([
					'app' => $app,
				], $opts));
			},
			'templates' => function(array $opts, $app) {
				return new \Bstage\View\Template(array_merge([
					'paths' => (function() use($app) {
						$paths = $app->meta('autoload_paths');
						foreach($paths as $k => $v) {
							$paths[$k] = dirname($v) . '/templates';
							if(strpos($paths[$k], '/modules') !== false || !is_dir($paths[$k])) {
								unset($paths[$k]);
							}
						}
						return $paths;
					})(),
					'callbacks' => [
						'url' => function($value=null, $query=null, $merge=false) use($app) {
							return $app->url($value, $query, $merge);
						},
						'asset' => function($value) use($app) {
							$theme = $app->config->get('site.theme');
							$path = 'templates/' . ($theme ? $theme . '/' : '') . $value;
							$time = @filemtime($app->meta('base_dir') . '/' . $path);
							return $app->url($path) . ($time ? '?' . $time : '');
						},
						'html' => function($tag, $name, $value='', array $opts=[]) use($app) {
							return $app->html->$tag($name, $value, $opts);
						},
						'pathInfo' => function() use($app) {
							return trim($app->request->getUri()->getPathInfo(), '/');
						},
						'auth' => function() use($app) {
							return isset($app->auth) ? $app->auth->id : null;
						},
					],
					'theme' => $app->config->get('site.theme'),
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
		//Event: create database schema
		$app->events->add('app.updating', function($event) use($app) {
			$app->db->createSchema();
		});
		//Event: add debug bar
		$app->events->add('app.response', function($event) use($app) {
			//is html response?
			if($event->response->getMediaType() !== 'html') {
				return;
			}
			//get output
			$output = $event->response->getContents();
			//show debug vars?
			if($app->meta('debug') && $event->request->getAttribute('primary')) {
				//debug vars
				$time = number_format(microtime(true) - BSTAGE_START_TIME, 5);
				$mem = number_format((memory_get_usage() - BSTAGE_START_MEM) / 1024, 0);
				$peak = number_format(memory_get_peak_usage() / 1024, 0);
				$queries = $app->db->getQueries();
				//debug data
				$debug  = '<div id="debug" style="width:100%; background:#f1f1f1; padding:10px; margin-top:30px; font-size:12px;">' . "\n";
				$debug .= '<div style="margin-bottom:5px;"><b>Debug bar</b></div>' . "\n";
				$debug .= '<div>Time: ' . $time . 's | Mem: ' . $mem . 'kb | Peak: ' . $peak . 'kb | Queries: ' . count($queries) . '</div>' . "\n";
				//db queries?
				if($queries) {
					$debug .= '<div style="margin-top:10px;"><b>Database queries</b></div>' . "\n";
					$debug .= '<ol style="margin:0; padding-left:15px;">' . "\n";
					foreach($queries as $q) {
						$debug .= '<li style="margin-top:5px;">' . $q . '</li>' . "\n";
					}
					$debug .= '</ol>' . "\n";
				}
				$debug .= '</div>' . "\n";
				//add before </body>
				if(stripos($output, '</body>') !== false) {
					$output = preg_replace('/<\/body>/i', $debug. '</body>', $output);
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