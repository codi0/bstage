<?php

namespace Bstage\View\Shortcode;

class Search extends AbstractShortcode {

	public function parse(array $params) {
		//set defaults
		$params = array_merge([
			'name' => '',
			'placeholder' => '',
		], $params);
		//valid name?
		if(!$params['name']) {
			throw new \Exception("Search shortcode requires a name parameter");
		}
		//create form
		$form = $this->app->form('search-' . $params['name'], [
			'method' => 'GET',
		]);
		//add fields
		$form->input('q', [ 'validate' => 'xss', 'label' => '', 'placeholder' => $params['placeholder'] ]);
		$form->submit('Search ' . $params['name']);
		//return
		return $form;	
	}

}