<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\Assets;

class Util {
	private static $baseUrl;
	private static $appName;

	public static function appUri($filename, $app = null) {
		return self::getBaseUrl().'assets/app/'.($app ?: self::getAppName()).'/'.$filename;
	}

	public static function addOnUri($addOn, $filename) {
		return self::getBaseUrl().'assets/addon/'.$addOn.'/'.$filename;
	}

	public static function assetUri($filename) {
		return self::getBaseUrl().'assets/'.$filename;
	}

	public static function mediapoolUri($filename) {
		return self::getBaseUrl().'mediapool/'.$filename;
	}

	public static function clearAppCache() {
		self::$baseUrl = null;
		self::$appName = null;
	}

	private static function getBaseUrl() {
		if (self::$baseUrl === null) {
			$appBase = trim(\sly_Core::getContainer()->get('sly-app-baseurl'), '/');
			$base    = '';

			if ($appBase !== '') {
				$steps = count(explode('/', $appBase));
				$base  = implode('/', array_fill(0, $steps, '..')).'/';
			}

			self::$baseUrl = $base;
		}

		return self::$baseUrl;
	}

	private static function getAppName() {
		if (self::$appName === null) {
			self::$appName = \sly_Core::getContainer()->get('sly-app-name');
		}

		return self::$appName;
	}
}
