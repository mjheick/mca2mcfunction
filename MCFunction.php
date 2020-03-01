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
	 * @param integer Y-chunkof chunk
	 * @param integer Z-chunkof chunk
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
		 * A chunk doesn't have to have a Y defined. We exception cause it makes no sense to exception for something expected to be missing.
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
		$nbt_Palette = $model_data['Palette'];
		$nbt_BlockStates = $model_data['BlockStates'];
		$palette_length = count($nbt_Palette);
		$blocks = [];
		for ($by = 0; $by < 16; $by++)
		{
			for ($bz = 0; $bz < 16; $bz++)
			{
				for ($bx = 0; $bx < 16; $bx++)
				{
					$block_key = $bx . ',' . $by . ',' . $bz;
					$block_index = ($by * 16 * 16) + ($bz * 16) + $bx;
					$blocks[$block_key] = self::readBlockIndex($block_index, $nbt_Palette, $nbt_BlockStates);
				}
			}
		}
		return $blocks;
	}

	private static function readBlockIndex($block_index, &$palette, &$blockstates)
	{
		$bits_per_index = self::bitlength(count($palette));

		$indexdata = self::bitarray($block_index, $bits_per_index, 64);
		/* $indexdata tells us  */
		$byte = ($blockstates[$indexdata['byte']] >> $indexdata['shr-bits']) & $indexdata['finish-mask'];
		if (array_key_exists('shl-bits_3', $indexdata))
		{
			$byte_2 = ($blockstates[$indexdata['byte_2']] & $indexdata['finish-mask_2']) << $indexdata['shl-bits_3'];
			$byte = $byte + $byte_2;
			$byte = $byte & $indexdata['finish-mask'];
		}

		return $palette[$byte];
	}

	/**
	 * This returns back a bit length based on the integer size of the palette
	 * Minecraft stores block indexes as compressed as possible
	 *
	 * @param integer palette size
	 * @return integer bit length
	 */
	private static function bitlength($palette_length)
	{
		$bit_length = 0;

		$proposed_bit_length = 14; /* Decrement from this */
		while (pow(2, $proposed_bit_length) > $palette_length)
		{
			$proposed_bit_length--;
		}
		$bit_length = $proposed_bit_length + 1;
		return $bit_length;
	}


	/**
	 * Take an offset and a bitlength and return back an array with information on how to extract the data
	 *
	 * If you need 1 byte: (data[byte] >> shr-bits) & finish-mask
	 * If you need 2 bytes: (data[byte_2] & finish-mask_2) << shl-bits_3 | Add with 1 byte, finally & finish-mask
	 *
	 * @param long Offset of data being requested
	 * @param long width of data in bits.
	 * @param long bits per index. byte=8, word=16, long=32, etc.
	 * @return array information to help derive bit-compressed data
	 */
	private static function bitarray($offset = 0, $bitlength = 8, $bpi = 8)
	{
		$data = [];
		/* starting_bit is our calculation in the entire stream where offset is */
		$starting_bit = $offset * $bitlength;
		/* starting_byte is where we tell you which index to perform operations on */
		$data['byte'] = floor($starting_bit / $bpi);
		/* Starting Mask = 0x01 << [starting_bit - (starting_byte * $bpi)] */
		$data['shr-bits'] = $starting_bit - ($data['byte'] * $bpi);
		/* create mask to make sure you get your correct value after shifting */
		$data['finish-mask'] = 1;
		for ($x = 1; $x < $bitlength; $x++)
		{
			$data['finish-mask'] = $data['finish-mask'] << 1;
			$data['finish-mask'] = $data['finish-mask'] + 1;
		}
		/* Does our data extend to the next index? */
		if ($data['shr-bits'] + $bitlength > $bpi)
		{
			$data['byte_2'] = $data['byte'] + 1;
			$data['shl-bits_3'] = $bpi - $data['shr-bits'];

			$data['finish-mask_2'] = 1;
			for ($x = 1; $x < ($bitlength - $data['shl-bits_3']); $x++)
			{
				$data['finish-mask_2'] = $data['finish-mask_2'] << 1;
				$data['finish-mask_2'] = $data['finish-mask_2'] + 1;
			}
		}
		return $data;
	}
}