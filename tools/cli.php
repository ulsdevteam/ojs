<?php

/**
 * @file tools/GenerateMetricsTool.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GenerateMetricsTool
 * @ingroup tools
 *
 * @brief CLI tool to generate fake metrics data
 */

require(dirname(__FILE__) . '/bootstrap.inc.php');

class CLITool extends CommandLineTool {

	/**
	 * Constructor.
	 * @param $argv array command-line arguments
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);

		if (sizeof($this->argv) < 1) {
			$this->usage();
			exit(1);
		}

		$this->command = array_shift($this->argv);
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "A generic CLI tool test.\n";
	}

	/**
	 * Delete submission data and associated files
	 */
	function execute() {
		$request = Application::get()->getRequest();

		import('lib.pkp.classes.core.APIRouter');
		$router = new APIRouter();
		$router->setApplication(Application::get());
		$request->setRouter($router);
		$request->_protocol = 'https';

		// Should get handler from command and route to
		// api/v1/{slug}/index.php
		import('api.v1.contexts.ContextHandler');
		$handler = new ContextHandler();

		$userId = 1; // should be admin. todo: get the actual admin

		// Set up the user
		$user = Services::get('user')->get($userId); // should get actual admin
		Registry::set('user', $user);

		// Seet up the session
		$session = SessionManager::getManager()->sessionDao->newDataObject();
		$session->setId(1);
		$session->setUserId($userId);
		$session->setIpAddress(123);
		$session->setUserAgent('');
		$session->setSecondsCreated(time());
		$session->setSecondsLastUsed(time());
		$session->setDomain('');
		$session->setSessionData('');
		SessionManager::getManager()->userSession = $session;


		// eval(\Psy\sh());
		import('lib.pkp.classes.security.authorization.UserRolesRequiredPolicy');
		$handler->addPolicy(new UserRolesRequiredPolicy($request));

		// Set up the API handler
		$router->setHandler($handler);
		$request->setRouter($router);

		// Fake the request object
		$method = 'GET';
		$uri = \Slim\Http\Uri::createFromString('/publicknowledge/api/v1/contexts');
		$handler->getApp()->add(function($request, $response, $next) use ($method, $uri) {

			$request = $request->withMethod($method);
			$request = $request->withUri($uri);
			// if there were to be a POST body
			// $request = $request->write(json_encode(['key' => 'val']));
			// $request->getBody()->rewind();

			return $next($request, $response);
		});

		// Run the route
		$handler->getApp()->run();

		// import('lib.pkp.api.v1.submissions.PKPSubmissionHandler');
		// $test = new PKPSubmissionHandler();
		// eval(\Psy\sh());
		echo "You specified the command $this->command\n";
	}
}

$tool = new CLITool(isset($argv) ? $argv : array());
$tool->execute();
