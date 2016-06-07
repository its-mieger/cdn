<?
	namespace CDN\Sync\Adapters\S3;

	use Aws\S3\Exception\S3Exception;
	use Aws\S3\S3Client;
	use CDN\Sync\ICdnAdapter;
	use CDN\Inventory\IInventory;
	use CDN\Sync\RemoteFile;
	use CDN\Util\Path;

	class S3Adapter implements ICdnAdapter {


		/** @var IInventory */
		protected $s3Client = null;
		protected $bucket = null;
		protected $rootDir = null;
		protected $appendHash = false;
		protected $distributionURLs = [];
		protected $cacheControl = null;
		protected $additionalMetaData = [];

		/**
		 * Creates an adapter instance from a config array
		 * @param array $configArray The config array
		 * @throws \Exception
		 * @return self
		 */
		public static function fromConfig(array $configArray) {
			if (empty($configArray['aws']['region']))
				throw new \Exception('No region specified for S3 adapter');
			if (empty($configArray['bucket']))
				throw new \Exception('No bucket specified for S3 adapter');
			if (empty($configArray['url']))
				throw new \Exception('No url specified for S3 adapter');

			// options for s3 client
			$s3Options = array_merge(['version' => '2006-03-01'], $configArray['aws']);

			// create adapter
			$client = new self(new S3Client($s3Options), $configArray['bucket'], $configArray['url']);
			$client->setAppendHash(!empty($configArray['append-hash']))
				->setCacheControl(!empty($configArray['cache-control']) ? $configArray['cache-control'] : null)
				->setAdditionalMetaData(!empty($configArray['metadata']) ? $configArray['metadata'] : null)
				->setRootDir(!empty($configArray['target-dir']) ? $configArray['target-dir'] : null);

			return $client;
		}

		/**
		 * Creates a new instance
		 * @param callable|S3Client $s3Client The s3 client instance to use. Also a resolver function is accepted which will be called on first usage
		 * @param string $bucket The bucket to use
		 * @param string[]|string $distributionURLs One or multiple distribution URLs which may be used for URL generation
		 */
		public function __construct($s3Client, $bucket, $distributionURLs) {
			$this->bucket           = $bucket;
			$this->distributionURLs = (is_array($distributionURLs) ? $distributionURLs : [$distributionURLs]);
			$this->s3Client         = $s3Client;

			// append trailing slash to URL
			foreach($this->distributionURLs as &$currUrl) {
				if (substr($currUrl, -1) != '/')
					$currUrl .= '/';
			}
		}

		/**
		 * Gets an s3 client instance to use
		 * @return S3Client The client instance
		 */
		public function getS3Client() {

			// resolve, if resolver function
			if (is_callable($this->s3Client))
				$this->s3Client = call_user_func($this->s3Client);

			return $this->s3Client;
		}

		/**
		 * Gets the bucket used
		 * @return string The bucket used
		 */
		public function getBucket() {
			return $this->bucket;
		}

		/**
		 * Gets the distribution URLs
		 * @return string[] The distribution URLs
		 */
		public function getDistributionURLs() {
			return $this->distributionURLs;
		}

		/**
		 * Gets if the hash will be appended to the specified file name
		 * @return boolean True if to append hash. Else false.
		 */
		public function getAppendHash() {
			return $this->appendHash;
		}

		/**
		 * Sets if to append the hash to the file name
		 * @param boolean $appendHash True if to append. Else false.
		 * @return $this
		 */
		public function setAppendHash($appendHash = true) {
			$this->appendHash = $appendHash;

			return $this;
		}

		/**
		 * Gets the cache control value
		 * @return null|string The cache control value
		 */
		public function getCacheControl() {
			return $this->cacheControl;
		}

		/**
		 * Sets the cache control value
		 * @param null|string $cacheControl The cache control value (eg. "max-age=86400") or null if not to set any cache control
		 * @return $this
		 */
		public function setCacheControl($cacheControl) {
			$this->cacheControl = $cacheControl;

			return $this;
		}

		/**
		 * Gets the prefix for bucket keys
		 * @return null|string The prefix
		 */
		public function getRootDir() {
			return $this->rootDir;
		}

		/**
		 * Sets the root directory
		 * @param null|string $rootDir The root directory
		 * @return $this
		 */
		public function setRootDir($rootDir) {
			$this->rootDir = Path::trimTrailingSlash($rootDir);

			return $this;
		}

		/**
		 * Gets the additional meta data for S3 objects
		 * @return array|callable The additional meta data as array or the resolver function
		 */
		public function getAdditionalMetaData() {
			return $this->additionalMetaData;
		}

		/**
		 * Sets the additional meta data for S3 objects
		 * @param array $additionalMetaData The additional meta as array or a resolver function. File as argument passed to resolver.
		 * @return $this
		 */
		public function setAdditionalMetaData($additionalMetaData) {
			$this->additionalMetaData = $additionalMetaData;

			return $this;
		}


		/**
		 * Publishes the specified file
		 * @param string $filename The file name
		 * @param string $content The file content
		 * @param string $contentType The mime content type
		 * @param bool $forceUpdate True to force file update even if hash matches current version
		 * @throws S3Exception
		 * @return RemoteFile
		 */
		public function pushFile($filename, $content, $contentType, $forceUpdate = false) {

			// get Mime-Type and md5
			$md5 = md5($content);

			// append hash to filename
			if ($this->appendHash)
				$relKey = preg_replace('/((\.[A-z0-9]+)|)$/', '_' . $md5 . '$1', $filename, 1);
			else
				$relKey = $filename;

			$key = ($this->rootDir ? $this->rootDir . '/' : '') . $relKey;

			$s3 = $this->getS3Client();

			// additional meta data
			$additionalMetaData = [];
			if (is_callable($this->additionalMetaData))
				$additionalMetaData = call_user_func($this->additionalMetaData, $filename);
			if (is_array($this->additionalMetaData))
				$additionalMetaData = $this->additionalMetaData;

			$alreadyPublished = false;
			if (!$forceUpdate) {
				// check if the file already exists
				try {
					$headResponse = $s3->headObject([
						'Bucket' => $this->bucket,
						'Key'    => $key
					]);

					$alreadyPublished = true;

					// compare meta data
					if (empty($headResponse['Metadata']['md5']) || $headResponse['Metadata']['md5'] != $md5)
						$alreadyPublished = false;
					foreach($additionalMetaData as $metaKey => $value) {
						if (empty($headResponse['Metadata'][strtolower($metaKey)]) || $headResponse['Metadata'][strtolower($metaKey)] != $value) {
							$alreadyPublished = false;
							break;
						}
					}
				}
				catch (S3Exception $ex) {

					// throw any exception but "NotFound"
					if ($ex->getAwsErrorCode() != 'NotFound')
						throw $ex;
				}
			}

			// upload if not already published
			if (!$alreadyPublished) {

				// meta data
				$metadata = [
					'md5'     => $md5,
					'created' => (new \DateTime())->format('Y-m-d H:i:s')
				];
				$metadata = array_merge($metadata, $additionalMetaData);

				// params
				$params = array(
					'Bucket'      => $this->bucket,
					'Key'         => $key,
					'ACL'         => 'public-read',
					'ContentType' => $contentType,
					'Metadata'    => $metadata,
					'Body'        => $content,
				);
				if ($this->cacheControl !== null)
					$params['CacheControl'] = $this->cacheControl;

				// upload file
				$s3->putObject($params);
			}

			return new RemoteFile(
				$relKey,
				$this->shuffleDistributionUrl($relKey) . $relKey
			);
		}


		/**
		 * Shuffles a distribution URL for the filename. This function will always return the same ULR for a file
		 * but maybe different URLs for different files
		 * @param string $filename The file name
		 * @return string The distribution URL to use for the specified file name
		 */
		protected function shuffleDistributionUrl($filename) {

			$count = count($this->distributionURLs);
			if ($count == 1)
				return $this->distributionURLs[0];

			$crc = crc32($filename);

			return $this->distributionURLs[$crc % $count];
		}

	}