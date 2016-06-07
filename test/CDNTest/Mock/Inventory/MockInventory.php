<?php

	namespace CDNTest\Mock\Inventory;

	use CDN\Inventory\IInventory;

	class MockInventory implements IInventory
	{
		protected $collection = [];

		public function put($localName, $remoteFile, $prefix = null) {

			if ($prefix === null)
				$this->collection[$localName] = $remoteFile;
			else
				$this->collection[$prefix][$localName] = $remoteFile;
		}

		public function get($localName, $prefix = null) {

			if ($prefix === null) {
				if (!empty($this->collection[$localName]))
					return $this->collection[$localName];
			}
			else {
				if (!empty($this->collection[$prefix][$localName]))
					return $this->collection[$prefix][$localName];
			}

			return null;
		}


	}