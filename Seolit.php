<?php

class SeolitResponse
{
	public $CurlErrorCode;
	public $CurlErrorText;
	public $HttpResultCode;
	public $DataRaw;
	public $Data;

	/**
	 * Checks for CURL, HTTP or Seolit API errors.
	 * @return bool
	 */
	public function isError()
	{
		return (
			$this->isErrorCurl() or
			$this->isErrorHttp() or
			$this->isErrorSeolit()
		);
	}

	/**
	 * Checks for CURL errors.
	 * @return bool
	 */
	public function isErrorCurl()
	{
		return (($this->DataRaw === false) and
		        !empty($this->CurlErrorCode));
	}

	/**
	 * Checks for HTTP errors.
	 * @return bool
	 */
	public function isErrorHttp()
	{
		return (($this->HttpResultCode <  200) or
		        ($this->HttpResultCode >= 300));
	}

	/**
	 * Checks for Seolit API errors.
	 * @return bool
	 */
	public function isErrorSeolit()
	{
		return (!isset($this->Data->result) or
		        (strcasecmp($this->Data->result, 'ok') !== 0));
	}
}

class SeolitPublishRequest
{
	/**
	 * ID of your project.
	 * @var int $ProjectId
	 */
	public $ProjectId;

	/**
	 * JSON encoded array of Accounts' IDs.
	 * @var string $Networks
	 */
	public $Networks;

	/**
	 * Message title.
	 * @var string $Title
	 */
	public $Title;

	/**
	 * Message text.
	 * @var string $Text
	 */
	public $Text;

	/**
	 * Message tags.
	 * @var string|null $Tags
	 */
	public $Tags;

	/**
	 * URL to post.
	 * @var string|null $Url
	 */
	public $Url;

	/**
	 * Image files to attach.
	 * @var string|string[]|null $ImageFiles
	 */
	public $ImageFiles;

	/**
	 * URL of image to post.
	 * @var string|string[]|null $ImageUrls
	 */
	public $ImageUrls;

	/**
	 * URL of video to post.
	 * @var string|null $VideoUrl
	 */
	public $VideoUrl;

	/**
	 * Date/Time to post message at.
	 * @var int|DateTime|DateTimeImmutable|string $PostDate
	 */
	public $PostDate;

	/**
	 * Autodeleting of published posts:
	 * <ul>
	 *   <li> -1 - set by template (default), </li>
	 *   <li> 0 - disabled, </li>
	 *   <li> 1 - enabled. </li>
	 * </ul>
	 * @var int|null $PostAutoDel
	 */
	public $PostAutoDel = Seolit::POST_AUTODEL_BY_TEMPLATE;

	/**
	 * Date/Time to delete published post.
	 * @var null|int|DateTime|DateTimeImmutable|string $PostAutoDelDate
	 */
	public $PostAutoDelDate;
}

class Seolit
{
	/** Delete post after publication as set in template */
	const POST_AUTODEL_BY_TEMPLATE = -1;

	/** Do not delete post after publication */
	const POST_AUTODEL_DISABLED    =  0;

	/** Automatically delete published post after specified date */
	const POST_AUTODEL_ENABLED     =  1;

	protected $ch;
	protected $token;

	/**
	 * Seolit constructor.
	 * @param string $token  API token for your account.
	 */
	public function __construct($token)
	{
		$this->ch    = curl_init();
		$this->token = $token;
	}

	/**
	 * Tries to make a string with formatted datetime.
	 * @param string|int|float|DateTime|DateTimeImmutable|null $DateTime Datetime value.
	 * @return string
	 */
	protected static function tryGetDateTimeFormatted($DateTime)
	{
		if (!isset($DateTime)) {
			return $DateTime;
		}

		if ($DateTime instanceof DateTime) {
			$dt = clone $DateTime;
			$dt->setTimezone(new DateTimeZone('UTC'));
			return $dt->format('Y-m-d\TH:i:s') . 'UTC';
		}
		else if ($DateTime instanceof DateTimeImmutable) {
			$dt = clone $DateTime;
			$dt = $dt->setTimezone(new DateTimeZone('UTC'));
			return $dt->format('Y-m-d\TH:i:s') . 'UTC';
		}
		else if (is_int($DateTime) or is_float($DateTime)) {
			return gmdate('Y-m-d\TH:i:s', $DateTime) . 'UTC';
		}
		return $DateTime;
	}

	/**
	 * Makes URL from its parts array (returned by parse_url() function).
	 * @param array $parsed_url
	 * @return string
	 */
	public static function unparse_url($parsed_url)
	{
		$scheme   = isset($parsed_url['scheme'])   ? $parsed_url['scheme'] . '://' : '';
		$host     = isset($parsed_url['host'])     ? $parsed_url['host'] : '';
		$port     = isset($parsed_url['port'])     ? ':' . $parsed_url['port'] : '';
		$user     = isset($parsed_url['user'])     ? $parsed_url['user'] : '';
		$pass     = isset($parsed_url['pass'])     ? ':' . $parsed_url['pass'] : '';
		$pass     = ($user || $pass) ? "$pass@" : '';
		$path     = isset($parsed_url['path'])     ? $parsed_url['path'] : '/';
		$query    = isset($parsed_url['query'])    ? '?' . $parsed_url['query'] : '';
		$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

		return "$scheme$user$pass$host$port$path$query$fragment";
	}

	/**
	 * Returns an array with default CURL options.
	 * @return array
	 */
	protected function curlDefaultOptions()
	{
		return [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => false,
			CURLOPT_CONNECTTIMEOUT => 20,
			CURLOPT_TIMEOUT        => 120,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 5,
			CURLOPT_SSL_VERIFYPEER => true,
		];
	}

	/**
	 * Executes CURL request with specified options and returns request result.
	 * @param $curl_options
	 * @return SeolitResponse
	 */
	protected function curlExec($curl_options) {
		$this->curlReset();
		curl_setopt_array($this->ch, $curl_options);

		$result = new SeolitResponse();
		$result->DataRaw        = curl_exec   ($this->ch);
		$result->CurlErrorCode  = curl_errno  ($this->ch);
		$result->CurlErrorText  = curl_error  ($this->ch);
		$result->HttpResultCode = curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);
		$result->Data           = json_decode ($result->DataRaw);

		return $result;
	}

	/**
	 * Executes GET request and returns request result.
	 * @param $url
	 * @param $params
	 * @return SeolitResponse
	 */
	protected function curlGet($url, $params)
	{
		$url_parts = parse_url($url);
		$url_parts['query'] = (!empty($url_parts['query']) ? ($url_parts['query'] . '&') : '') .
		                      http_build_query($params);

		$options = $this->curlDefaultOptions();
		$options[CURLOPT_POST] = false;
		$options[CURLOPT_URL]  = self::unparse_url($url_parts);
		return $this->curlExec($options);
	}

	/**
	 * Executes POST request and returns request result.
	 * @param $url
	 * @param $params
	 * @return SeolitResponse
	 */
	protected function curlPost($url, $params)
	{
		$options = $this->curlDefaultOptions();
		$options[CURLOPT_POST] = true;
		$options[CURLOPT_URL]  = $url;
		$options[CURLOPT_POSTFIELDS] = $params;
		return $this->curlExec($options);
	}

	/**
	 * Resets CURL options to default.
	 */
	protected function curlReset()
	{
		// Remove all callback functions as they can hold onto references
		// and are not cleaned up by curl_reset. Using curl_setopt_array
		// does not work for some reason, so removing each one individually.
		curl_setopt($this->ch, CURLOPT_HEADERFUNCTION,   null);
		curl_setopt($this->ch, CURLOPT_READFUNCTION,     null);
		curl_setopt($this->ch, CURLOPT_WRITEFUNCTION,    null);
		curl_setopt($this->ch, CURLOPT_PROGRESSFUNCTION, null);
		curl_reset ($this->ch); // reset CURL to default settings
	}

	/**
	 * Returns array with parameters to publish message via Seolit API.
	 * @param SeolitPublishRequest $request
	 * @return array
	 */
	protected function getPublishParams($request)
	{
		$postAutoDel = $request->PostAutoDel;
		if (is_numeric($postAutoDel)) {
			if ($postAutoDel <= self::POST_AUTODEL_BY_TEMPLATE) {
				$postAutoDel = self::POST_AUTODEL_BY_TEMPLATE;
			}
			elseif ($postAutoDel >= self::POST_AUTODEL_ENABLED) {
				$postAutoDel = self::POST_AUTODEL_ENABLED;
			}
			else {
				$postAutoDel = self::POST_AUTODEL_DISABLED;
			}
		}
		elseif ($postAutoDel !== null) {
			$postAutoDel = self::POST_AUTODEL_BY_TEMPLATE;
		}

		if (($postAutoDel == self::POST_AUTODEL_ENABLED) and empty($postAutoDelDate)) {
			$postAutoDel = null;
		}

		$imgFiles = [];
		if (!empty($request->ImageFiles)) {
			if (is_array($request->ImageFiles)) {
				$i = 0;
				foreach ($request->ImageFiles as $file) {
					$i++;
					$file_type = filetype($file);
					$file_name = basename($file);

					$imgFiles['postFile' . $i] = new CURLFile($file, $file_type, $file_name);
				}
			}
			elseif (is_string($request->ImageFiles)) {
				$file_type              = filetype($request->ImageFiles);
				$file_name              = basename($request->ImageFiles);
				$imgFiles['postFile1'] = new CURLFile($request->ImageFiles, $file_type, $file_name);
			}
		}

		$imgUrls = [];
		if (!empty($request->ImageUrls)) {
			if (is_array($request->ImageUrls)) {
				if (count($request->ImageUrls) > 1) {
					$i = 0;
					foreach ($request->ImageUrls as $url) {
						if (!empty($url)) {
							$i++;
							$imgFiles['postUrlImage[' . $i . ']'] = $url;
						}
					}
				}
				else {
					$imgUrls['postUrlImage'] = reset($request->ImageUrls);
				}
			}
			elseif (is_string($request->ImageUrls)) {
				$imgFiles['postUrlImage'] = $request->ImageUrls;
			}
		}

		$params = [
			'token'        => $this->token,
			'prj'          => $request->ProjectId,
			'networks'     => $request->Networks,
			'postName'     => $request->Title,
			'postText'     => $request->Text,
			'postTags'     => $request->Tags,
			'postUrl'      => $request->Url,
			'postUrlVideo' => $request->VideoUrl,
			'postDateTime' => self::tryGetDateTimeFormatted($request->PostDate),
		];

		if ($postAutoDel !== null) {
			$params['postAutodel'] = $postAutoDel;
			if (!empty($request->PostAutoDelDate)) {
				$params['postAutodelDateTime'] = self::tryGetDateTimeFormatted($request->PostAutoDelDate);
			}
		}

		$params += $imgUrls;
		$params += $imgFiles;
		return $params;
	}

	/**
	 * Gets projects info data.
	 * <p>Example JSON response:</p>
	 * <pre>
	 * {
	 *   "status": "ok",
	 *   "projects": [
	 *     {
	 *       "id": 0,
	 *       "name": "Example Project",
	 *       "accounts": [
	 *         {
	 *           "id": 1,
	 *           "project_id": 0,
	 *           "name": "Example Facebook"
	 *         },
	 *         {
	 *           "id": 2,
	 *           "project_id": 0,
	 *           "name": "Example Instagram"
	 *         }
	 *       ]
	 *     },
	 *     {
	 *       "id": 1,
	 *       "name": "Example Project 2",
	 *       "accounts": [
	 *         {
	 *           "id": 3,
	 *           "project_id": 1,
	 *           "name": "Example Telegram"
	 *         }
	 *       ]
	 *     }
	 *   ]
	 * }
	 * </pre>
	 * @return SeolitResponse
	 */
	public function getProjectsInfo()
	{
		return $this->curlGet(
			'https://seolit.ru/index.php?option=com_seolit&task=apisettings.info',
			[
				'token' => $this->token,
			]
		);
	}

	/**
	 * Gets data of a message queued for publication.
	 * <p>Example JSON response:</p>
	 * <pre>
	 * {
	 *     "status": "ok",
	 *     "item": {
	 *         "id": 0,
	 *         "project_id": 0,
	 *         "account_id": 1,
	 *         "account_name": "Example Facebook",
	 *         "title": "Sample message",
	 *         "text": "The quick brown fox jumps over the lazy dog.",
	 *         "tags": "#sample", 
	 *         "url": "https:\/\/seolit.ru\/",
	 *         "attachments": [
	 *             {
	 *                 "type": "image",
	 *                 "url": "https:\/\/seolit.ru\/images\/seolit-logo.png"
	 *             }
	 *         ],
	 *         "publish": {
	 *             "state": "published",
	 *             "date_plan": "2019-04-25T14:55:00Z",
	 *             "date_fact": "2019-04-25T14:57:21Z",
	 *             "url": "https:\/\/facebook.com\/1234sample12345_3421message1234"
	 *         },
	 *         "delete": {
	 *             "state": "delete_error",
	 *             "error": "Session expired",
	 *             "date_plan": "2019-05-25T14:51:21Z"
	 *             "date_fact": "2019-05-25T14:52:17Z"
	 *         }
	 *     }
	 * }
	 * </pre>
	 * <p>Attachment types: image, video.</p>
	 * <p>Publish states: queued, paused, published, publish_error.</p>
	 * <p>Delete states: queued, deleted, delete_error.</p>
	 * @param int $item_id Queued message item ID.
	 * @return SeolitResponse
	 */
	public function getPublishQueueItem($item_id)
	{
		return $this->curlGet(
			'https://seolit.ru/index.php?option=com_seolit&task=queueelem.info',
			[
				'token' => $this->token,
				'id'    => $item_id,
			]
		);
	}

	/**
	 * Publishes message via Seolit API.
	 * <p>Example JSON response:</p>
	 * <pre>
	 * {
	 *     "result": "OK",
	 *     "messages": [
	 *         {
	 *             "message": "Ваша запись добавлена в очередь на публикацию!",
	 *             "type": "message"
	 *         }
	 *     ],
	 *     "items": [
	 *         1,
	 *         2,
	 *         3
	 *     ]
	 * }
	 * </pre>
	 * <p>"items" contains an array of identifiers of items added to the publication queue.</p>
	 * @param SeolitPublishRequest $request Object with parameters of message to publish.
	 * @return SeolitResponse
	 */
	public function publish($request)
	{
		$params = $this->getPublishParams($request);
		return $this->curlPost(
			'https://seolit.ru/index.php?option=com_seolit&task=publish.publish',
			$params
		);
	}
}

// API token
$token      = 'your-token';
// Project's ID
$project_id = 50;
// Accounts' IDs array
$networks   = json_encode([10, 11, 12]);


$request = new SeolitPublishRequest;
$request->ProjectId  = $project_id;
$request->Networks   = $networks;
// Title of message
$request->Title      = 'Первое сообщение, размещённое через API';
// Text of message
$request->Text       = "Мы можем разместить достаточно большой текст, в т.ч. с переводами строк.\nС новой строки текст будет размещён в тех соцсетях, которые не игнорируют данный символ.";
// Tags to post
$request->Tags       = '#SEO #news #sample';
// URL to post
$request->Url        = 'https://seolit.ru/';
// URL of image to post
$request->ImageUrls  = 'https://seolit.ru/images/seolit-logo.png';
// URL of video to post.
$request->VideoUrl   = '';
// Date/Time to post message at
$dt                  = new DateTime('Monday next week');
$request->PostDate   = clone $dt;
// Images to attach
$request->ImageFiles = ['testimage.jpg', 'testimage.jpg'];

$seolit   = new Seolit($token);
$response = $seolit->publish($request);
$item_id  = null;
echo $response->Data->result . "\n<br>\n";
if (isset($response->Data->messages)) {
	foreach ($response->Data->messages as $message) {
		echo $message->type . ' => ' . $message->message . "\n<br>\n";
	}
}

if (!$response->isError() and isset($response->Data->items) and is_array($response->Data->items)) {
	foreach ($response->Data->items as $item) {
		$item_id = $item;
		echo 'publish queue item id: ' . $item . "\n<br>\n";
	}
}

// -------------------------------------------------------
// Automatically deleting message

// Title of message
$request->Title       = 'Второе сообщение, размещённое через API';
// Text of message
$request->Text       .= "\nЭто сообщение удалится через неделю.";
// Enable autodeleting
$request->PostAutoDel = Seolit::POST_AUTODEL_ENABLED;
// Date/Time to delete published post
$dt->add(DateInterval::createFromDateString('+7 Days'));
$request->PostAutoDelDate = clone $dt;

$response = $seolit->publish($request);
echo $response->Data->result . "\n<br>\n";
if (isset($response->Data->messages)) {
	foreach ($response->Data->messages as $message) {
		echo $message->type . ' => ' . $message->message . "\n<br>\n";
	}
}

if (!$response->isError() and isset($response->Data->items) and is_array($response->Data->items)) {
	foreach ($response->Data->items as $item) {
		$item_id = $item;
		echo 'publish queue item id: ' . $item . "\n<br>\n";
	}
}

if (!empty($item_id)) {
	$response = $seolit->getPublishQueueItem($item_id);
	if (!$response->isError() and isset($response->Data->item)) {
		$item = $response->Data->item;
		if (strcasecmp($item->publish->state ?? '', 'published') === 0) {
			echo "message published\n<br>\n";
			if (!empty($item->publish->url)) {
				echo 'message url: ' . $item->publish->url . "\n<br>\n";
			}
		}
	}
}
