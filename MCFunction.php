<?php

/**
 * working with https://minecraft.gamepedia.com/Chunk_format
 *
 * Extracting world data converted from Anvil -> NBT -> json -> php array
 */
class MCFunction
{
	private static $nbtjson = null;

	public static function loadNBTJSON($data)
	{
		self::$nbtjson = json_decode($data, true);
		if (is_null(self::$nbtjson))
		{
			throw new Exception('error loading json data');
		}
		if (!is_array(self::$nbtjson))
		{
			throw new Exception('nbt data not in php array format');
		}
	}


	/**
	 * Retrieves block data from a Chunk
	 *
	 * @see https://minecraft.gamepedia.com/Chunk_format
	 * @param integer X-chunkof chunk
	 * @param integer X-chunkof chunk
	 * @param integer X-chunkof chunk
	 * @return array decoded data
	 * @throws Exception many, many things can go wrong
	 */
	public static function getChunk($x = 0, $y = 0, $z = 0)
	{
		/**
		 * We should have xPos and zPos of this chunk to compare with
		 */
		if (!array_key_exists('Level', self::$nbtjson))
		{
			throw new Exception('Level nbtblock not found');
		}
		$nbt_level = self::$nbtjson['Level'];
		$xPos = array_key_exists('xPos', $nbt_level) ? $nbt_level['xPos'] : null;
		$zPos = array_key_exists('zPos', $nbt_level) ? $nbt_level['zPos'] : null;
		if (is_null($xPos) || is_null($zPos))
		{
			throw new Exception('Could not find xPos or zPos');
		}
		if (($x != $xPos) || ($z != $zPos))
		{
			throw new Exception('Chunk is not present in NBT, param=[x=' . $x . ',z=' . $z . '], chunk=[x=' . $xPos . ',z=' . $zPos . ']');
		}
		if (!array_key_exists('Sections', $nbt_level))
		{
			throw new Exception('Sections nbtblock not found');
		}
		$nbt_sections = $nbt_level['Sections'];
		/**
		 * Loop thorugh all Sections until we find Y=$y
		 */
		$model_data = [];
		foreach ($nbt_sections as $section)
		{
			if (array_key_exists('Y', $section) && array_key_exists('Palette', $section) && array_key_exists('BlockStates', $section))
			{
				if ($section['Y'] == $y)
				{
					$model_data = $section;
				}
			}
		}
		if (count($model_data) === 0)
		{
			throw new Exception('Chunk Y=' . $y . ' is not present in NBT, param=[x=' . $x . ',z=' . $z . ']');
		}

		/**
		 * Lets make the Builder Array
		 * key=(x,y,z) = blockdata
		 */
		$palette_length = count($model_data['Palette']);
		$blocks = [];
		for ($by = 0; $by < 16; $by++)
		{
			for ($bz = 0; $bz < 16; $bz++)
			{
				for ($bx = 0; $bx < 16; $bx++)
				{
					$block_key = $bx . ',' . $by . ',' . $bz;
					$blockpos = ($by * 16 * 16) + ($bz * 16) + $bx;
				}
			}
		}

		return $model_data;
	}
}