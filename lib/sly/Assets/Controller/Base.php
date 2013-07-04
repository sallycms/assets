<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\Assets\Controller;

use Gaufrette\Util\Path;
use sly\Assets\Service;
use sly_Authorisation_Exception;
use sly_Exception;
use sly_Response;
use sly_Util_Directory;
use sly_Util_String;

abstract class Base extends \sly_Controller_Frontend_Base {
	protected function sendFile($file, $process, $useExtensionBlacklist, $checkPermissions) {
		if (!is_file($file)) {
			$response = new sly_Response('File not found.', 404);
			$response->setExpires(time()-24*3600);
			$response->setContentType('text/plain', 'UTF-8');

			return $response;
		}

		$container = $this->getContainer();
		$request   = $this->getRequest();
		$config    = $container->getConfig();
		$service   = $container->getAssetService();
		$configs   = $this->getConfig();

		try {
			// check if the filetype may be accessed at all
			// and check if the file is inside an allowed path

			if ($useExtensionBlacklist) {
				$this->checkForBlockedExtensions($config, $file);
			}

			$isProtected = $checkPermissions ? $this->checkFilePermission($service, $file) : false;

			// "clear" any errors that might came up when detecting the timezone
			// or, more likely, Gaufrette's suppressed error from unregistering
			// the sly:// protocol.

			if (error_get_last()) @trigger_error('', E_USER_NOTICE);
			$errorLevel = error_reporting(0);

			// check the ETag

			$type = \sly_Util_File::getMimetype($file);
			$etag = $configs['etag'] && file_exists($file) ? md5_file($file) : null;

			if ($etag) {
				$etags = $request->getETags();

				if (in_array('"'.$etag.'"', $etags)) {
					$response = new sly_Response('Not modified', 304);
					$this->setResponseHeaders($configs, $response, $etag, $type);

					return $response;
				}
			}

			// optionally process the file

			$resultFile = $process ? $service->compile($file) : $file;
			$lastError  = error_get_last();

			error_reporting($errorLevel);

			if (!empty($lastError) && mb_strlen($lastError['message']) > 0) {
				throw new sly_Exception($lastError['message'].' in '.$lastError['file'].' on line '.$lastError['line'].'.');
			}

			// prepare the response

			$type     = \sly_Util_File::getMimetype($resultFile); // the type might have changed during processing
			$response = new \sly_Response_Stream($resultFile, 200);
			$response->setContentType($type, 'UTF-8');

			$this->setResponseHeaders($configs, $response, $etag, $type);

			// if the file is protected, mark the response as private

			if ($isProtected) {
				if ($response->hasCacheControlDirective('public')) {
					$response->removeCacheControlDirective('public');
				}

				$response->addCacheControlDirective('private');
			}
		}
		catch (\Exception $e) {
			$response = new sly_Response();

			if ($e instanceof sly_Authorisation_Exception) {
				$response->setStatusCode(403);
				$response->addCacheControlDirective('private');
			}
			else {
				$response->setStatusCode(500);
			}

			if ($container->get('sly-environment') === 'dev' || $e instanceof sly_Authorisation_Exception) {
				$response->setContent($e->getMessage());
			}
			else {
				$response->setContent('Error while processing asset.');
			}

			$response->setExpires(time()-24*3600);
			$response->setContentType('text/plain', 'UTF-8');
		}

		$hash = $request->get('t', 'string');

		if ($hash) {
			$filehasher = $container['sly-filehasher'];

			if ($filehasher->hash($file) === $hash) {
				// cache 1 week if hash matching
				$response->addCacheControlDirective('max-age', 60 * 60 * 24 * 7);
			}
			else {
				// cache short time if hash not matching
				$response->addCacheControlDirective('max-age', 10);
			}
		}

		return $response;
	}

	protected function checkForBlockedExtensions(\sly_Configuration $config, $file) {
		$blocked = $config->get('blocked_extensions');

		foreach ($blocked as $ext) {
			if (sly_Util_String::endsWith($file, $ext)) {
				throw new sly_Authorisation_Exception('Forbidden');
			}
		}
	}

	protected function getConfig() {
		$config = $this->getContainer()->getConfig()->get('assets', array());

		return array_merge(array(
			'etag'          => true,
			'expires'       => null,
			'cache-control' => array('default' => array('max-age' => 29030401))
		), $config);
	}

	protected function checkFilePermission(Service $service, $file) {
		$isProtected = $service->isProtected($file);

		if ($isProtected && !$service->checkPermission($file)) {
			throw new sly_Authorisation_Exception('access forbidden');
		}

		return $isProtected;
	}

	protected function setResponseHeaders(array $config, sly_Response $response, $etag = null, $type = null) {
		$cacheControl = $config['cache-control'];

		$this->addCacheControlDirective($cacheControl, 'default', $response);

		// add type-specific cache-control headers

		if ($type) {
			$type = explode('/', $type, 2);

			$this->addCacheControlDirective($cacheControl, $type[0], $response);

			if (count($type) == 2) {
				$this->addCacheControlDirective($cacheControl, $type[0].'_'.$type[1], $response);
			}
		}

		$now     = time();
		$expires = $config['expires'];

		$response->setLastModified($now);

		if (is_int($expires)) {
			$response->setExpires($now + $expires);
		}

		if ($etag) {
			$response->setEtag($etag);
		}
	}

	protected function addCacheControlDirective(array $cacheControl, $key, sly_Response $response) {
		if (isset($cacheControl[$key])) {
			foreach ($cacheControl[$key] as $k => $value) {
				$response->addCacheControlDirective($k, $value);
			}
		}
	}

	protected function normalizePath($path) {
		$path = sly_Util_Directory::normalize($path);
		$path = str_replace('..', '', $path);
		$path = str_replace(DIRECTORY_SEPARATOR, '/', sly_Util_Directory::normalize($path));
		$path = str_replace('./', '/', $path);
		$path = str_replace(DIRECTORY_SEPARATOR, '/', sly_Util_Directory::normalize($path));

		if (empty($path)) {
			return '';
		}

		if ($path[0] === '/') {
			$path = substr($path, 1);
		}

		return $path;
	}
}
