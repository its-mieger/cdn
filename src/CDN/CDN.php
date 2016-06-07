<?
	namespace CDN;

	use CDN\Inventory\IInventory;

	/**
	 * Class to retrieve CDN URLs of published files
	 * @package CDN
	 */
	class CDN {

		/**
		 * @var IInventory
		 */
		protected static $inventory;
		protected static $defaultProtocol = 'http';
		protected static $bypassActive = false;

		/**
		 * Sets the CDN inventory. This must be called before getUrl to load the URLs.
		 * @param IInventory $inventory The inventory.
		 */
		public static function loadInventory(IInventory $inventory) {
			self::$inventory = $inventory;
		}

		/**
		 * Gets the CDN URL for the specified local file
		 * @param string $localFile The local file. If relative path is passed the file is searched below
		 * the root directory of the inventory
		 * @param string|null $protocol The protocol to use (eg. "https"). If null the default protocol will be used
		 * @throws NotPublishedException
		 * @throws \Exception
		 * @return string The CDN URL. If bypass is active, the input path will be returned
		 */
		public static function getUrl($localFile, $protocol = null) {

			// bypass?
			if (self::$bypassActive)
				return $localFile;

			// inventory loaded?
			if (!self::$inventory)
				throw new \Exception('No CDN inventory loaded.');

			if ($protocol === null)
				$protocol = self::$defaultProtocol;

			// get URL from inventory
			$url = self::$inventory->getUrl($localFile);

			// throw exception if not found in inventory
			if (empty($url))
				throw new NotPublishedException($localFile);

			return $protocol . '://' . $url;
		}

		/**
		 * Activates or deactivates the CDN bypass. If bypass is active getUrl will return local file names
		 * instead of CDN URLs
		 * @param bool $active True if to activate. Else false.
		 */
		public static function bypass($active = true) {
			self::$bypassActive = $active;
		}

		/**
		 * Sets the default protocol
		 * @param string $protocol The protocol to use (eg. "https")
		 */
		public static function setDefaultProtocol($protocol) {
			self::$defaultProtocol = $protocol;
		}

		/**
		 * Gets the default protocol
		 * @return string The default protocol
		 */
		public static function getDefaultProtocol() {
			return self::$defaultProtocol;
		}

	}