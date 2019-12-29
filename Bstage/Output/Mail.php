<?php

namespace Bstage\Output;

class Mail {

	protected $fromName = '';
	protected $fromEmail = '';

	protected $events = null;

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function send($to, $subject, $body, $from=null, $isHtml=null) {
		//set mail array
		$mail = array(
			'to' => $to,
			'subject' => trim($subject),
			'body' => trim($body),
			'from' => $from ?: $this->fromEmail,
			'name' => $this->fromName,
			'headers' => array(),
			'html' => $isHtml,
		);
		//mail event?
		if($this->events) {
			//EVENT: mail.send
			$event = $this->events->dispatch('mail.send', $mail);
			//update mail data
			$mail = $event->getParams();
		}
		//valid to address?
		if(!filter_var($mail['to'], FILTER_VALIDATE_EMAIL)) {
			throw new \Exception('Invalid to email address');
		}
		//valid from address?
		if(!filter_var($mail['from'], FILTER_VALIDATE_EMAIL)) {
			throw new \Exception('Invalid from email address');
		}
		//is html?
		if($mail['html'] === null) {
			$mail['html'] = strip_tags($mail['body']) !== $mail['body'];
		}
		//add lines breaks?
		if($mail['html'] && strip_tags($mail['body']) === strip_tags($mail['body'], '<p><br><div><table>')) {
			$mail['body'] = str_replace("\n", "\n<br>\n", $mail['body']);
		}
		//build headers
		$mail['headers'] = $this->buildHeaders($mail);
		//use safe mode?
		if(ini_get('safe_mode')) {
			return mail($mail['to'], $mail['subject'], $mail['body'], $mail['headers']);
		}
		//return with params
		return mail($mail['to'], $mail['subject'], $mail['body'], $mail['headers'], '-f' . $mail['from']);
	}

	protected function buildHeaders(array $mail) {
		//set vars
		$output = '';
		$headers = $mail['headers'];
		//set from header?
		if(!isset($headers['From']) || !$headers['From']) {
			if($mail['name']) {
				$headers['From'] = $mail['name'] . ' <' . $mail['from'] . '>';
			} else {
				$headers['From'] = $mail['from'];
			}
		}
		//set from header?
		if(!isset($headers['Reply-To']) || !$headers['Reply-To']) {
			$headers['Reply-To'] = $mail['from'];
		}
		//set mime header?
		if(!isset($headers['MIME-Version']) || !$headers['MIME-Version']) {
			if($mail['html']) {
				$headers['MIME-Version'] = '1.0';
			}
		}
		//set content type header?
		//set mime header?
		if(!isset($headers['Content-Type']) || !$headers['Content-Type']) {
			if($mail['html']) {
				$headers['Content-Type'] = 'text/html; charset=utf-8';
			} else {
				$headers['Content-Type'] = 'text/plain; charset=utf-8';
			}
		}
		//loop through headers
		foreach($headers as $k => $v) {
			$output .= ucfirst($k) . ': ' . $v . "\r\n";
		}
		//return
		return trim($output);
	}

}