<?
	namespace CDN\Inventory;

	use CDN\Util\Path;

	/**
	 * Base class for file inventories.
	 * @package CDN\Inventory
	 */
	abstract class AbstractFileInventory implements IInventory {

		/**
		 * @var bool|array
		 */
		private $data = false;


		/**
		 * Writes the data to file
		 * @param array $inventoryData The inventory data
		 */
		protected abstract function write(array $inventoryData);

		/**
		 * Reads the inventory data from file
		 * @return array The inventory data. Empty array if not existing
		 */
		protected abstract function read();


		/**
		 * Gets the root directory
		 * @return string|null The root directory or null if none set
		 */
		public function getRoot() {
			$this->loadData();

			return (!empty($this->data['root']) ? $this->data['root'] : null);
		}

		protected function setRoot($root) {
			$this->loadData();

			$this->data['root'] = $root;
		}

		/**
		 * Sets the inventory entry for a remote file
		 * @param string $localFile The local file
		 * @param string $remoteFile The remote file
		 * @param string $url The URL
		 * @return $this
		 */
		public function put($localFile, $remoteFile, $url) {
			$this->loadData();

			$this->data['files'][$localFile] = [
				'remote' => $remoteFile,
				'url'    => $url,
			];

			$this->write($this->data);

			return $this;
		}

		/**
		 * Gets the URL for a specified file
		 * @param string $localFile The local file
		 * @return string|false The URL or false if not existing
		 */
		public function getUrl($localFile) {
			$this->loadData();

			if (!empty($this->data['files'][$localFile]['url']))
				return $this->data['files'][$localFile]['url'];

			return false;
		}

		/**
		 * Gets the remote file name for a specified file
		 * @param string $localFile The local file
		 * @return string|false The remote file name or false if not existing
		 */
		public function getRemoteFile($localFile) {
			$this->loadData();

			if (!empty($this->data['files'][$localFile]['remote']))
				return $this->data['files'][$localFile]['remote'];

			return false;
		}

		public function clear($root = null) {
			$this->loadData();

			$this->data['root'] = $root;
			$this->data['files'] = [];

			$this->write($this->data);

			return $this;
		}

		/**
		 * Loads the inventory data if not already loaded
		 */
		protected function loadData() {
			if ($this->data === false)
				$this->data = $this->read();
		}
	}