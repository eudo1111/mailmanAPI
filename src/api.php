<?php

namespace eudo1111\mailmanapi;

use GuzzleHttp\Client;

#[\AllowDynamicProperties]

class MailmanAPI {

	private $mailmanURL;
	private $password;
	private $user;
	private $client;

	/**
	 * @param $mailmanurl
	 *  Mailman Base URL
	 * @param $password
	 *  Administration Passwort for your Mailman List
	 * @param $user
	 * Username
	 */
	public function __construct($mailmanurl, $password, $user, $validade_ssl_certs = true) {

		$this->mailmanURL = $mailmanurl;
		$this->password = $password;
		$this->user = $user;
		$this->jar = new \GuzzleHttp\Cookie\CookieJar;

		$this->client = new Client([
			'base_uri' => $this->mailmanURL,
			'cookie' => true,
			'verify' => true,
			'allow_redirects' =>  true,
			'cookies' => $this->jar

		]);
		$url = '/accounts/login';
		$token = $this->getCSRFToken($url);
		$response = $this->client->post( '/accounts/login/', [
			'form_params' => [
				'login' => $this->user,
				'password' => $this->password,
				'csrfmiddlewaretoken' => $token,
				'next' => '/mailman3/lists/'
			],
			'cookies' => $this->jar,
			'debug' => false
		]);
		$dom = new \DOMDocument;
			// set error level
		$internalErrors = libxml_use_internal_errors(true);
		$dom->loadHTML($response->getBody());
		libxml_use_internal_errors($internalErrors);
	}

	/**
	 * Return Array of all Mailman Lists
	 */
	public function getMaillists() {
		$response = $this->client->request('GET', '/mailman3/lists/', ['cookies' => $this->jar]);
		$dom = new \DOMDocument;
		$internalErrors = libxml_use_internal_errors(true);
		$dom->loadHTML($response->getBody());
		libxml_use_internal_errors($internalErrors);	
		$tables = $dom->getElementsByTagName("table")[0];
		$trs = $tables->getElementsByTagName("tr");
		return $this->getMembersFromTableRows($trs, $isSinglePage = true);
	}

	/**
	 * Return Array of all Members in a Mailman List
	 */
	public function getMemberlist() {
		$response = $this->client->request('GET', $this->mailmanURL . '/members/member/?count=200', ['cookies' => $this->jar]);

		$dom = new \DOMDocument;
		$internalErrors = libxml_use_internal_errors(true);
		$dom->loadHTML($response->getBody());
		libxml_use_internal_errors($internalErrors);	
		$tables = $dom->getElementsByTagName("table")[0];
		$trs = $tables->getElementsByTagName("tr");

		$finder = new \DomXPath($dom);
		$pagination = $finder->query("//*[contains(@class, 'page-link')]");
		
		$memberList = array();
		if (count($pagination) === 0) {
			return $this->getMembersFromTableRows($trs, $isSinglePage = true);
		}

		$urlsForLetters = array();

		foreach($pagination as $link) {
			$urlsForLetters[] =  $link->getAttribute('href');
		}

		// Remove "Previous" & "Next"
		array_pop($urlsForLetters);
		array_shift($urlsForLetters);
		
		foreach($urlsForLetters as $url) {
			// For first page we already have the members
			if (!empty($url)) {
				$response = $this->client->request('GET', $this->mailmanURL . '/members/member/' . $url, ['cookies' => $this->jar]);
				$dom = new \DOMDocument('1.0', 'UTF-8');

				// set error level
				$internalErrors = libxml_use_internal_errors(true);

				$dom->loadHTML($response->getBody());

				// Restore error level
				libxml_use_internal_errors($internalErrors);

				$tables = $dom->getElementsByTagName("table")[0];
				$trs = $tables->getElementsByTagName("tr");
			}
			$memberList = array_merge(
				$memberList,
				$this->getMembersFromTableRows($trs)
			);
		}
		return $memberList;
	}

	/**
	 * Get the e-mail addresses from a list of table rows (<tr>).
	 *
	 * @param  DOMNodeList  $trs
	 * @param  bool    	$isSinglePage
	 *
	 * @return array
	 */
	protected function getMembersFromTableRows($trs, $isSinglePage = false)
	{
		$memberList = [];

		for ($i = 1; $i < $trs->length; $i++) {
			$tds = $trs[$i]->getElementsByTagName("td");
			$member = trim($tds[1]->nodeValue);
			// Only get Email
			$pattern = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i';
			preg_match($pattern, $member, $matches);
			$memberList[] = $matches[0];
		}
		return $memberList;
	}

	/**
	 * Add new Members to a Mailman List
	 * @param $members
	 *  Array of Members that should be added
	 * @return
	 *  Array of Members that were successfully added
	 */
	public function addMembers($members) {
		$url = $this->mailmanURL . '/mass_subscribe/';
		$token = $this->getCSRFToken($url);
		$response = $this->client->request('POST', $url, [
			'form_params' => [
				'csrfmiddlewaretoken' => $token,
				'emails' => join(chr(10), $members),
				'pre_confirmed' => '1',
				'pre_approved' => '1',
				'pre_verified' => '1',
				'send_welcome_message' => 'False'
			],
			'cookies' => $this->jar
		]);
		$internalErrors = libxml_use_internal_errors(true);
		return $this->parseResultList($response->getBody());
	}

	/**
	 * Remove Members to a Mailman List
	 * @param $members
	 *  Array of Members that should be added
	 * @return
	 *  Array of Members that were successfully removed
	 */
	public function removeMembers($members) {

		$url = $this->mailmanURL . '/mass_removal/';
		$token = $this->getCSRFToken($url);

		$response = $this->client->request('POST', $url, [
			'form_params' => [
				'csrfmiddlewaretoken' => $token,
				'emails' => join(chr(10), $members)
			],
			'cookies' => $this->jar
		]);
		$internalErrors = libxml_use_internal_errors(true);
		return $this->parseResultList($response->getBody());
	}

	/**
	 * Parse the HTML Body of an Add or Remove Action to get List of successfull add/remove entries
	 * @param $body
	 *  the HTML Body of the Result Page
	 * @return
	 * Array of Entrys that were successfull
	 */
	private function parseResultList($body) {
		$dom = new \DOMDocument;
		$dom->loadHTML($body);
		$result = array();
		$finder = new \DomXPath($dom);

		// Get the alerts (success & error)
		$alerts = $finder->query("//*[contains(@class, 'alert')]"); 
		if ($alerts) {
			foreach($alerts as $alert) {
					$result[] = trim($alert->nodeValue);
			}
		}
		return $result;
	}

	/*
	 * Get CSRF Token for a Page
	 * @param $page
	 * 	the Page you want the token for
	 */
	private function getCSRFToken($page) {
		$response = $this->client->request('GET', $page, [
			'cookies' => $this->jar
		]);
		$dom = new \DOMDocument;
		// set error level
		$internalErrors = libxml_use_internal_errors(true);
		$dom->loadHTML($response->getBody());
		// Restore error level
		libxml_use_internal_errors($internalErrors);

		$xp = new \DOMXpath($dom);
		$finder = $xp->query('//input[@name="csrfmiddlewaretoken"]');
		$element = $finder->item(0);
		return $element->getAttribute('value');
	}
}
?>
