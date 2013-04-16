<?php

/**
 * Froxlor API Core-Module
 *
 * PHP version 5
 *
 * This file is part of the Froxlor project.
 * Copyright (c) 2010- the Froxlor Team (see authors).
 *
 * For the full copyright and license information, please view the COPYING
 * file that was distributed with this source code. You can also view the
 * COPYING file online at http://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright  (c) the authors
 * @author     Froxlor team <team@froxlor.org> (2010-)
 * @license    GPLv2 http://files.froxlor.org/misc/COPYING.txt
 * @category   Modules
 * @package    API
 * @since      0.99.0
 */

/**
 * Class Core
 *
 * @copyright  (c) the authors
 * @author     Froxlor team <team@froxlor.org> (2010-)
 * @license    GPLv2 http://files.froxlor.org/misc/COPYING.txt
 * @category   Modules
 * @package    API
 * @since      0.99.0
 */
class Core extends FroxlorModule implements iCore {

	/**
	 * @see iCore::statusVersion()
	 *
	 * @return string version
	 */
	public static function statusVersion() {
		return ApiResponse::createResponse(
				200,
				null,
				array('version' => Froxlor::API_RELEASE_VERSION)
		);
	}

	/**
	 * @see iCore::statusApiVersion()
	 *
	 * @return string version
	 */
	public static function statusApiVersion() {
		return ApiResponse::createResponse(
				200,
				null,
				array('version' => Froxlor::getApiVersion())
		);
	}

	/**
	 * @see iCore::statusUpdate()
	 *
	 * @return string
	 */
	public static function statusUpdate() {

		// define URL to check
		$update_check_uri = 'http://version.froxlor.org/Froxlor/api/' . Froxlor::API_RELEASE_VERSION;

		if (!function_exists('curl_init')) {
			// awww, we can't check, just post where they shall go
			return ApiResponse::createResponse(
					404,
					array(
							"Could not find the PHP cURL extension to automatically check.",
							"Please visit '".$update_check_uri."/pretty' to check manually for a new version of froxlor."
					),
					null
			);
		} else {

			$ch = curl_init();
			// set url
			curl_setopt($ch, CURLOPT_URL, $update_check_uri);
			// no header in result
			curl_setopt($ch, CURLOPT_HEADER, 0);
			// return result
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			// timeout
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			// now check
			$latestversion = curl_exec($ch);
			// clear
			curl_close($ch);
			// split
			// 0 => version
			// 1 => info-link
			// 2 => whether it's a testing-version or not
			// 3 => extra message
			$_vinfo = explode('|', $latestversion);

			$result_data = array(
					'version' => Froxlor::API_RELEASE_VERSION,
					'online_version' => $_vinfo[0],
					'online_info' => $_vinfo[1],
					'online_extra_message' => (isset($_vinfo[3]) && $_vinfo[3] != '' ? $_vinfo[3] : null),
					'is_newer' => (Module::cmpFroxlorVersions(Froxlor::API_RELEASE_VERSION, $_vinfo[0]) == -1 ? 1 : 0),
					'is_testing' => ((int)$_vinfo[2] == 1 ? 1 : 0),
			);

			// call beforeReturn hooks
			Hooks::callHooks('statusUpdate_beforeReturn', $result_data);

			// create response
			return ApiResponse::createResponse(
					200,
					null,
					$result_data
			);
		}
	}

	/**
	 * @see iCore::statusSystem()
	 *
	 * @return array
	 */
	public static function statusSystem() {

		$user = self::getParam('_userinfo');
		$resp = Froxlor::getApi()->apiCall(
				'Permissions.statusUserPermission',
				array('userid' => $user->id, 'ident' => 'Core.view_statusSystem')
		);

		if ($resp->getResponseCode() != '200') {
			throw new ApiException(403, 'You are not allowed to access this function');
		}

		// PHP version
		$phpversion = phpversion();
		// PHP memory limit
		$phpmemorylimit = @ini_get("memory_limit");
		// PHP SAPI
		$webserverinterface = strtoupper(@php_sapi_name());

		// get PDO Object
		$pdo = Database::getDatabaseAdapter()->getDatabase();
		// DB Type
		$dbtype = $pdo->getDatabaseType();
		// DB Version
		$dbversion = $pdo->getDatabaseVersion();

		// load average
		if (function_exists('sys_getloadavg')) {
			$loadArray = sys_getloadavg();
			$load = number_format($loadArray[0], 2, '.', '') . " / " . number_format($loadArray[1], 2, '.', '') . " / " . number_format($loadArray[2], 2, '.', '');
		} else {
			$load = @file_get_contents('/proc/loadavg');
			if (!$load) {
				$load = 'unknown';
			}
		}

		// Kernel
		if (function_exists('posix_uname')) {
			$kernel_nfo = posix_uname();
			$kernel = $kernel_nfo['release'] . ' (' . $kernel_nfo['machine'] . ')';
		} else {
			$kernel = 'unknown';
		}

		// Server uptime
		$uptime_array = explode(" ", @file_get_contents("/proc/uptime"));

		if (is_array($uptime_array)
				&& isset($uptime_array[0])
				&& is_numeric($uptime_array[0])
		) {
			// Some calculatioon to get a nicly formatted display
			$seconds = round($uptime_array[0], 0);
			$minutes = $seconds / 60;
			$hours = $minutes / 60;
			$days = floor($hours / 24);
			$hours = floor($hours - ($days * 24));
			$minutes = floor($minutes - ($days * 24 * 60) - ($hours * 60));
			$seconds = floor($seconds - ($days * 24 * 60 * 60) - ($hours * 60 * 60) - ($minutes * 60));
			$uptime = "{$days}d, {$hours}h, {$minutes}m, {$seconds}s";
			// Just cleanup
			unset($uptime_array, $seconds, $minutes, $hours, $days);
		} else {
			$uptime = 'unknown';
		}

		$result_data = array(
				'froxlor_version' => Froxlor::API_RELEASE_VERSION,
				'php_version' => $phpversion,
				'php_sapi' => $webserverinterface,
				'php_memorylimit' => $phpmemorylimit,
				'db_type' => $dbtype,
				'db_version' => $dbversion,
				'server_load' => $load,
				'server_kernel' => $kernel,
				'server_uptime' => $uptime
		);

		// call beforeReturn hooks
		Hooks::callHooks('statusSystem_beforeReturn', $result_data);

		return ApiResponse::createResponse(
				200, null,
				$result_data
		);
	}

	/**
	 * @see iCore::listApiFunctions()
	 *
	 * @param string $module optional only list functions of specific module
	 *
	 * @throws CoreException
	 * @return array
	 */
	public static function listApiFunctions() {

		$module = self::getParam('module', true, null);

		$functions = array();
		if ($module != null) {
			// check for existence
			Module::requireModules($module);
			// now get all static functions
			$reflection = new ReflectionClass($module);
			$_functions = $reflection->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC);
			foreach ($_functions as $func) {
				if ($func->class == $module) {
					array_push($functions, array(
					'function' => $func->name,
					'module' => $func->class
					));
				}
			}
		} else {
			// check all the modules
			$path = FROXLOR_API_DIR . '/modules/';
			// valid directory?
			if (is_dir($path)) {
				// create RecursiveIteratorIterator
				$its = new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator($path)
				);
				// check every file
				foreach ($its as $fullFileName => $it ) {
					// does it match the Filename pattern?
					$matches = array();
					if (preg_match("/^module\.(.+)\.php$/i", $it->getFilename(), $matches)) {
						// check for existence
						Module::requireModules($matches[1]);
						// now get all static functions
						$reflection = new ReflectionClass($matches[1]);
						$_functions = $reflection->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC);
						foreach ($_functions as $func) {
							if ($func->class == $matches[1]) {
								array_push($functions, array(
								'function' => $func->name,
								'module' => $func->class
								));
							}
						}
					}
				}
			} else {
				// yikes - no valid directory to check
				throw new CoreException(500, "Cannot search directory '".$path."'. No such directory.");
			}
		}

		// return the list
		return ApiResponse::createResponse(200, null, $functions);
	}

	/**
	 * @see iCore::doSetup();
	 *
	 * @return null
	 */
	public static function doSetup() {
		// call the hook to run Core_moduleSetup on all modules
		Hooks::callHooks('Core_moduleSetup');
	}

	/**
	 * (non-PHPdoc)
	 * @see FroxlorModule::Core_moduleSetup()
	 */
	public function Core_moduleSetup() {

		/**
		 * caution: this is test-code
		 * and just inserts a few entities so
		 * we can test the stuff :P
		 */

		// settings
		$set = Database::dispense('settings');
		$set->module = 'Core';
		$set->section = 'system';
		$set->name = 'dbversion';
		$set->value = '1';
		$setid = Database::store($set);

		// core permissions
		$perm = Database::dispense('permissions');
		$perm->module = 'Core';
		$perm->name = 'view_statusSystem';
		$permid1 = Database::store($perm);

		$perm = Database::dispense('permissions');
		$perm->module = 'Core';
		$perm->name = 'useAPI';
		$permid2 = Database::store($perm);

		// resources
		$res = Database::dispense('resources');
		$res->module = 'Core';
		$res->resource = 'maxloginattempts';
		$res->default = 3;
		$resid = Database::store($res);

		// default groups
		$sagroup = Database::dispense('groups');
		$sagroup->groupname = '@superadmin';
		$sagroup->sharedPermissions = array(Database::load('permissions', $permid1), Database::load('permissions', $permid2));
		$sagroupid = Database::store($sagroup);

		$dgroup = Database::dispense('groups');
		$dgroup->groupname = '@deactivated';
		Database::store($dgroup);

		// user-resource-limits
		$ur = Database::dispense('limits');
		$ur->resourceid = $resid;
		$ur->limit = 3;
		$ur->inuse = 0;
		$urid = Database::store($ur);

		// default user
		$user = Database::dispense('users');
		$user->apikey = 'mysupersecretkey';
		$user->name = 'superadmin';
		$user->sharedGroups = array(Database::load('groups', $sagroupid));
		$user->ownLimits = array(Database::load('limits', $urid));
		Database::store($user);
	}
}
