<?
	namespace CDN\Inventory;

	interface IInventory
	{
		/**
		 * Gets the root directory
		 * @return string|null The root directory or null if none set
		 */
		public function getRoot();

		/**
		 * Sets the inventory entry for a remote file
		 * @param string $localFile The local file
		 * @param string $remoteFile The remote file
		 * @param string $url The URL
		 * @return $this
		 */
		public function put($localFile, $remoteFile, $url);

		/**
		 * Gets the URL for a specified file
		 * @param string $localFile The local file
		 * @return string|false The URL or false if not existing
		 */
		public function getUrl($localFile);

		/**
		 * Gets the remote file name for a specified file
		 * @param string $localFile The local file
		 * @return string|false The remote file name or false if not existing
		 */
		public function getRemoteFile($localFile);

	}