<?php

/**
 * Take binary data and parse out NBT data
 *
 * @see https://minecraft.gamepedia.com/NBT_format
 * @see https://web.archive.org/web/20110723210920/http://www.minecraft.net/docs/NBT.txt
 */

class NBT
{
	/**
	 * Some place to store the data so we can work on it in the class
	 */
	public static $data = null;

	/**
	 * Init. Load data
	 */
	public static function loadData($data)
	{
		self::$data = $data;
	}

	/**
	 * Reads the data that's stored (if any)
	 * Parses it out, and returns back parseable json
	 */
	public static function saveJSON()
	{
		return "{}";
	}
}