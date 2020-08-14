<?php

//PSR-17 compatible (without interfaces)

namespace Bstage\Http\Message;

class Factory {

    public function createRequest($method, $uri) {
		return new Request($method, $uri);
	}

	public function createResponse($code=200, $reasonPhrase='') {
		return new Response($code, [], null, '1.1', $reasonPhrase);
	}

	public function createStream($content='') {
		return new Stream($content);
	}

	public function createStreamFromFile($filename, $mode='r') {
		if(!$resource = @fopen($filename, $mode)) {
			throw new \Exception('Failed to open ' . $filename);
		}
		return new Stream($resource);
	}

    public function createStreamFromResource($resource) {
		return new Stream($resource);
	}

    public function createUploadedFile($stream, $size=null, $error=\UPLOAD_ERR_OK, $clientFilename=null, $clientMediaType=null) {
		if($size === null) {
			$size = $stream->getSize();
		}
		return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
	}

	public function createUri($uri='') {
		return new Uri($uri);
	}

	public function createServerRequest($method, $uri, array $serverParams=[]) {
		return new ServerRequest($method, $uri, [], null, '1.1', $serverParams);
	}

	public function createFromGlobals($class) {
		static $cached = [];
		if(strpos($class, '\\') === false) {
			$class = __NAMESPACE__ . '\\' . $class;
		}
		if(!isset($cached[$class])) {
			$method = __FUNCTION__;
			if(!method_exists($class, $method)) {
				throw new \Exception('Class ' . $class . ' does not contain ' . $method . ' method');
			}
			$cached[$class] = $class::$method();
		}
		return $cached[$class];
	}

}