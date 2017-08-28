<?php

class Seolit
{
	protected $ch;
	protected $token;

	/**
	 * Seolit constructor.
	 */
	public function __construct($token)
	{
		$this->ch    = curl_init();
		$this->token = $token;
	}

	public function publish($project_id, $title, $text, $url, $urlImage, $tags, $postDate, $networks, $files)
	{
		$fileParam = [];
		if (is_array($files)) {
			$i = 0;
			foreach ($files as $file) {
				$i++;
				$file_type = filetype($file);
				$file_name = basename($file);

				$fileParam['postFile'.$i] = new CURLFile($file, $file_type, $file_name);
			}
		} elseif (is_string($files)) {
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

		$params += $fileParam;

		curl_setopt($this->ch, CURLOPT_POST, true);
		curl_setopt($this->ch, CURLOPT_URL, 'https://seolit.ru/index.php?option=com_seolit&task=publish.publish');
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_HEADER, false);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $params);

		$return = curl_exec($this->ch);
		$result = json_decode($return);
		$res    = $result->result;

		return $res;
	}
}

// API token
$token = 'c51f6ea0d1c9efed7c445a4a76804a';
// Project's ID
$project_id = 50;
// Accounts IDs array
$networks = json_encode([7853, 8206, 5473]);
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
echo $seolit->publish($project_id, $title, $text, $url, $urlImage, $tags, $postDate, $networks, $files);
