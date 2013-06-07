<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\Assets;

use sly_App_Base;
use sly_Container;
use sly_Core;
use sly_I18N;
use sly_Request;
use sly_Response;
use sly_Router_Base;

class App extends sly_App_Base {
	const CONTROLLER_PARAM = 'slycontroller';  ///< string  the request param that contains the page
	const ACTION_PARAM     = 'slyaction';      ///< string  the request param that contains the action

	public function isBackend() {
		return false;
	}

	public function initialize() {
		$container = $this->getContainer();
		$config    = $container->getConfig();

		// add our own services
		$container['sly-service-asset'] = $container->share(function($container) {
			$service      = new Service($container['sly-dispatcher']);
			$lessCompiler = $container['sly-service-asset-lessphp'];
			$filePerm     = $container['sly-config']->get('fileperm') ?: 0644;
			$dirPerm      = $container['sly-config']->get('dirperm') ?: 0777;

			$service->addProcessListener(function($lessFile) use ($lessCompiler, $filePerm, $dirPerm) {
				if (!\sly_Util_String::endsWith($lessFile, '.less') || !file_exists($lessFile)) {
					return $lessFile;
				}

				$css     = $lessCompiler->process($lessFile);
				$dir     = SLY_TEMPFOLDER.'/sally/less-cache';
				$tmpFile = $dir.'/'.md5($lessFile).'.css';

				\sly_Util_Directory::create($dir, $dirPerm, true);

				file_put_contents($tmpFile, $css);
				chmod($tmpFile, $filePerm);

				return $tmpFile;
			});

			return $service;
		});

		$container['sly-service-asset-lessphp'] = $container->share(function($container) {
			$lessc    = new \lessc();
			$compiler = new Compiler\Lessphp($lessc);
			$config   = $container['sly-config'];

			$lessc->setFormatter('compressed');
			$lessc->registerFunction('asset', array($compiler, 'lessAssetFunction'));

			foreach ($config->get('less_import_dirs') as $includeDir) {
				$compiler->addImportDir(SLY_BASE.DIRECTORY_SEPARATOR.trim($includeDir, DIRECTORY_SEPARATOR));
			}

			return $compiler;
		});

		// init basic error handling
		$container->getErrorHandler()->init();

		// set timezone
		$this->setDefaultTimezone();

		// init a proper locale, even though we don't need one in the app (but an addOn might need it)
		if (!$container->has('sly-i18n')) {
			$locale = $config->get('default_locale', 'en_gb');
			$container->setI18N(new sly_I18N($locale, null, false));
		}

		// load static config
		$yamlReader = $container['sly-service-yaml'];
		$config->setStatic('/', $yamlReader->load(SLY_SALLYFOLDER.'/assets/config/static.yml'));

		// boot addOns
		if (!sly_Core::isSetup()) {
			sly_Core::loadAddOns();
		}

		// setup the stream wrappers
		$this->registerStreamWrapper();

		// register listeners
		sly_Core::registerListeners();
	}

	public function run() {
		try {
			// resolve URL and find controller
			$container = $this->getContainer();
			$this->performRouting($container->getRequest());

			// notify the addOns
			$this->notifySystemOfController();

			// do it, baby
			$dispatcher = $this->getDispatcher();
			$response   = $dispatcher->dispatch($this->controller, $this->action);
		}
		catch (\sly_Controller_Exception $e) {
			$response = new sly_Response('', 404);
		}
		catch (\Exception $e) {
			$response = new sly_Response('Internal Error', 500);
		}

		// send the response :)
		$response->send();
	}

	public function getControllerClassPrefix() {
		return 'sly\Assets\Controller';
	}

	public function getCurrentControllerName() {
		return $this->controller;
	}

	public function getCurrentAction() {
		return $this->action;
	}

	protected function getControllerFromRequest(sly_Request $request) {
		return $request->request(self::CONTROLLER_PARAM, 'string', 'asset');
	}

	protected function getActionFromRequest(sly_Request $request) {
		return $request->request(self::ACTION_PARAM, 'string', 'index');
	}

	protected function prepareRouter(sly_Container $container) {
		// use the basic router
		$router = new sly_Router_Base();

		// addOn assets
		$router->appendRoute(
			'/assets/addon/(?P<addon>[^/.]+/[^/.]+)/(?P<file>.+?)',
			array(self::CONTROLLER_PARAM => 'asset', self::ACTION_PARAM => 'addon')
		);

		// app assets
		$router->appendRoute(
			'/assets/app/(?P<app>[^/.]+)/(?P<file>.+?)',
			array(self::CONTROLLER_PARAM => 'asset', self::ACTION_PARAM => 'app')
		);

		// frontend (project) assets
		$router->appendRoute(
			'/assets/(?P<file>.+?)',
			array(self::CONTROLLER_PARAM => 'asset', self::ACTION_PARAM => 'project')
		);

		// mediapool
		$router->appendRoute(
			'/mediapool/(?P<file>.+?)',
			array(self::CONTROLLER_PARAM => 'asset', self::ACTION_PARAM => 'mediapool')
		);

		// let addOns extend our router rule set
		return $container->getDispatcher()->filter('SLY_ASSETS_ROUTER', $router, array('app' => $this));
	}

	/**
	 * get request dispatcher
	 *
	 * @return sly_Dispatcher
	 */
	protected function getDispatcher() {
		if ($this->dispatcher === null) {
			$this->dispatcher = new \sly_Dispatcher($this->getContainer(), $this->getControllerClassPrefix(), false);
		}

		return $this->dispatcher;
	}
}
