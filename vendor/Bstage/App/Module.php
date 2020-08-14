<?php

namespace Bstage\App;

class Module {

	protected $name;
	protected $dir;
	protected $version;
	protected $requires = [];

	protected $app;

	public function __construct(array $opts=[]) {
		//loop through opts
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//set module name?
		if(!$this->name) {
			$this->name = strtolower(explode('\\', get_class($this))[0]);
		}
		//load config
		$this->config();
		//check for update?
		if($to = $this->version) {
			//get stored version
			$from = $this->app->config->get("modules.$this->name.version", 0);
			//update now?
			if($to > $from) {
				//update module
				$this->update($from, $to);
				//update version
				$this->app->config->set("modules.$this->name.version", $to);
			}
		}
		//load templates
		$this->templates();
		//init hook
		$this->init();
		//load additional modules
		foreach($this->requires as $module => $opts) {
			$this->app->module($module, $opts);
		}
	}

	public function isAppModule() {
		return $this->name === $this->app->meta('name');
	}

	protected function config() {
		$this->app->config->mergePath($this->dir . '/config');
	}

	protected function update($from, $to) {
		$this->app->db->createSchema($this->dir . '/database/schema.sql');
	}

	protected function templates() {
		$this->app->templates->addPath($this->dir . '/templates');
	}

	protected function init() {

	}

}