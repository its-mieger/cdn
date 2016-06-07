<?php

	namespace CDN\Util;

	class Path {

		const DS = '/';

		/**
		 * Checks if the specified path is relative
		 * @param string $path The path
		 * @return bool True if is relative path. Else false.
		 */
		public static function isRelative($path) {
			return substr($path, 0, 1) != Path::DS;
		}

		/**
		 * Gets the path relative to a specified directory
		 * @param string $path The path
		 * @param string $root The root directory
		 * @return string The path relative to the root directory
		 */
		public static function relativePathTo($path, $root) {
			$path = self::shrinkPath($path);
			$root = self::shrinkPath($root);

			if (substr($path, 0, strlen($root)) != $root)
				return $path;

			$relPath = substr($path, strlen($root));
			if (substr($relPath, 0, 1) == Path::DS)
				$relPath = substr($relPath, 1);

			return $relPath;
		}

		/**
		 * Removes any trailing slashes from path
		 * @param string $path The path
		 * @return string The path
		 */
		public static function trimTrailingSlash($path) {
			if (substr($path, -1) == Path::DS)
				return substr($path, 0, strlen($path) - 1);
			else
				return $path;
		}

		/**
		 * Converts relative paths to an absolute path
		 * @param string $path The path (relative or absolute)
		 * @param string $root The root path
		 * @return string The absolute path
		 */
		public static function absolutePath($path, $root) {
			$path = self::shrinkPath($path);
			$root = self::shrinkPath($root);

			if (!self::isRelative($path))
				return $path;

			return self::trimTrailingSlash($root) . Path::DS . $path;
		}

		public static function shrinkPath($path) {

			$elements = explode(Path::DS, $path);

			if ($elements[0] == '.')
				array_shift($elements);

			$out = [];
			foreach($elements as $index => $curr) {
				if ($curr == '..' && !empty($out))
					array_pop($out);
				else
					$out[] = $curr;
			}

			return implode(Path::DS, $out);

		}

	}