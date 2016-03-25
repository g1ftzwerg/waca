<?php

namespace Waca\Pages;

use Exception;
use Waca\DataObjects\User;
use Waca\DataObjects\WelcomeTemplate;
use Waca\Exceptions\ApplicationLogicException;
use Waca\Helpers\Logger;
use Waca\Security\SecurityConfiguration;
use Waca\SessionAlert;
use Waca\Tasks\InternalPageBase;
use Waca\WebRequest;

class PageWelcomeTemplateManagement extends InternalPageBase
{
	/**
	 * Main function for this page, when no specific actions are called.
	 * @return void
	 */
	protected function main()
	{
		$templateList = WelcomeTemplate::getAll($this->getDatabase());

		$this->assignCSRFToken();

		$this->assign('templateList', $templateList);
		$this->setTemplate('welcome-template/list.tpl');
	}

	/**
	 * Handles the requests for selecting a template to use.
	 *
	 * @throws ApplicationLogicException
	 */
	protected function select()
	{
		// get rid of GETs
		if (!WebRequest::wasPosted()) {
			$this->redirect('welcomeTemplates');
		}

		$this->validateCSRFToken();

		$user = User::getCurrent($this->getDatabase());

		if (WebRequest::postBoolean('disable')) {
			$user->setWelcomeTemplate(null);
			$user->save();

			SessionAlert::success('Disabled automatic user welcoming.');
			$this->redirect('welcomeTemplates');

			return;
		}

		$database = $this->getDatabase();

		$templateId = WebRequest::postInt('template');
		$template = WelcomeTemplate::getById($templateId, $database);

		if ($template === false) {
			throw new ApplicationLogicException('Unknown template');
		}

		$user->setWelcomeTemplate($template->getId());
		$user->save();

		SessionAlert::success("Updated selected welcome template for automatic welcoming.");

		$this->redirect('welcomeTemplates');
	}

	/**
	 * Handles the requests for viewing a template.
	 *
	 * @throws ApplicationLogicException
	 */
	protected function view()
	{
		$database = $this->getDatabase();

		$templateId = WebRequest::getInt('template');

		/** @var WelcomeTemplate $template */
		$template = WelcomeTemplate::getById($templateId, $database);

		if ($template === false) {
			throw new ApplicationLogicException('Cannot find requested template');
		}

		$templateHtml = $this->getWikiTextHelper()->getHtmlForWikiText($template->getBotCode());

		$this->assign('templateHtml', $templateHtml);
		$this->assign('template', $template);
		$this->setTemplate('welcome-template/view.tpl');
	}

	/**
	 * Handler for the add action to create a new welcome template
	 *
	 * @throws Exception
	 */
	protected function add()
	{
		if (WebRequest::wasPosted()) {
			$this->validateCSRFToken();
			$database = $this->getDatabase();

			$template = new WelcomeTemplate();
			$template->setDatabase($database);
			$template->setUserCode(WebRequest::postString('usercode'));
			$template->setBotCode(WebRequest::postString('botcode'));
			$template->save();

			Logger::welcomeTemplateCreated($database, $template);

			$this->getNotificationHelper()->welcomeTemplateCreated($template);

			SessionAlert::success("Template successfully created.");

			$this->redirect('welcomeTemplates');
		}
		else {
			$this->assignCSRFToken();
			$this->setTemplate("welcome-template/add.tpl");
		}
	}

	/**
	 * Hander for editing templates
	 */
	protected function edit()
	{
		$database = $this->getDatabase();

		$templateId = WebRequest::getInt('template');

		/** @var WelcomeTemplate $template */
		$template = WelcomeTemplate::getById($templateId, $database);

		if ($template === false) {
			throw new ApplicationLogicException('Cannot find requested template');
		}

		if (WebRequest::wasPosted()) {
			$this->validateCSRFToken();
			$template->setUserCode(WebRequest::postString('usercode'));
			$template->setBotCode(WebRequest::postString('botcode'));
			$template->setUpdateVersion(WebRequest::postInt('botcode'));
			$template->save();

			Logger::welcomeTemplateEdited($database, $template);

			SessionAlert::success("Template updated.");

			$this->getNotificationHelper()->welcomeTemplateEdited($template);

			$this->redirect('welcomeTemplates');
		}
		else {
			$this->assignCSRFToken();
			$this->assign('template', $template);
			$this->setTemplate('welcome-template/edit.tpl');
		}
	}

	protected function delete()
	{
		$this->redirect('welcomeTemplates');

		if (!WebRequest::wasPosted()) {
			return;
		}

		$this->validateCSRFToken();

		$database = $this->getDatabase();

		$templateId = WebRequest::postInt('template');
		$updateVersion = WebRequest::postInt('updateversion');

		/** @var WelcomeTemplate $template */
		$template = WelcomeTemplate::getById($templateId, $database);

		if ($template === false) {
			throw new ApplicationLogicException('Cannot find requested template');
		}

		// set the update version to the version sent by the client (optimisticly lock from initial page load)
		$template->setUpdateVersion($updateVersion);

		$database
			->prepare("UPDATE user SET welcome_template = NULL WHERE welcome_template = :id;")
			->execute(array(":id" => $templateId));

		Logger::welcomeTemplateDeleted($database, $template);

		$template->delete();

		SessionAlert::success(
			"Template deleted. Any users who were using this template have had automatic welcoming disabled.");
		$this->getNotificationHelper()->welcomeTemplateDeleted($templateId);
	}

	/**
	 * Sets up the security for this page. If certain actions have different permissions, this should be reflected in
	 * the return value from this function.
	 *
	 * If this page even supports actions, you will need to check the route
	 *
	 * @return SecurityConfiguration
	 * @category Security-Critical
	 */
	protected function getSecurityConfiguration()
	{
		switch ($this->getRouteName()) {
			case 'edit':
			case 'add':
			case 'delete':
				// WARNING: if you want to unlink edit/add/delete, you'll want to change the barrier tests in the
				// template
				return $this->getSecurityManager()->configure()->asAdminPage();
			case 'view':
			case 'select':
				return $this->getSecurityManager()->configure()->asInternalPage();
			default:
				return $this->getSecurityManager()->configure()->asInternalPage();
		}
	}
}