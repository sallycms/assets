<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\Asset\Controller\Asset;

use Gaufrette\Util\Path;
use sly_Response;

class Asset extends Base {
	public function addonAction() {
		$request   = $this->getRequest();
		$container = $this->getContainer();
		$addon     = $request->get('addon', 'string', null);
		$file      = $this->normalizePath($request->get('file', 'string', null));

		// validate the addOn status

		if (mb_strlen($addon) === 0) {
			return new sly_Response('no addOn given', 400);
		}

		$addonService = $container['sly-service-addon'];

		if (!$addonService->isInstalled($addon)) {
			return new sly_Response('no addOn given', 400);
		}

		// validate the filename

		if (mb_strlen($file) === 0) {
			return new sly_Response('no file given', 400);
		}

		$pkgService = $container['sly-service-package-addon'];
		$fullPath   = $pkgService->baseDirectory($addon).'assets/'.$file;

		// and send the file

		return $this->sendFile($fullPath, true);
	}

	public function appAction() {
		$request = $this->getRequest();
		$app     = $this->normalizePath($request->get('app', 'string', null));
		$file    = $this->normalizePath($request->get('file', 'string', null));

		// validate the app

		if (mb_strlen($app) === 0) {
			return new sly_Response('no app given', 400);
		}

		$appDir = SLY_BASE.'/'.basename($app); // basename is just to make sure there are no path jumps in there

		if (!is_dir($appDir) || !is_file($appDir.'/composer.json')) {
			return new sly_Response('no app given', 400);
		}

		// validate the filename

		if (mb_strlen($file) === 0) {
			return new sly_Response('no file given', 400);
		}

		// and send the file

		return $this->sendFile($appDir.'/assets/'.$file, true);
	}

	public function projectAction() {
		$request = $this->getRequest();
		$file    = $this->normalizePath($request->get('file', 'string', null));

		// validate the filename

		if (mb_strlen($file) === 0) {
			return new sly_Response('no file given', 400);
		}

		// and send the file

		return $this->sendFile(SLY_BASE.'/assets/'.$file, true);
	}

	public function mediapoolAction() {
		$request = $this->getRequest();
		$file    = $this->normalizePath($request->get('file', 'string', null));

		// validate the filename

		if (mb_strlen($file) === 0) {
			return new sly_Response('no file given', 400);
		}

		// and send the file

		return $this->sendFile('sly://media/'.$file, false);
	}
}
