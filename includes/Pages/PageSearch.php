<?php

namespace Waca\Pages;

use PDO;
use Waca\DataObjects\Request;
use Waca\DataObjects\User;
use Waca\Exceptions\ApplicationLogicException;
use Waca\Security\SecurityConfiguration;
use Waca\Tasks\InternalPageBase;
use Waca\WebRequest;

class PageSearch extends InternalPageBase
{
	/**
	 * Main function for this page, when no specific actions are called.
	 */
	protected function main()
	{
		$this->setHtmlTitle('Search');

		// Dual-mode page
		if (WebRequest::wasPosted()) {
			$this->validateCSRFToken();
			// TODO: logging

			$searchType = WebRequest::postString('type');
			$searchTerm = WebRequest::postString('term');

			$this->validateSearchParameters($searchType, $searchTerm);

			$results = array();

			switch ($searchType) {
				case 'name':
					$results = $this->getNameSearchResults($searchTerm);
					break;
				case 'email':
					$results = $this->getEmailSearchResults($searchTerm);
					break;
				case 'ip':
					$results = $this->getIpSearchResults($searchTerm);
					break;
			}

			// deal with results
			$this->assign('requests', $results);
			$this->assign('term', $searchTerm);
			$this->assign('target', $searchType);

			$userIds = array_map(
				function(Request $entry) {
					return $entry->getReserved();
				},
				$results);
			$userList = User::getUsernames($userIds, $this->getDatabase());
			$this->assign('userlist', $userList);

			$this->assignCSRFToken();
			$this->setTemplate('search/searchResult.tpl');
		}
		else {
			$this->assignCSRFToken();
			$this->setTemplate('search/searchForm.tpl');
		}
	}

	/**
	 * Gets search results by name
	 *
	 * @param $searchTerm string
	 *
	 * @returns array<Request>
	 */
	private function getNameSearchResults($searchTerm)
	{
		$padded = '%' . $searchTerm . '%';

		$database = $this->getDatabase();

		$query = 'SELECT * FROM request WHERE name LIKE :term AND email <> :clearedEmail AND ip <> :clearedIp';
		$statement = $database->prepare($query);
		$statement->bindValue(":term", $padded);
		$statement->bindValue(":clearedEmail", $this->getSiteConfiguration()->getDataClearEmail());
		$statement->bindValue(":clearedIp", $this->getSiteConfiguration()->getDataClearIp());
		$statement->execute();

		/** @var Request $r */
		$requests = $statement->fetchAll(PDO::FETCH_CLASS, Request::class);
		foreach ($requests as $r) {
			$r->setDatabase($database);
			$r->isNew = false;
		}

		return $requests;
	}

	/**
	 * Gets search results by email
	 *
	 * @param $searchTerm string
	 *
	 * @return array <Request>
	 * @throws ApplicationLogicException
	 */
	private function getEmailSearchResults($searchTerm)
	{
		if ($searchTerm === "@") {
			throw new ApplicationLogicException('The search term "@" is not valid for email address searches!');
		}

		$padded = '%' . $searchTerm . '%';

		$database = $this->getDatabase();

		$query = 'SELECT * FROM request WHERE email LIKE :term AND email <> :clearedEmail AND ip <> :clearedIp';
		$statement = $database->prepare($query);
		$statement->bindValue(":term", $padded);
		$statement->bindValue(":clearedEmail", $this->getSiteConfiguration()->getDataClearEmail());
		$statement->bindValue(":clearedIp", $this->getSiteConfiguration()->getDataClearIp());
		$statement->execute();

		/** @var Request $r */
		$requests = $statement->fetchAll(PDO::FETCH_CLASS, Request::class);
		foreach ($requests as $r) {
			$r->setDatabase($database);
			$r->isNew = false;
		}

		return $requests;
	}

	/**
	 * Gets search results by IP address or XFF IP address
	 *
	 * @param $searchTerm string
	 *
	 * @returns array<Request>
	 */
	private function getIpSearchResults($searchTerm)
	{
		$padded = '%' . $searchTerm . '%';

		$database = $this->getDatabase();

		$query = <<<SQL
SELECT * FROM request
WHERE ip LIKE :term OR forwardedip LIKE :paddedTerm AND email <> :clearedEmail AND ip <> :clearedIp
SQL;

		$statement = $database->prepare($query);
		$statement->bindValue(":term", $searchTerm);
		$statement->bindValue(":paddedTerm", $padded);
		$statement->bindValue(":clearedEmail", $this->getSiteConfiguration()->getDataClearEmail());
		$statement->bindValue(":clearedIp", $this->getSiteConfiguration()->getDataClearIp());
		$statement->execute();

		/** @var Request $r */
		$requests = $statement->fetchAll(PDO::FETCH_CLASS, Request::class);
		foreach ($requests as $r) {
			$r->setDatabase($database);
			$r->isNew = false;
		}

		return $requests;
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
		return $this->getSecurityManager()->configure()->asInternalPage();
	}

	/**
	 * @param $searchType
	 * @param $searchTerm
	 *
	 * @throws ApplicationLogicException
	 */
	protected function validateSearchParameters($searchType, $searchTerm)
	{
		if (!in_array($searchType, array('name', 'email', 'ip'))) {
			// todo: handle more gracefully.
			throw new ApplicationLogicException('Unknown search type');
		}

		if ($searchTerm === '%' || $searchTerm === '') {
			throw new ApplicationLogicException('No search term specified entered');
		}
	}
}