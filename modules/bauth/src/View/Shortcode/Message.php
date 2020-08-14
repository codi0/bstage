<?php

namespace Bauth\View\Shortcode;

class Message {

	public function parse(array $params, $app) {
		//set vars
		$html = '';
		$msg = trim(isset($params['msg']) ? $params['msg'] : $app->input('msg', 'GET'));
		//show message?
		if(!empty($msg)) {
			$html .= '<div class="message">' . "\n";
			$html .= $msg . "\n";
			$html .= '</div>' . "\n";
		}
		//return
		return $html;
	}

}