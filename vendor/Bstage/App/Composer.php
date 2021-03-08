<?php

namespace Bstage\App;

/**
 * Composer Dependency Manager
 *
 * Manage php library dependencies via a web browser, using https://getcomposer.org
 *
 * <code>
 * $composer = new \Bstage\Composer\Composer;
 * $composer->installDeps();
 * </code>
 */
class Composer {

    /**
     * Minimum php version required
     * @var string
     */
	const MIN_PHP = '5.3.2';

    /**
     * Minimum ioncube loader version required
     * @var string
     */
	const MIN_IONCUBE = '4.0.9';

    /**
     * Is production environment?
     * @var boolean
     */
	protected $isProduction = false;

    /**
     * Base directory for composer install
     * @var string
     */
	protected $baseDir = '.';

    /**
     * Local path to Composer phar
     * @var string
     */
	protected $localPharPath = '%composer_dir%/composer.phar';

    /**
     * Remote path to Composer phar
     * @var string
     */
	protected $remotePharPath = 'https://getcomposer.org/composer.phar';

    /**
     * Composer application class
     * @var string
     */
	protected $composerClass = 'Composer\Console\Application';

    /**
     * Composer environment vars
     * @var array
     */
	protected $env = array(
		'COMPOSER_VENDOR_DIR' => '%base_dir%/vendor',
		'COMPOSER_HOME' => '%vendor_dir%/composer',
		'COMPOSER_BIN_DIR' => '%composer_dir%/bin',
		'COMPOSER_CACHE_DIR' => '%composer_dir%/cache',
		'COMPOSER' => '%base_dir%/composer.json',
		'COMPOSER_PROCESS_TIMEOUT' => null,
		'COMPOSER_DISCARD_CHANGES' => null,
		'COMPOSER_NO_INTERACTION' => null,
	);

    /**
     * List of production args
     * @var array
     */
	protected $productionArgs = array(
		'install' => array( '--no-dev', '--prefer-dist', '--optimize-autoloader' ),
		'update' => array( '--no-dev', '--prefer-dist', '--optimize-autoloader' ),
		'dump-autoload' => array( '--no-dev', '--optimize' ),
		'create-project' => array( '--prefer-dist' ),
	);

    /**
     * Constructor - sets object properties
     *
     * @param  array $opts
     * @return void
     */
	public function __construct(array $opts=array()) {
		//set opts
		foreach($opts as $k => $v) {
			//property exists?
			if(!isset($this->$k) && !property_exists($this, $k)) {
				continue;
			}
			//is array?
			if($this->$k === (array) $this->$k) {
				$this->$k = array_merge($this->$k, $v);
			} else {
				$this->$k = $v;
			}
		}
		//disable detect unicode
		@ini_set('detect_unicode', 0);
		//standardise env vars
		foreach($this->env as $k => $v) {
			//replace keys
			$this->env[$k] = str_replace(
				array( '%base_dir%', '%vendor_dir%', '%composer_dir%', '\\' ),
				array( $this->baseDir, $this->env['COMPOSER_VENDOR_DIR'], $this->env['COMPOSER_HOME'], '/' ),
				$v
			);
			//set value?
			if($this->env[$k] !== '') {
				putenv($k . "=" . $this->env[$k]);
			}
		}
		//standardise local path
		$this->localPharPath = str_replace(
			array( '%base_dir%', '%vendor_dir%', '%composer_dir%', '\\' ),
			array( $this->baseDir, $this->env['COMPOSER_VENDOR_DIR'], $this->env['COMPOSER_HOME'], '/' ),
			$this->localPharPath
		);
	}

    /**
     * Returns list of installed packages
	 *
     * @return array
     */
	public function getPackages() {
		//set vars
		$packages = array();
		$lockFile = str_replace('.json', '.lock', $this->env['COMPOSER']);
		//lock file found?
		if($json = $this->getFileContents($lockFile)) {
			//decode json
			$json = json_decode($json, true);
			//add package names
			foreach($json['packages'] as $p) {
				$packages[$p['name']] = $this->env['COMPOSER_VENDOR_DIR'] . '/' . $p['name'];
			}
		}
		//return
		return $packages;
	}

    /**
     * Quick setup for composer install
     *
	 * @param  string $destDir
	 * @param  boolean $clearCache
     * @return array|null
     */
	public function setup($destDir='', $clearCache=false) {
		//set vars
		$composerFile = $this->env['COMPOSER'];
		$lockFile = str_replace('.json', '.lock', $composerFile);
		//has composer.json?
		if(!is_file($composerFile)) {
			return null;
		}
		//has up to date lock file?
		if(is_file($lockFile)) {
			if(filemtime($lockFile) >= filemtime($composerFile)) {
				return null;
			}
		}
		//run install
		$res = $this->installDeps();
		//move to dir?
		if($destDir) {
			$this->moveDeps($destDir);
		}
		//clear cache?
		if($clearCache) {
			$this->clearCache();
		}
		//touch lock
		touch($lockFile);
		//return
		return $res;
	}

    /**
     * Installs packages defined in composer.json
     *
     * @param  array $args
     * @return array
     */
	public function installDeps(array $args=array()) {
		return $this->cmd('install', $args);
	}

    /**
     * Updates packages to their latest version
     *
     * @param  array $args
     * @return array
     */
	public function updateDeps(array $args=array()) {
		return $this->cmd('update', $args);
	}

    /**
     * Moves dependencies to destination directory
     *
     * @param  string $destDir
     * @return void
     */
	public function moveDeps($destDir) {
		//loop through packages
		foreach($this->getPackages() as $name => $dir) {
			//paths found?
			if(!$paths = glob($dir . '/src/*', GLOB_ONLYDIR)) {
				continue;
			}
			//loop through paths
			foreach($paths as $path) {
				$pathName = str_replace(dirname($path) . '/', '', $path);
				rename($path, $destDir . '/' . $pathName);
			}
			//up one?
			if(strpos($name, '/') !== false) {
				$dir = dirname($dir);
			}
			//delete dir
			$this->rmDir($dir);
		}
	}

    /**
     * Builds composer autoloader
     *
     * @param  boolean $optimize
     * @return array
     */
	public function buildAutoloader($optimize=false) {
		//set args
		$args = $optimize ? array( '--optimize' ) : array();
		//execute
		return $this->cmd('dump-autoload', $args);
	}

    /**
     * Validates composer.json file
     *
     * @return array
     */
	public function validateJson() {
		return $this->cmd('validate');
	}

    /**
     * Creates project
     *
     * @param  string $package
     * @param  string $path
     * @param  string $version
     * @return array
     */
	public function createProject($package=null, $path=null, $version=null) {
		//set path?
		if($package && !$path) {
			$path = explode('/', $package);
			$path = $path[0];
		}
		//execute
		return $this->cmd('create-project', array( $package, $path, $version ));
	}

    /**
     * Archives project
     *
     * @param  string $format
     * @param  string $toDir
     * @return array
     */
	public function archiveProject($format=null, $toDir=null) {
		//set args
		$format = '--format=' . ($format ? $format : 'zip');
		$toDir = '--dir=' . ($toDir ? $toDir : '.');
		$args = array( $format, $toDir );
		//execute
		return $this->cmd('archive', $args);
	}

    /**
     * Finds package information
     *
     * @param  string $package
     * @param  boolean $nameOnly
     * @return array
     */
	public function findPackage($package, $nameOnly=false) {
		//set args
		$args = array( $package );
		//name only search?
		if($nameOnly) $args[] = '--only-name';
		//execute
		return $this->cmd('search', $args);
	}

    /**
     * Shows package information
     *
     * @param  string $package
     * @param  string $version
     * @param  boolean $installedOnly
     * @return array
     */
	public function showPackage($package=null, $version=null, $installedOnly=false) {
		//set args
		$args = array( $package, $version );
		//installed only?
		if($installedOnly) $args[] = '--installed';
		//execute
		return $this->cmd('show', $args);
	}

    /**
     * Finds local changes in packages
     *
     * @param  boolean $verbose
     * @return array
     */
	public function findLocalChanges($verbose=true) {
		//set args
		$args = $verbose ? array( '--verbose' ) : array();
		//execute
		return $this->cmd('status', $args);
	}

    /**
     * Updates composer phar
     *
     * @param  boolean $rollback
     * @return array
     */
	public function updateComposer($rollback=false) {
		//set args
		$args = $rollback ? array( '--rollback' ) : array();
		//execute
		return $this->cmd('self-update', $args);
	}

    /**
     * Clears composer cache
     *
     * @return boolean
     */
	public function clearCache() {
		return $this->rmDir($this->env['COMPOSER_CACHE_DIR']);
	}

    /**
     * Executes composer command
     *
     * @param  string $cmd
     * @param  array $args
     * @return array
     */
	public function cmd($cmd, array $args=array()) {
		//set vars
		$cmd = str_replace('_', '-', trim($cmd));
		$args = $this->formatArgs($args, $cmd);
		$tmpArgv = isset($GLOBALS['argv']) ? $GLOBALS['argv'] : array();
		//store current limits
		$ia = @ignore_user_abort();
		$ml = @ini_get('memory_limit');
		$tl = @ini_get('max_execution_time');
		//update limits
		@ignore_user_abort(true);
		@ini_set('memory_limit', '512M');
		@ini_set('max_execution_time', 0);
		//create dir?
		if(!is_dir($this->env['COMPOSER_HOME'])) {
			mkdir($this->env['COMPOSER_HOME'], 0755, true);
		}
		//init composer?
		if(!class_exists($this->composerClass)) {
			$this->initValidate()->initDownload()->initBootstrap();
		}
		//update argv
		$GLOBALS['argv'] = $_SERVER['argv'] = explode(' ', $this->localPharPath . ' ' . $cmd . ($args ? ' ' . implode(' ', $args) : ''));
		$GLOBALS['argc'] = $_SERVER['argc'] = count($_SERVER['argv']);
		//start composer app
		$app = new $this->composerClass;
		//no exit allowed
		$app->setAutoExit(false);
		//run app
		$code = $app->run();
		//reset argv
		$GLOBALS['argv'] = $_SERVER['argv'] = $tmpArgv;
		$GLOBALS['argc'] = $_SERVER['argc'] = count($tmpArgv);
		//reset limits
		@ignore_user_abort($ia);
		@ini_set('memory_limit', $ml);
		@ini_set('max_execution_time', $tl);
		//return
		return array(
			'cmd' => $cmd,
			'args' => $args,
			'code' => (int) $code,
			'success' => empty($code),
		);
	}

    /**
     * Validates minimum requirements
     *
     * @return $this
     */
	protected function initValidate() {
		//set vars
		$errors = array();
		//compatible php version?
		if(version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
			$errors['php'] = 'This server is running PHP ' . PHP_VERSION . '. You must upgrade to ' . self::MIN_PHP . ' or higher, to continue.';
		}
		//compatible ioncube version?
		if($this->fnEnabled('ioncube_loader_version') && version_compare(ioncube_loader_version(), self::MIN_IONCUBE, '<')) {
			$errors['ioncube'] = 'This server is running ioncube loader ' . ioncube_loader_version() . '. You must upgrade to ' . self::MIN_IONCUBE . ' or higher, to continue.';
		}
		//compatible suhosin extension?
		if(extension_loaded('suhosin')) {
			//get whitelist & blacklist
			$wl = @ini_get('suhosin.executor.include.whitelist');
			$bl = @ini_get('suhosin.executor.include.blacklist');
			//match found?
			if(($wl && stripos($wl, 'phar') === false) || (!$wl && stripos($bl, 'phar') !== false)) {
				$errors['suhosin'] = 'This server is running the suhison PHP extension. You must add \'phar\' to suhosin.executor.include.whitelist, to continue.';
			}
		}
		//can request remote files?
		if(!@ini_get('allow_url_fopen') && !$this->fnEnabled('curl_init')) {
			$errors['remote'] = 'This server cannot request files remotely. You must enable \'allow_url_fopen\' in php.ini or install CURL, to continue.';
		}
		//show errors?
		if(count($errors) > 0) {
			//intro message
			echo '<p><b>Errors detected, unable to continue using composer:</b></p>' . "\n";
			//loop through array
			foreach($errors as $err) {
				echo '<p>' . $err . '</p>' . "\n";
			}
			//stop
			exit();
		}
		//chain it
		return $this;
	}

    /**
     * Downloads composer phar
     *
     * @return $this
     */
	protected function initDownload() {
		//can download?
		if(!is_file($this->localPharPath) && $data = $this->getFileContents($this->remotePharPath)) {
			//get dir
			$dir = dirname($this->localPharPath);
			//create dir?
			if($dir && !is_dir($dir)) {
				mkdir($dir, 0755, true);
			}
			//create file
			file_put_contents($this->localPharPath, $data, LOCK_EX);
		}
		//chain it
		return $this;
	}

    /**
     * Loads composer phar bootstrap file
     *
     * @return $this
     */
	protected function initBootstrap() {
		//load bootstrap file?
		if(!class_exists($this->composerClass)) {
			require_once('phar://' . $this->localPharPath . '/src/bootstrap.php');
		}
		//chain it
		return $this;
	}

    /**
     * Returns file content (local or remote)
     *
     * @param  string $path
	 * @param  mixed $default
     * @return mixed
     */
	protected function getFileContents($path, $default=null) {
		//set vars
		$timeout = 5;
		$redirects = 5;
		$ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		//local file?
		if(strpos($path, '://') === false) {
			return ($path && is_file($path)) ? file_get_contents($path) : $default;
		}
		//use CURL?
		if($this->fnEnabled('curl_init')) {
			//use curl
			$ch = curl_init();
			//set options
			curl_setopt_array($ch, array(
				CURLOPT_URL => $path,
				CURLOPT_USERAGENT => $ua,
				CURLOPT_TIMEOUT => $timeout,
				CURLOPT_MAXREDIRS => $redirects,
				CURLOPT_HEADER => false,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_SSL_VERIFYPEER => false,
			));
			//make request
			$res = curl_exec($ch);
			//close
			curl_close($ch);
			//return
			return $res;
		}
		//use file get contents
		return file_get_contents($path, false, stream_context_create(array(
			'http' => array(
				'timeout' => $timeout,
				'user_agent' => $ua,
			),
		)));
	}

    /**
     * Formats input args
     *
	 * @param  array $args
     * @param  string $cmd

     * @return array
     */
	protected function formatArgs(array $args, $cmd=null) {
		//add production args?
		if($cmd && $this->isProduction && isset($this->productionArgs[$cmd])) {
			//loop through array
			foreach($this->productionArgs[$cmd] as $a) {
				$args[] = $a;
			}
		}
		//clean args
		return array_map(function($str) {
			return trim(preg_replace('/\s+/', ' ', $str));
		}, $args);
	}

    /**
     * Removes directory (recursive)
     *
     * @param  string $dir
     * @return boolean
     */
	protected function rmDir($dir) {
		//dir exists?
		if(!is_dir($dir)) {
			return true;
		}
		//create iterator
		$iterator = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
		//loop through array
		foreach(new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
			if($file->isDir()) {
				rmdir($file->getPathname());
			} else {
				unlink($file->getPathname());
			}
		}
		//return
		return (bool) rmdir($dir);
	}

    /**
     * Checks if function enabled
     *
     * @param  string $fn
     * @return boolean
     */
	protected function fnEnabled($fn) {
		//set vars
		$disabled = array();
		//loop through ini get keys
		foreach(array( 'disable_functions', 'suhosin.executor.func.blacklist' ) as $k) {
			//loop through functions
			foreach(explode(',', @ini_get($k)) as $f) {
				if($f = trim($f)) {
					$disabled[] = $f;
				}
			}
		}
		//return
		return function_exists($fn) && !in_array($fn, $disabled);
	}

}