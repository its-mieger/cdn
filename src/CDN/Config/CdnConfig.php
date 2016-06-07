<?
	namespace CDN\Config;

	use CDN\Util\Path;

	class CdnConfig {

		protected $data = false;

		protected $configFile = null;
		protected $relativePathRoot = null;

		public function __construct($configFile = null) {
			$this->configFile = $configFile;
			$this->relativePathRoot = dirname($this->configFile);
		}


		public function setConfigFile($filename) {
			$this->configFile = $filename;
		}

		public function getConfigFile() {
			return $this->configFile;
		}

		public function getRelativePathRoot() {
			return $this->relativePathRoot;
		}

		public function getVendorDir() {
			$this->readConfig();
			
			if (!empty($this->data['vendor-dir']))
				$dir = $this->data['vendor-dir'];
			else
				$dir = 'vendor';

			return Path::absolutePath($dir, $this->relativePathRoot);
		}

		public function getInventoryDir() {
			$this->readConfig();

			if (!empty($this->data['inventory-dir']))
				$dir = $this->data['inventory-dir'];
			else
				$dir = $this->getVendorDir() . '/cdn';

			return Path::absolutePath($dir, $this->relativePathRoot);
		}

		public function getRootDir() {
			$this->readConfig();

			if (!empty($this->data['root-dir']))
				return Path::absolutePath($this->data['root-dir'], $this->relativePathRoot);
			else
				return $this->relativePathRoot;
		}

		public function getRootDirRelative() {
			$this->readConfig();

			if (!empty($this->data['root-dir']))
				return $this->data['root-dir'];
			else
				return null;
		}

		public function getDefaultAdapterConfig() {
			$this->readConfig();

			if (!empty($this->data['default-config']))
				return $this->data['default-config'];
			else
				return [];
		}
		public function getAdapters() {
			$adapters = ['S3' => 'CDN\\Sync\\Adapters\\S3\\S3Adapter'];

			if (!empty($this->data['adapters']))
				$adapters = array_merge($adapters, $this->data['adapters']);

			return $adapters;
		}

		public function getPaths() {
			$this->readConfig();

			if (!empty($this->data['paths'])) {

				$paths = $this->data['paths'];
				if ($this->isAssoc($paths))
					return $paths;
				else
					return array_fill_keys($paths, []);
			}
			else
				return [];
		}


		protected function readConfig() {
			if ($this->data === false) {

				$fn = $this->getConfigFile();
				if (file_exists($fn)) {

					$fileContent = file_get_contents($fn);
					if ($fileContent === false)
						throw new \Exception('Could not read CDN configuration (' . $this->configFile . ')');

					$data = json_decode($fileContent, true);
					if ($data === false)
						throw new \Exception('Invalid JSON in CDN configuration (' . $this->configFile . ')');

					$this->data = $data;
				}
				else {
					$this->data = [];
				}

			}
		}

		protected function isAssoc($arr) {
			return array_keys($arr) !== range(0, count($arr) - 1);
		}


	}