<?php
namespace Waca;

use Exception;
use \Offline;
use PdoDatabase;
use Waca\Exceptions\EnvironmentException;
use Waca\Exceptions\ReadableException;
use Waca\Providers\GlobalStateProvider;

/**
 * Internal application entry point.
 *
 * @package Waca
 */
class WebStart
{
	/**
	 * Application entry point.
	 *
	 * Sets up the environment and runs the application, performing any global cleanup operations when done.
	 */
	public function run()
	{
		try {
			if ($this->setupEnvironment()) {
				$this->main();
			}
		}
		catch(EnvironmentException $ex) {
			ob_end_clean();
			print Offline::getOfflineMessage(false, $ex->getMessage());
		}
		catch(ReadableException $ex) {
			ob_end_clean();
			print $ex->getReadableError();
		}
		finally {
			$this->cleanupEnvironment();
		}
	}

	/**
	 * Global exception handler
	 *
	 * Smarty would be nice to use, but it COULD BE smarty that throws the errors.
	 * Let's build something ourselves, and hope it works.
	 *
	 * @param $exception
	 * @category Security-Critical - has the potential to leak data when exception is thrown.
	 */
	public static function exceptionHandler(Exception $exception)
	{
		global $baseurl, $filepath, $enableErrorTrace;

		$errorDocument = <<<HTML
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8">
<title>Oops! Something went wrong!</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="{$baseurl}/lib/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<style>
  body {
    padding-top: 60px;
  }
</style>
<link href="{$baseurl}/lib/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
</head><body><div class="container">
<h1>Oops! Something went wrong!</h1>
<p>We'll work on fixing this for you, so why not come back later?</p><p class="muted">If our trained monkeys ask, tell them this error ID: <code>$1$</code></p>
$2$
</div></body></html>
HTML;

		$errorData = self::getExceptionData($exception);
		$errorData['server'] = $_SERVER;
		$errorData['get'] = $_GET;
		$errorData['post'] = $_POST;

		$state = serialize($errorData);
		$errorId = sha1($state);

		// TODO: log the error state somewhere.

		// clear and discard any content that's been saved to the output buffer
		if (ob_get_level() > 0) {
			ob_end_clean();
		}

		// push error ID into the document.
		$message = str_replace('$1$', $errorId, $errorDocument);

		if ($enableErrorTrace) {
			ob_start();
			var_dump($errorData);
			$textErrorData = ob_get_contents();
			ob_end_clean();

			$message = str_replace('$2$', $textErrorData, $message);
		}
		else {
			$message = str_replace('$2$', "", $message);
		}

		// output the document
		print $message;

		die;
	}

	/**
	 * Environment setup
	 *
	 * This method initialises the tool environment. If the tool cannot be initialised correctly, it will return false
	 * and shut down prematurely.
	 *
	 * @return bool
	 * @throws EnvironmentException
	 */
	private function setupEnvironment()
	{
		global $schemaVersion;

		// initialise global exception handler
		set_exception_handler(array(WebStart::class, "exceptionHandler"));

		// start output buffering if necessary
		if (ob_get_level() === 0) {
			ob_start();
		}

		// initialise super-global providers
		WebRequest::setGlobalStateProvider(new GlobalStateProvider());

		// check the tool is still online
		if (Offline::isOffline()) {
			print Offline::getOfflineMessage(false);
			ob_end_flush();
			return false;
		}

		// check the schema version
		$database = PdoDatabase::getDatabaseConnection("acc");
		$actualVersion = $database->query("SELECT version FROM schemaversion")->fetchColumn();
		if ($actualVersion !== $schemaVersion) {
			throw new EnvironmentException("Database schema is wrong version! Please either update configuration or database.");
		}

		// Start up sessions
		Session::start();

		// environment initialised!
		return true;
	}

	/**
	 * Main application logic
	 */
	private function main()
	{
		// Get the right route for the request
		$router = new RequestRouter();
		$page = $router->route();

		// run the route code for the request.
		$page->execute();
	}

	/**
	 * Any cleanup tasks should go here
	 *
	 * Note that we need to be very careful here, as exceptions may have been thrown and handled.
	 * This should *only* be for cleaning up, no logic should go here.
	 */
	private function cleanupEnvironment()
	{
		// Clean up anything we splurged after sending the page.
		if (ob_get_level() > 0) {
			for ($i = ob_get_level(); $i > 0; $i--) {
				ob_end_clean();
			}
		}
	}

	/**
	 * @param Exception $exception
	 * @return array
	 */
	private static function getExceptionData($exception)
	{
		if ($exception == null) {
			return null;
		}

		return array(
			'message'  => $exception->getMessage(),
			'stack'    => $exception->getTraceAsString(),
			'previous' => self::getExceptionData($exception->getPrevious())
		);
	}
}