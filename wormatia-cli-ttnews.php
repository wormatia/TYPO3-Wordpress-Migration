<?php

WP_CLI::add_command('wormatia_ttnews', 'WormatiaCliTTNews');

/**
 * Class WormatiaCliTTNews
 */
class WormatiaCliTTNews extends WP_CLI_Command {
	/**
	 * Run tt-news migration
	 *
	 * ## OPTIONS
	 *
	 * <host>
	 * : DB host
	 *
	 * <dbname>
	 * : DB name
	 *
	 * <username>
	 * : DB user
	 *
	 * <password>
	 * : DB password
	 *
	 * <start_uid>
	 * : The first uid to start import
	 *
	 * ## EXAMPLES
	 *
	 *     wp wormatia_ttnews migrate typo3_db_host typo3_db_name typo3_db_username typo3_db_password
	 *
	 * @when after_wp_load
	 */
	public function migrate($args, $assoc_args)
	{
		list($paramHost, $paramDbName, $paramUsername, $paramPassword, $paramStartUid) = $args;

		$defaultCategory = get_cat_ID('Aktuell');
		$wordpressCategories = [
			'aktuell'      => get_cat_ID('Aktuell'),
			'1_mannschaft' => get_cat_ID('1. Mannschaft'),
			'2_mannschaft' => get_cat_ID('2. Mannschaft'),
			'alte_herren'  => get_cat_ID('Alte Herren'),
			'frauen'       => get_cat_ID('Frauen & Mädchen'),
			'jugend'       => get_cat_ID('Jugend'),
		];

		$categoryMap = [
			3 => $wordpressCategories['aktuell'], // Aktuell
			4 => $wordpressCategories['1_mannschaft'], // 1. Mannschaft'
			5 => $wordpressCategories['2_mannschaft'], // '2. Mannschaft
			6 => $wordpressCategories['jugend'],// 'Jugend'
			7 => $wordpressCategories['alte_herren'], // 'AH'
			8 => $wordpressCategories['frauen'], // 'Frauen'
			9 => $wordpressCategories['aktuell'], // 'Fans'
			10 => $wordpressCategories['aktuell'], // 'Aktuell'
			11 => $wordpressCategories['1_mannschaft'], // '1. Mannschaft'
			12 => $wordpressCategories['2_mannschaft'], // '2. Mannschaft')
			13 => $wordpressCategories['jugend'],  // 'Jugend'
			14 => $wordpressCategories['alte_herren'],// 'AH'
			15 => $wordpressCategories['frauen'],// 'Frauen & Mädchen'
			16 => $wordpressCategories['aktuell'], // 'Fans'
			17 => $wordpressCategories['jugend'], // 'Jugend'
			18 => $wordpressCategories['jugend'], // 'A-Jugend'
			19 => $wordpressCategories['jugend'], // 'B-Jugend'
			20 => $wordpressCategories['jugend'],  // 'C-Jugend'
			21 => $wordpressCategories['jugend'], // 'D-Jugend'
			22 => $wordpressCategories['jugend'], // 'E-Jugend'
			23 => $wordpressCategories['jugend'], // 'F-Jugend'
			24 => $wordpressCategories['jugend'], // 'G-Jugend'
			25 => $wordpressCategories['aktuell'],  // 'Top'
			26 => $wordpressCategories['aktuell'], // 'Startseite
			27 => $wordpressCategories['aktuell'], // 'Presseinfo Regionalliga Südwest'
			28 => $wordpressCategories['aktuell'], // 'eSport
		];

		$userNameMap = $this->getAuthorMap();

		$pdoDsn = sprintf('mysql:host=%s;dbname=%s;port=3306;charset=utf8', $paramHost, $paramDbName);
		$sourceConnection = new PDO($pdoDsn, $paramUsername, $paramPassword);
		$sourceConnection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		WP_CLI::line('opened source connection');

		$ttNewsCategories = $sourceConnection->query(
			'SELECT uid_local, uid_foreign FROM tt_news_cat_mm'
		);

		foreach ($ttNewsCategories->fetchAll() as $row) {
			$newsCategoryMap[$row['uid_local']][] = $categoryMap[$row['uid_foreign']];
		}

		$sql = sprintf(
			'SELECT * FROM tt_news WHERE hidden=0 AND deleted=0 AND uid >= %d',
			$paramStartUid
		);

		$ttNewsStatement = $sourceConnection->query($sql);

		while ($ttNewsRow = $ttNewsStatement->fetch()) {
			if (empty($ttNewsRow['bodytext'])) {
				WP_CLI::warning(sprintf('ERROR: %s', 'Empty ID: ' . $ttNewsRow['uid']));

				continue;
			}

			if (empty($ttNewsRow['title'])) {
				$ttNewsRow['title'] = strtok($ttNewsRow['bodytext'], "\n");
			}

			//$title = iconv("ISO8859-1","UTF-8//IGNORE", $ttNewsRow['title']);
			//$bodyText = iconv("ISO8859-1","UTF-8//IGNORE", $ttNewsRow['bodytext']);
			//$title = iconv("UTF-8", "UTF-8//IGNORE", $ttNewsRow['title']);
			//$bodyText = iconv("UTF-8", "UTF-8//IGNORE", $ttNewsRow['bodytext']);
			$title = $ttNewsRow['title'];
			$bodyText = str_replace("\n", "<p></p>", $ttNewsRow['bodytext']);

			$postDateTime = (new DateTimeImmutable())->setTimestamp($ttNewsRow['datetime']);
			$postDateTime = $postDateTime->setTimezone(new DateTimeZone('Europe/Berlin'));
			$postDate = $postDateTime->format('Y-m-d H:i:s');

			$postDateUTC = $postDateTime->setTimezone(new DateTimeZone('UTC'));
			$postDateGmt = $postDateUTC->format('Y-m-d H:i:s');

			if (post_exists($title, '', $postDate)) {
				WP_CLI::line(sprintf('Skip already imported UID: %d, title: %s', $ttNewsRow['uid'], $title));

				continue;
			}

			if (!isset($userNameMap[$ttNewsRow['author']])) {
				$authorId = "0";
				WP_CLI::error(sprintf('cannot assign author %s', $ttNewsRow['author']), false);
			} else {
				$authorId = (string) $userNameMap[$ttNewsRow['author']];
			}

			$postarr = [
				'post_title' => $title,
				'post_content' => $bodyText ? $bodyText : '',
				'post_date' => $postDate,
				'post_date_gmt' => $postDateGmt,
				'post_author' => $authorId,
				'post_status' => 'publish',
				'post_type' => 'post',
			];

			// Save post
			$newPostId = wp_insert_post($postarr, true);

			if ($newPostId instanceof WP_Error) {
				WP_CLI::error(sprintf('ERROR: %s | UID: %d', $newPostId->get_error_code(), $ttNewsRow['uid']), false);

				continue;
			} else {
				WP_CLI::line(sprintf('Imported: %s -> %s', $postDate, $title));
			}

			// Set categories
			if (isset($newsCategoryMap[$ttNewsRow['uid']])) {
				wp_set_post_categories($newPostId, $newsCategoryMap[$ttNewsRow['uid']]);
			} else {
				wp_set_post_categories($newPostId, $defaultCategory);
			}

			if (empty($ttNewsRow['image'])) {
				continue;
			}

			// Download image
			$wpUploadDir = wp_upload_dir();

			$ttNewsImages = explode(',', $ttNewsRow['image']);

			$galleryImages = [];

			foreach ($ttNewsImages as $key => $ttNewsImage) {
				$uploadFilePath = $wpUploadDir['path'] . '/' . $ttNewsImage;

				WP_CLI::line(sprintf('     --- Image: %s', $uploadFilePath));

				if (!file_exists($uploadFilePath)) {
					$contents = file_get_contents('https://www.wormatia.de/uploads/pics/' . $ttNewsImage);
					$saveFile = fopen($uploadFilePath, 'w');
					fwrite($saveFile, $contents);
					fclose($saveFile);
				}

				if ($key === 0) {
					$this->addFeaturedImage($uploadFilePath, $ttNewsRow, $newPostId);
				} else {
					$galleryImages[] = $uploadFilePath;
				}
			}

			if (count($galleryImages) > 0) {
				$this->addImagGallery($galleryImages, $ttNewsRow, $newPostId);
			}
		}
	}

	/**
	 * Covert TYPO3 style links in imported news content
	 *
	 * @when after_wp_load
	 */
	public function convertTypo3Links($args, $assoc_args)
	{
		global $wpdb;

		$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASSWORD);
		$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

		$statement = $pdo->query("SELECT * FROM {$wpdb->posts} WHERE post_type = 'post' ");

		while ($postRow = $statement->fetch()) {

			if (!preg_match('/<link\s(ftp:\/\/|https?:\/\/|\/)([^\s]+)[^>]*>(.+?)<\/link>/', $postRow['post_content'])) {
				continue;
			}

			$replacedPostContent = preg_replace(
				'/<link\s(ftp:\/\/|https?:\/\/|\/)([^\s]+)[^>]*>(.+?)<\/link>/',
				'<a href="\\1\\2">\\3</a>',
				$postRow['post_content']
			);

			$post = get_post((int)$postRow['ID']);
			$post->post_content = $replacedPostContent;

			$result = wp_update_post($post, true);

			if ($result instanceof WP_Error) {
				WP_CLI::error(sprintf('ERROR: %s | ID: %d', $result->get_error_code() . ' ' . $result->get_error_message() , $postRow['ID']), false);
			} else {
				WP_CLI::line(sprintf('Updated post ID: %d', $postRow['ID']));
			}
		}
	}

	/**
	 * Assign authors to blog posts
	 *
	 * ## OPTIONS
	 *
	 * <host>
	 * : DB host
	 *
	 * <dbname>
	 * : DB name
	 *
	 * <username>
	 * : DB user
	 *
	 * <password>
	 * : DB password
	 *
	 * <start_uid>
	 * : The first uid to start import
	 *
	 * ## EXAMPLES
	 *
	 *     wp wormatia_ttnews assignPostAuthors typo3_db_host typo3_db_name typo3_db_username typo3_db_password
	 *
	 * @when after_wp_load
	 */
	public function assignPostAuthors($args, $assoc_args)
	{
		global $wpdb;

		list($paramHost, $paramDbName, $paramUsername, $paramPassword, $paramStartUid) = $args;

		$userNameMap = $this->getAuthorMap();

		$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASSWORD);
		$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

		$pdoDsn = sprintf('mysql:host=%s;dbname=%s;port=3306;charset=utf8', $paramHost, $paramDbName);
		$sourceConnection = new PDO($pdoDsn, $paramUsername, $paramPassword);
		$sourceConnection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		WP_CLI::line('opened source connection');

		$sql = sprintf(
			'SELECT * FROM tt_news WHERE hidden=0 AND deleted=0 AND uid >= %d ORDER BY uid ASC',
			$paramStartUid
		);

		$ttNewsStatement = $sourceConnection->query($sql);

		while ($ttNewsRow = $ttNewsStatement->fetch()) {
			$title = $ttNewsRow['title'];
			$postDate = date('Y-m-d H:i:s', $ttNewsRow['datetime']);

			if (!isset($userNameMap[$ttNewsRow['author']])) {
				WP_CLI::error(sprintf('cannot assign author %s', $ttNewsRow['author']), false);

				continue;
			}

			$postId = post_exists($title, '', $postDate, 'post');

			if ($postId > 0) {
				$post = get_post($postId);

				if ((int)$post->post_author > 0) {
					WP_CLI::line(sprintf('Skip already updated post ID: %d', $post->ID));
					continue;
				}

				$post->post_author = (string) $userNameMap[$ttNewsRow['author']];
				wp_update_post($post);

				WP_CLI::line(sprintf('Updated post ID: %d', $post->ID));
			}
		}
	}

	/**
	 * Add featured image
	 *
	 * @param string $uploadFilePath
	 * @param array $ttNewsRow
	 * @param int $newPostId
	 */
	private function addFeaturedImage(string $uploadFilePath, $ttNewsRow, $newPostId): void
	{
		$attachId = $this->addAttachment($uploadFilePath, $ttNewsRow, $newPostId);
		set_post_thumbnail($newPostId, $attachId);
	}

	/**
	 * @param array $uploadFilePathArray
	 * @param array $ttNewsRow
	 * @param int $newPostId
	 */
	private function addImagGallery(array $uploadFilePathArray, $ttNewsRow, $newPostId): void
	{
		$attachementIds = [];

		foreach ($uploadFilePathArray as $uploadedFilePath) {
			$attachementIds[] = $this->addAttachment($uploadedFilePath, $ttNewsRow, $newPostId);
		}

		$post = get_post($newPostId);
		$post->post_content .= '[gallery ids="' . implode(',', $attachementIds) . '"]' . "\n";

		WP_CLI::line(sprintf('     --- Image-Gallery: %s', implode(',', $attachementIds)));

		wp_update_post($post);
	}

	/**
	 * @param string $uploadFilePath
	 * @param array $ttNewsRow
	 * @param int $newPostId
	 * @return int|\WP_Error
	 */
	private function addAttachment(string $uploadFilePath, array $ttNewsRow, int $newPostId)
	{
		$filetype = wp_check_filetype(basename($uploadFilePath), null);

		$attachment = array(
			'post_mime_type' => $filetype['type'],
			'post_title' => sanitize_file_name(basename($uploadFilePath)),
			'post_content' => $ttNewsRow['imagecaption'] ? $ttNewsRow['imagecaption'] : '',
			'post_status' => 'inherit'
		);

		$attachId = wp_insert_attachment($attachment, $uploadFilePath, $newPostId);
		// Attachment has its ID too "$attach_id"
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		$attachData = wp_generate_attachment_metadata($attachId, $uploadFilePath);
		wp_update_attachment_metadata($attachId, $attachData);

		return $attachId;
	}

	/**
	 * @return array
	 */
	private function getAuthorMap(): array
	{
		$userNameMap = [];
		foreach (get_users() as $user) {
			/** @var $user WP_User */
			$userNameMap[$user->display_name] = $user->ID;
		}
		return $userNameMap;
	}
}
