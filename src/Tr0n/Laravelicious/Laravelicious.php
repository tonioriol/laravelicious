<?php

/**
 * This file is part of the Laravelicious package.
 *
 * (c) Toni Oriol <hell@tonioriol.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tr0n\Laravelicious;

use \Config;
use \Illuminate\Support\Collection;
use \Illuminate\Support\Str;
use \SimpleXMLElement;
use Tr0n\Laravelicious\Exceptions\DeliciousConnectionException;

/**
 * Class Laravelicious
 *
 * Main class of the Laravelicious package, it handles all de logic of connection, requests and response-parsing with
 * the Delicious API.
 *
 * @package Tr0n\Laravelicious
 * @author  Toni Oriol <hell@tonioriol.com>
 */
class Laravelicious {

	private $user;
	private $password;
	private $params;
	private $url;
	private $lastReq;

	public function __construct() {
		$this->user     = Config::get('laravelicious::general.user');
		$this->password = Config::get('laravelicious::general.password');
	}


	public function setUserPassword($user, $password) {
		$this->user     = $user;
		$this->password = $password;
		return $this;
	}

	/**
	 * Check to see when a user last posted an item. Returns the last updated time for the user, as well as the number
	 * of new items in the user’s inbox since it was last visited.
	 *
	 * Use this before calling posts/all to see if the data has changed since the last fetch.
	 *
	 *
	 * @return array    With 'success', 'bookmark_key', 'inbox_key', 'network_key' fields on success.
	 *                  With 'success', 'url' and 'message' fields on failure.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/posts.md#v1postsupdate
	 */
	public function update() {

		$this->buildUrl('posts/update');

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response['code'] == 200) {
			$response = [
				'success'  => true,
				'inboxnew' => (string) $response['inboxnew'],
				'time'     => (string) $response['time'],
				'message'  => (string) $response['code'],
				'url'      => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Add a new post to Delicious.
	 *
	 * @param array   $params (see below)
	 *
	 * @param string  $params['url']         The url of the item (required).
	 * @param string  $params['description'] The description of the item (required).
	 * @param string  $params['extended']    Notes for the item.
	 * @param array   $params['tags']        Tags for the item.
	 * @param string  $params['dt']          Datestamp of the item (format “CCYY-MM-DDThh:mm:ssZ”). Requires a LITERAL “T” and “Z”
	 *                                       like in ISO8601 at http://www.cl.cam.ac.uk/~mgk25/iso-time.html for Example:
	 *                                       1984-09-01T14:21:31Z.
	 * @param bool    $params['replace']     Don’t replace post if given url has already been posted (Default to false).
	 * @param bool    $params['shared']      Make the item private (Default to true).
	 *
	 * @return array  With 'success', 'url' and 'message' fields.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/posts.md#v1postsadd
	 */
	public function add($params) {

		$this->buildUrl('posts/add');

		// Special case due to a bug on the delicious api, that is unable to handle non ascii chars on "url", "description" and "extended" fields
		// on the "url" the way that works better is double urlencode() only the non ascii chars.
		// On the "description" and "extended" fields the best way is replace them by a neutral equivalents (i.e. "í" becomes "i").
		// Yes that's ugly but its the only way to do.
		$params['url'] = $this->urlEncodeNonAsciiChars($params['url']);
		$params['description'] = Str::ascii($params['description']);
		if (array_key_exists('extended', $params)) { // optional value
			$params['extended'] = Str::ascii($params['extended']);
		}

		$this->params = $params;
		$this->buildQueryString();

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response['code'] == 'done') {
			$response = [
				'success' => true,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Delete an existing post from Delicious.
	 *
	 *
	 * @param array   $params (see below)
	 *
	 * @param string $params['url'] The URL of the item (required).
	 *
	 * @return array With 'success', 'url' and 'message' fields.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/posts.md#v1postsdelete
	 */
	public function delete($params) {
		$this->buildUrl('posts/delete');

		$this->params = $params;
		$this->buildQueryString();

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response['code'] == 'done') {
			$response = [
				'success' => true,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Returns one or more posts on a single day matching the arguments. If no date or url is given, most recent date will be used (that is: the current day).
	 *
	 *
	 *
	 * @param array   $params (see below)
	 *
	 * @param array   $params['tags']         Filter by this tags.
	 * @param string  $params['dt']           Filter by this date, defaults to the most recent date on which bookmarks were
	 *                                        saved.
	 * @param string  $params['url']          Fetch a bookmark for this URL, regardless of date. Note: Be sure to URL-encode the
	 *                                        argument value.
	 * @param array   $params['hashes']       Fetch multiple bookmarks by one or more URL MD5s regardless of date.
	 * @param bool    $params['meta']         Include change detection signatures on each item in a ‘meta’ attribute. Clients
	 *                                        wishing to maintain a synchronized local store of bookmarks should retain the value
	 *                                        of this attribute — its value will change when any significant field of the
	 *                                        bookmark changes.
	 *
	 * @return array    With 'success', 'dt', 'url', 'bookmark_key', 'inbox_key', 'network_key', 'tags', 'posts', 'url' fields on success.
	 *                  With 'success', 'url' and 'message' fields on failure.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/posts.md#v1postsget
	 */
	public function get($params = null) {

		$this->buildUrl('posts/get');

		$this->params = $params;
		// force the tag separator to 'comma' for correctly "explode" them after.
		$this->params['tag_separator'] = 'comma';
		$this->buildQueryString();

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response['code'] != 'no bookmarks' and $response['code'] != 'access denied') {
			$response = [
				'success'      => true,
				'dt'           => (string) $response['dt'],
				'bookmark_key' => (string) $response['bookmark_key'],
				'inbox_key'    => (string) $response['inbox_key'],
				'network_key'  => (string) $response['network_key'],
				'tags'         => (string) $response['tags'],
				'posts'        => $this->parseXMLItemList($response->post),
				'url'          => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Private/public bookmarks for a specific user and optionally by tag(s)
	 *
	 *
	 * @param array   $params (see below)
	 *
	 * @param string  $params['user']           The username of the user you want to get their bookmarks (required).
	 * @param array   $params['tags']           The tags you want to filter by.
	 * @param string  $params['private']        Private key (only if you want to get their private bookmarks).
	 *
	 * @return Collection The collection fetched bookmarks
	 * @throws DeliciousConnectionException
	 *
	 * @link https://delicious.com/rss
	 */
	public function getByUser($params) {
		// same as https://api.del.icio.us/v2/json/tonioriol/!manifiesto
		$this->buildUrl('http://feeds.delicious.com/v2/json/', true);

		// add default "count" value to the max available if it is not set outside
		if(!array_key_exists('count', $params)) {
			$params['count'] = 100;
		}

		$this->params = $params;

		$this->buildPath();

		$response = json_decode($this->doReq(), true);

		if (count($response) > 0) {

			$response = [
				'success'     => true,
				'message'     => 'done',
				'posts'     => $response,
				'url'         => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => 'no bookmarks',
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Returns a list of the most recent posts, filtered by argument. Maximum 100.
	 *
	 *
	 * @param array   $params (see below)
	 *
	 * @param string  $params['tag']   Filter by this tag.
	 * @param integer $params['count'] Number of items to retrieve (Default:15, Maximum:100).
	 *
	 * @return array    With 'success', 'message', 'items', 'url' fields on success.
	 *                  With 'success', 'url' and 'message' fields on failure.
	 *
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/posts.md#v1postsrecent
	 */
	public function recent($params = null) {
		$this->buildUrl('posts/recent');

		$this->params = $params;
		$this->buildQueryString();

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response['user'] == $this->user) {

			$response = [
				'success' => true,
				'message' => (string) $response['code'],
				'posts'   => $this->parseXMLItemList($response->post),
				'url'     => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Returns a list of dates with the number of posts at each date.
	 *
	 *
	 * @param array  $params (see below)
	 *
	 * @param string $params['tag'] Filter by this tag.
	 *
	 * @return array    With 'success', 'message', 'dates', 'url' fields on success.
	 *                  With 'success', 'url' and 'message' fields on failure.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/posts.md#v1postsdates
	 */
	public function dates($params = null) {
		$this->buildUrl('posts/dates');

		$this->params = $params;
		$this->buildQueryString();

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response['user'] == $this->user) {

			$response = [
				'success' => true,
				'message' => (string) $response['code'],
				'dates'   => $this->parseXMLItemList($response->date),
				'url'     => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Fetch all bookmarks by date or index range. Please use sparingly. Call the update function to see if you need to fetch this at all.
	 *
	 *
	 * @param array   $params (see below)
	 *
	 * @param string  $params['tag_separator']     Returns tags separated by a comma, instead of a space character. A
	 *                                             space separator is currently used by default to avoid breaking
	 *                                             existing clients - these default may change in future API revisions.
	 * @param string  $params['tag']               Filter by this tag.
	 * @param string  $params['start']             Start returning posts this many results into the set.
	 * @param string  $params['results']           Return up to this many results. By default, up to 1000 bookmarks are
	 *                                             returned, and a maximum of 100000 bookmarks is supported via this
	 *                                             API.
	 * @param string  $params['fromdt']            Filter for posts on this date or later.
	 * @param string  $params['todt']              Filter for posts on this date or earlier.
	 * @param bool    $params['meta']              Include change detection signatures on each item in a ‘meta’
	 *                                             attribute. Clients wishing to maintain a synchronized local store of
	 *                                             bookmarks should retain the value of this attribute - its value will
	 *                                             change when any significant field of the bookmark changes.
	 *
	 * @return array    With 'success', 'message', 'dates', 'url' fields on success.
	 *                  With 'success', 'url' and 'message' fields on failure.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/posts.md#v1postsall
	 */
	public function all($params = null) {
		$this->buildUrl('posts/all');

		$this->params = $params;
		$this->buildQueryString();

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response['user'] == $this->user) {

			$response = [
				'success' => true,
				'message' => (string) $response['code'],
				'posts'   => $this->parseXMLItemList($response->post),
				'total'   => (string) $response['total'],
				'url'     => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Returns a change manifest of all posts. Call the update function to see if you need to fetch this at all.
	 *
	 * This method is intended to provide information on changed bookmarks, without the overhead of a complete download
	 * of all post data. By default, it returns a hash for every bookmark in the user's account.
	 *
	 * Each post element returned offers a url attribute containing an URL MD5, with an associated meta attribute
	 * containing the current change detection signature for that bookmark.
	 *
	 *
	 * @return SimpleXMLElement
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/posts.md#v1postsallhashes
	 */
	public function hashes() {
		$this->buildUrl('posts/all?hashes');

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response['user'] == $this->user) {

			$response = [
				'success' => true,
				'message' => (string) $response['code'],
				'posts'   => $this->parseXMLItemList($response->post),
				'url'     => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Returns a list of popular tags, recommended tags and network tags for a user. This method is intended to provide suggestions for tagging a particular url.
	 *
	 *
	 * @param array   $params (see below)
	 *
	 * @param string  $params['url'] URL for which you'd like suggestions (required).
	 *
	 * @return array    With 'success', 'message', 'popular', 'recommended', 'network' and 'url' fields on success.
	 *                  With 'success', 'message' and 'url' fields on failure.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/posts.md#v1postssuggest
	 */
	public function suggest($params) {
		$this->buildUrl('posts/suggest');

		$this->params = $params;
		$this->buildQueryString();

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response['code'] != 'no suggestions' and $response['code'] != 'access denied') {

			$response = [
				'success'     => true,
				'message'     => (string) $response['code'],
				'popular'     => $this->parseXMLTagsToArray($response->popular),
				'recommended' => $this->parseXMLTagsToArray($response->recommended),
				'network'     => $this->parseXMLTagsToArray($response->network),
				'url'         => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Returns a list of tags and number of times used by a user.
	 *
	 *
	 * @return array    With 'success', 'message', 'tags', 'url' fields on success.
	 *                  With 'success', 'message' and 'url' fields on failure.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/tags.md#v1tagsget
	 */
	public function getTags() {
		$this->buildUrl('tags/get');

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response['code'] != 'no tags' and $response['code'] != 'access denied') {

			$response = [
				'success' => true,
				'message' => (string) $response['code'],
				'tags'    => $this->parseXMLItemList($response->tag, false),
				'url'     => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Delete an existing tag from all posts
	 *
	 *
	 * @param array   $params (see below)
	 *
	 * @param string  $params['tag']    Tag to delete (required).
	 *
	 * @return array    With 'success', 'message', and 'url' fields on success.
	 *                  With 'success', 'message' and 'url' fields on failure.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/tags.md#v1tagsdelete
	 */
	public function deleteTag($params) {
		$this->buildUrl('tags/delete');

		$this->params = $params;
		$this->buildQueryString();

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response['code'] != 'no tags' and $response['code'] != 'access denied') {

			$response = [
				'success' => true,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Rename an existing tag with a new tag name.
	 *
	 *
	 * @param array   $params (see below)
	 *
	 * @param string  $params['old']    Tag to rename (required).
	 * @param string  $params['new']    New tag name (required).
	 *
	 * @return array    With 'success', 'message', and 'url' fields on success.
	 *                  With 'success', 'message' and 'url' fields on failure.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/tags.md#v1tagsrename
	 */
	public function renameTag($params = null) {
		$this->buildUrl('tags/rename');

		$this->params = $params;
		$this->buildQueryString();

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response['code'] != 'no tags' and $response['code'] != 'access denied') {

			$response = [
				'success' => true,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Retrieve all of a user’s bundles.
	 *
	 *
	 * @param array   $params (see below)
	 *
	 * @param string  $params['bundle']    Fetch just the named bundle.
	 *
	 * @return array    With 'success', 'message', 'bundles' and 'url' fields on success.
	 *                  With 'success', 'message' and 'url' fields on failure.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/tagbundles.md#v1tagsbundlesall
	 */
	public function getTagBundles($params = null) {
		$this->buildUrl('tags/bundles/all');

		$this->params = $params;
		$this->buildQueryString();

		$response = $this->doReq();

		if (!empty($response) and $response != '<?xml version="1.0" encoding="UTF-8"?>') {

			$response = new SimpleXMLElement($response);

			$response = [
				'success' => true,
				'message' => (string) $response['code'],
				'bundles' => $this->parseXMLItemList($response->bundle),
				'url'     => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => '',
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Assign a set of tags to a single bundle, wipes away previous settings for bundle.
	 *
	 *
	 * @param array   $params (see below)
	 *
	 * @param string  $params['bundle']    Name of the bundle (required).
	 * @param array   $params['tags']      List of tags (required).
	 *
	 * @return array    With 'success', 'message', 'bundles' and 'url' fields on success.
	 *                  With 'success', 'message' and 'url' fields on failure.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/tagbundles.md#v1tagsbundlesset
	 */
	public function setTagBundle($params = null) {
		$this->buildUrl('tags/bundles/set');

		$this->params = $params;
		$this->buildQueryString();

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response == 'ok') {

			$response = [
				'success' => true,
				'message' => (string) $response->bundle,
				'url'     => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**
	 * Delete a tag bundle.
	 *
	 *
	 * @param array   $params (see below)
	 *
	 * @param string  $params['bundle']    Name of the bundle (required).
	 *
	 * @return array    With 'success', 'message', 'bundles' and 'url' fields on success.
	 *                  With 'success', 'message' and 'url' fields on failure.
	 * @throws DeliciousConnectionException
	 *
	 * @link https://github.com/SciDevs/delicious-api/blob/master/api/tagbundles.md#v1tagsbundlesdelete
	 */
	public function deleteTagBundle($params = null) {
		$this->buildUrl('tags/bundles/delete');

		$this->params = $params;
		$this->buildQueryString();

		$response = $this->doReq();
		$response = new SimpleXMLElement($response);

		if ($response == 'done') {

			$response = [
				'success' => true,
				'message' => (string) $response->bundle,
				'url'     => $this->url
			];
		} else {
			$response = [
				'success' => false,
				'message' => (string) $response['code'],
				'url'     => $this->url
			];
		}

		return $response;
	}

	/**------------------------------------------------
	 *
	 * Private helper and aux methods
	 *
	 *-------------------------------------------------
	*/

	/**
	 * URL-encode only the non-ascii characters, one by one.
	 * @param $param
	 *
	 * @return mixed
	 */
	private function urlEncodeNonAsciiChars($param) {
		// match only the non ascii characters
		return preg_replace_callback('/[^\x00-\x7F]/', function ($matches) {
			return rawurlencode($matches[0]);
		}, $param);
	}

	/**
	 * Executes te request by cURL
	 *
	 * @param string $url used as an alternate url, needed when the req is on feeds.delicious.com
	 *
	 * @return string The plain response
	 * @throws DeliciousConnectionException
	 */
	private function doReq() {

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $this->url);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Lalicious/1.0');
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, $this->user .':' .$this->password);

		$counter = 0;
		do {
			$this->wait();
			$response = curl_exec($ch);
			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			++$counter;
			if($httpcode == 500 or $httpcode == 999) {
				throw new DeliciousConnectionException('Delicious has throttled the application. Attempts: ' . $counter . '. HTTP code: "' . $httpcode . '". URL: ' . $this->url);
			}
		} while(!$response and $counter < 10);

		if($response) {
			return $response;
		} else {
			throw new DeliciousConnectionException('No response from Delcious after ' . $counter . ' attempts. URL: ' . $this->url . 'User: ' . $this->user . '. Password: ' . substr($this->password, 0, 1) . '...' . substr($this->password, -1));
		}

	}

	/**
	 * Wait one second between requests
	 */
	private function wait() {
		$now = microtime(true);

		if (!is_null($this->lastReq)) {
			if ($this->lastReq > ($now - 1000000)) {
				usleep($now - $this->lastReq);
				$this->lastReq = microtime(true);
			}
		} else {
			$this->lastReq = $now;
		}
	}

	/**
	 * Add the object params to the query string.
	 *
	 * @param array $params the params to be added.
	 */
	private function buildQueryString() {

		if (!is_null($this->params) and !empty($this->params)) {
			foreach ($this->params as $name => $value) {
				if (!is_null($value) and !empty($value)) {

					// add "?" if no other param attached to the query string, "?" otherwise.
					$this->url .= Str::contains($this->url, '?') ? '&' : '?';

					if (is_string($value)) {

						$this->url .= $name . '=' . rawurlencode($value);

					} elseif (is_bool($value)) {

						$this->url .= $name . '=' . ($value ? 'yes' : 'no');

					} elseif (is_array($value)) {

						$separator = ($name == 'tag' or $name == 'tags') ? rawurlencode(',') : '+';

						$this->url .= $name . '=';

						foreach ($value as $i => $val) {
							if ($i > 0) {
								$this->url .= $separator;
							}
							$this->url .= urlencode($val);
						}
					}
				}
			}
		}
	}

	private function buildPath() {
		if (array_key_exists('user', $this->params)) {
			$this->url .= $this->params['user'];
		}

		if (array_key_exists('tags', $this->params)) {
			$this->url .= '/';
			foreach ($this->params['tags'] as $i => $tag) {
				if ($i > 0) {
					$this->url .= '+';
				}
				$this->url .= urlencode($tag);
			}
		}

		if (array_key_exists('private', $this->params)) {
			$this->url .= Str::contains($this->url, '?') ? '&' : '?';
			$this->url .= 'private=' . $this->params['private'];
		}

		$this->url .= Str::contains($this->url, '?') ? '&' : '?';
		$this->url .= 'count=100';
	}

	/**
	 * Build the url every time with the values on the class properties and with the given path.
	 *
	 * @param      $path
	 * @param bool $replace if set to true the url is not set with the config connection settings (Default to false).
	 */
	private function buildUrl($path, $replace = false) {

		if (!$replace) {
			// protocol://user:pwd@domain.tld/path?query=string
			$this->url = Config::get('laravelicious::general.protocol');
			$this->url .= '://'; // protocol user separator
//			$this->url .= Config::get('laravelicious::general.user');
//			$this->url .= ':'; // user pwd separator
//			$this->url .= Config::get('laravelicious::general.password');
//			$this->url .= '@'; // pwd domain separator
			$this->url .= Config::get('laravelicious::general.base-url');
			$this->url .= $path;
		} else {
			$this->url = $path;
		}
	}

	private function parseXMLItemList($list, $explodeTags = true) {
		$items = [];

		foreach ($list as $post) {
			$i = [];
			foreach ($post->attributes() as $k => $v) {
				if($k == 'tag' and $explodeTags) {
					$i[(string)$k] = explode(',', (string) $v);
				} else {
					$i[(string)$k] = (string)$v;
				}
			}
			$items[] = $i;
		}
		return $items;
	}

	private function parseXMLTagsToArray($list) {
		$items = [];
		foreach ($list as $post) {
			$items[] = (string) $post['tag'];
		}
		return $items;
	}

}
