<?
	namespace CDN\Sync;

	use CDN\Inventory\IInventory;
	use CDN\Util\Path;
	use CDN\Util\MimeType;

	class CdnSync {
		/** @var IInventory */
		protected $inventory;
		protected $rootDir = null;
		protected $publishedFiles = [];


		public function __construct(IInventory $inventory, $rootDir = null) {
			$this->inventory = $inventory;
			$this->rootDir = $rootDir;
		}

		public function publish($directories, ICdnAdapter $adapter, $forceUpdate = false) {

			foreach($directories as $currDirectoryAbs) {
				$files = $this->directoryFiles($currDirectoryAbs);

				foreach($files as $currFile) {

					// avoid duplicate file push
					if (empty($this->publishedFiles[$currFile])) {

						$contentType = MimeType::getFromFilename($currFile);

						// get file content
						$content = file_get_contents($currFile);
						if ($content === false)
							throw new \Exception('Could not read file "' . $currFile . '"');

						// push the specified file
						$remoteFile = $adapter->pushFile(
							Path::relativePathTo($currFile, $currDirectoryAbs),
							$content,
							$contentType,
							$forceUpdate
						);

						$this->inventory->put(Path::relativePathTo($currFile, $this->rootDir), $remoteFile->getRemoteFileName(), $remoteFile->getUrl());

						// remember file
						$this->publishedFiles[$currFile] = true;
					}
				}
			}
		}


		/**
		 * Reads all files within a directory
		 * @param string $directory The directory
		 * @return string[] The absolute file names
		 */
		protected function directoryFiles($directory) {
			$array_items = array();
			if ($handle = opendir($directory)) {
				while (false !== ($file = readdir($handle))) {
					if ($file != "." && $file != "..") {
						if (is_dir($directory . "/" . $file)) {
							$array_items = array_merge($array_items, self::directoryFiles($directory . "/" . $file));
						}
						else {
							$file          = $directory . "/" . $file;
							$array_items[] = preg_replace("/\/\//si", "/", $file);
						}
					}
				}
				closedir($handle);
			}

			return $array_items;
		}

	}
