<?php

namespace includes\Helpers;

use PHPUnit_Extensions_MockFunction;
use PHPUnit_Framework_TestCase;
use Waca\Helpers\EmailHelper;

/**
 * @requires extension runkit
 */
class EmailHelperTest extends PHPUnit_Framework_TestCase
{
	/** @var PHPUnit_Extensions_MockFunction */
	private $mailMock;
	/** @var EmailHelper */
	private $emailHelper;

	public function setUp()
	{
		if (!extension_loaded('runkit') || !class_exists('PHPUnit_Extensions_MockFunction')) {
			$this->markTestSkipped('Dependencies for test are not available. Please install zenovich/runkit and tcz/phpunit-mockfunction');

			return;
		}

		$this->emailHelper = new EmailHelper();

		$this->mailMock = new PHPUnit_Extensions_MockFunction('mail', $this->emailHelper);
	}

	public function testSendMail()
	{
		$this->mailMock->expects($this->once())
		               ->with('noreply@stwalkerster.co.uk', 'test mail subject', 'test mail content',
			               "From: accounts-enwiki-l@lists.wikimedia.org\r\n")
		               ->will($this->returnValue(true));

		$this->emailHelper->sendMail('noreply@stwalkerster.co.uk', 'test mail subject', 'test mail content');
	}

	public function testSendMailWithHeader()
	{
		$this->mailMock->expects($this->once())
		               ->with('noreply@stwalkerster.co.uk', 'test mail subject', 'test mail content',
			               "X-ACC-Test: foobar\r\nFrom: accounts-enwiki-l@lists.wikimedia.org\r\n")
		               ->will($this->returnValue(true));

		$this->emailHelper->sendMail('noreply@stwalkerster.co.uk', 'test mail subject', 'test mail content',
			array('X-ACC-Test' => 'foobar'));
	}

	public function tearDown()
	{
		if (extension_loaded('runkit') && class_exists('PHPUnit_Extensions_MockFunction')) {
			// restore functionality
			$this->mailMock->restore();
		}

		parent::tearDown();
	}
}