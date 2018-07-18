<?php

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
     * Publishes message via Seolit API.
     *
     * @param int          $project_id       ID of your project.
     * @param string       $title            Message title.
     * @param string       $text             Message text.
     * @param string       $url              URL to post.
     * @param string       $urlImage         URL of image to post.
     * @param string       $tags             Message tags.
     * @param int          $postDate         Date/Time to post message at.
     * @param string       $networks         JSON encoded array of Accounts' IDs.
     * @param string|array $files            Images to attach.
     * @param int|null     $postAutoDel      Autodeleting of published posts: -1 - set by template, 0 - disabled, 1 - enabled.
     * @param int|null     $postAutoDelDate  Date/Time to delete published post.
     * @return mixed
     */
    public function publish($project_id, $title, $text, $url, $urlImage, $tags, $postDate, $networks, $files, $postAutoDel = self::POST_AUTODEL_BY_TEMPLATE, $postAutoDelDate = null)
	{
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
		
		$fileParam = [];
		if (is_array($files)) {
			$i = 0;
			foreach ($files as $file) {
				$i++;
				$file_type = filetype($file);
				$file_name = basename($file);

				$fileParam['postFile'.$i] = new CURLFile($file, $file_type, $file_name);
			}
		}
		elseif (is_string($files)) {
			$file_type = filetype($files);
			$file_name = basename($files);
			$fileParam['postFile1'] = new CURLFile($files, $file_type, $file_name);
		}
		$params = ['token'        => $this->token,
		           'prj'          => $project_id,
		           'postName'     => $title,
		           'postText'     => $text,
		           'postUrl'      => $url,
		           'postUrlImage' => $urlImage,
		           'postTags'     => $tags,
		           'postDateTime' => $postDate,
		           'networks'     => $networks,
		];
		
		if ($postAutoDel !== null) {
			$params['postAutodel'] = $postAutoDel;
			if (!empty($postAutoDelDate)) {
				$params['postAutodelDateTime'] = $postAutoDelDate;
			}
		}

		$params += $fileParam;

		curl_setopt($this->ch, CURLOPT_POST,           true);
		curl_setopt($this->ch, CURLOPT_URL,            'https://seolit.ru/index.php?option=com_seolit&task=publish.publish');
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_HEADER,         false);
		curl_setopt($this->ch, CURLOPT_TIMEOUT,        20);
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS,     $params);

		$return = curl_exec   ($this->ch);
		$result = json_decode ($return);

		return $result;
	}
}

// API token
$token = 'your-token';
// Project's ID
$project_id = 50;
// Accounts' IDs array
$networks = json_encode([10, 11, 12]);
// Title of message
$title = 'Первое сообщение, размещённое через API';
// Text of message
$text = "Мы можем разместить достаточно большой текст, в т.ч. с переводами строк.\nС новой строки текст будет размещён в тех соцсетях, которые не игнорируют данный символ.";
// URL to post
$url = 'https://seolit.ru';
// URL of image to post
$urlImage = 'https://seolit.ru/images/seolit-logo.png';
// Tags to post
$tags = '#SEO #news #крымнаш';
// Date/Time to post message at
$postDate = strtotime('Monday next week');
// Images to attach
$files = ['testimage.jpg', 'testimage.jpg'];

$seolit = new Seolit($token);
$res = $seolit->publish($project_id, $title, $text, $url, $urlImage, $tags, $postDate, $networks, $files);
echo $res->result . "\n<br>\n";
if (isset($res->messages)) {
	foreach ($res->messages as $message) {
		echo $message->type . ' => ' . $message->message . "\n<br>\n";
	}
}

// -------------------------------------------------------
// Automatically deleting message

// Title of message
$title = 'Второе сообщение, размещённое через API';
// Text of message
$text .= "\nЭто сообщение удалится через неделю.";
// Enable autodeleting
$postAutoDel     = Seolit::POST_AUTODEL_ENABLED;
// Date/Time to delete published post
$postAutoDelDate = strtotime ('+7 Days', $postDate);

$res = $seolit->publish($project_id, $title, $text, $url, $urlImage, $tags, $postDate, $networks, $files, $postAutoDel, $postAutoDelDate);
echo $res->result . "\n<br>\n";
if (isset($res->messages)) {
	foreach ($res->messages as $message) {
		echo $message->type . ' => ' . $message->message . "\n<br>\n";
	}
}

