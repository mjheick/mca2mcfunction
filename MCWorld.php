<?php
/**
 * Class MCWorld
 * Lets take apart some minecraft worlds
 *
 * @see https://minecraft.gamepedia.com/Chunk_format
 * @see https://minecraft.gamepedia.com/NBT_format
 * @see https://minecraft.gamepedia.com/Region_file_format
 * @see https://web.archive.org/web/20110723210920/http://www.minecraft.net/docs/NBT.txt
 */

class MCWorld
{
	/**
	 * @var array a list of MCA files that we have loaded
	 * index is the filename, data is an object of the parsed data
	 */
	private static $MCA_Files = [];

	/**
	 * @var constants for defining blocks in NBT data
	 */
	const TAG_End = 0x00;
	const TAG_Byte = 0x01;
	const TAG_Short = 0x02;
	const TAG_Int = 0x03;
	const TAG_Long = 0x04;
	const TAG_Float = 0x05;
	const TAG_Double = 0x06;
	const TAG_Byte_Array = 0x07;
	const TAG_String = 0x08;
	const TAG_List = 0x09;
	const TAG_Compound = 0x0a;
	const TAG_Int_Array = 0x0b;
	const TAG_Long_Array = 0x0c;

	/**
	 * @var Some place to store the data so we can work on it in the class
	 */
	private static $nbt_data_length = 0;

	/**
	 * @var Some place to store the data so we can work on it in the class
	 */
	private static $nbt_data_offset = null;

	/**
	 * @var A place to make json data with a recursive internal private static function
	 */
	private static $json = '';

	/**
	 * #1 - Loading of MCA files
	 *
	 * @param string|array a list of MCA files, including path
	 * @return number quantity of files correctly loaded
	 * @throws Exception Files not found, Can't load NBT
	 */
	public static function loadFiles($files)
	{
		/* Sanity Checking */
		if (PHP_INT_SIZE < 8)
		{
			throw new Exception('PHP_INT_SIZE < 8, this must be run on 64-bit system');
		}

		$loaded_files = 0;
		self::$MCA_Files = [];
		if (!is_array($files))
		{
			$files = [$files];
		}
		foreach($files as $file)
		{
			$filename = basename($file);
			try
			{
				$mca_data = self::readMCA($file);
				self::$MCA_Files[$filename] = [];
				/* Load all 1024 chunks */
				for ($chunk_z = 0; $chunk_z < 32; $chunk_z++)
				{
					for ($chunk_x = 0; $chunk_x < 32; $chunk_x++)
					{
						$nbt_chunk_data = self::readChunk($mca_data, $chunk_x, $chunk_z);
						if (!is_null($nbt_chunk_data))
						{
							$nbt_array_data = self::nbt2array($nbt_chunk_data);
							$xPos = null;
							$zPos = null;
							if (array_key_exists('Level', $nbt_array_data))
							{
								$xPos = array_key_exists('xPos', $nbt_array_data['Level']) ? $nbt_array_data['Level']['xPos'] : 'x';
								$zPos = array_key_exists('zPos', $nbt_array_data['Level']) ? $nbt_array_data['Level']['zPos'] : 'x';
								self::$MCA_Files[$filename][$xPos . ',' . $zPos] = $nbt_array_data;
							}
						}
					}
				}
				$loaded_files++;
			}
			catch (Exception $e)
			{
				// Quashing exceptions on loader right now
				//echo "{{" . $e->getMessage() . "}}\n";
			}
		}
		return $loaded_files;
	}

	/**
	 * Get Block data from a chunk, 16x16x16
	 *
	*/
	public static function getChunk($x = 0, $y = 0, $z = 0)
	{
		/**
		 * See if we have a file loaded w/ the necessary X/Z chunks
		 */
		foreach (self::$MCA_Files as $stored_file => $stored_file_data)
		{
			foreach ($stored_file_data as $positional_key => $chunk_data_array)
			{
				list($xPos, $zPos) = explode(',', $positional_key);
				if (($xPos == $x) && ($zPos == $z))
				{
					$chunkdata = [];
					try
					{
						$chunkdata = self::getChunkData($chunk_data_array, $xPos, $y, $zPos);
					}
					catch (Exception $e)
					{

					}
					return $chunkdata;
				}
			}
		}
	}

	/**
	 * Load a MCA file into the class
	 *
	 * @param string Path and File to MCA file
	 * @return mixed Data read from file
	 * @throws Exception issues in reading file
	 */
	private static function readMCA($filename)
	{
		$mca_data = null;
		$handle = fopen($filename, 'rb');
		if (!$handle)
		{
			throw new Exception('file ' . $filename . ' cannot be opened for read');
		}
		$mca_data = fread($handle, filesize($filename));
		fclose($handle);
		return $mca_data;
	}

	/**
	 * Reads chunk data
	 * @param mixed data stream
	 * @param number X 
	 * @param number Z
	 * @return mixed NBT data read from Chunk
	 */
	private static function readChunk($data, $x, $z)
	{
		/* calculate the offset (0 >= x|z >= 31) */
		if ((($x < 0) || ($x > 31)) || (($z < 0) || ($z > 31)))
		{
			throw new Exception('chunk parameter out of range 0-31, x=' . $x . ',z=' . $z);
		}
		$chunkOffset = self::chunkOffset($x, $z);

		/* First 3 bytes is offset of chunk data in 4k sectors from start of file */
		/* byte 4 is length of chunk in 4k sectors */
		$chunkDataStart = self::bin2dec(self::read8($data, $chunkOffset) . self::read8($data, $chunkOffset + 1) . self::read8($data, $chunkOffset + 2)) * 4096;
		$chunkDataLength = self::bin2dec(self::read8($data, $chunkOffset + 3)) * 4096;

		if ($chunkDataStart > strlen($data))
		{
			throw new Exception('chunk offset dataStart past end of chunk, x=' . $x . ',z=' . $z . ',chunkStart' . $chunkStart . ',mca_length' . strlen($data));
		}

		/* Chunk does not exist */
		if (($chunkDataStart == 0) && ($chunkDataLength == 0))
		{
			return null;
		}

		/* Working on the actual chunk now */
		$chunk_length = self::bin2dec(self::read32($data, $chunkDataStart)); /* 4 bytes: length of chunk */
		$chunk_compression = self::bin2dec(self::read8($data, $chunkDataStart + 4)); /* 1 byte: compression of data */
		$chunk_data = substr($data, $chunkDataStart + 5, $chunk_length);
		if ($chunk_compression == 1)
		{
			throw new Exception('unsupported chunk compression GZip (RFC1952)');
		}
		if ($chunk_compression == 2)
		{
			$chunk_data = gzinflate( substr($chunk_data, 2) );
		}
		if (($chunk_compression != 1) && ($chunk_compression != 2))
		{
			throw new Exception('unsupported chunk compression (value=' . $chunk_compression . ')');
		}
		return $chunk_data;
	}

	/**
	 * Convert a binary "string" to a usable number / big-endien
	 *
	 * @param mixed binary data, up to 64-bit (8 bytes)
	 * @return number numeric representation of binary data
	 */
	private static function bin2dec($binary_data)
	{
		$decimal = 0;
		while (strlen($binary_data) > 0)
		{
			$decimal *= 256; /* shift result left by 8 bytes */
			$byte = substr($binary_data, 0, 1);
			$byte = hexdec('0x' . bin2hex($byte));
			if (strlen($binary_data) > 1)
			{
				$binary_data = substr($binary_data, 1);
			}
			else
			{
				$binary_data = "";
			}
			if (( $byte & 0x80) == 0x80) { $decimal += 128; }
			if (( $byte & 0x40) == 0x40) { $decimal += 64; }
			if (( $byte & 0x20) == 0x20) { $decimal += 32; }
			if (( $byte & 0x10) == 0x10) { $decimal += 16; }
			if (( $byte & 0x08) == 0x08) { $decimal += 8; }
			if (( $byte & 0x04) == 0x04) { $decimal += 4; }
			if (( $byte & 0x02) == 0x02) { $decimal += 2; }
			if (( $byte & 0x01) == 0x01) { $decimal += 1; }
		}
		return (int)$decimal;
	}

	/**
	 * Location of chunk data in mca file
	 *
	 * @see https://minecraft.gamepedia.com/Region_file_format
	 * @param number X chunk
	 * @param number Z chunk
	 * @return number offset in NBT to locate the chunk
	 */
	private static function chunkOffset($x, $z)
	{
		return (4 * (($x % 32) + ($z % 32) * 32));
	}

	/**
	 * Read 8 bytes at offset
	 *
	 * @param mixed data stream
	 * @param number offset
	 * @return mixed data present at offset in data
	 */
	private static function read8($data, $offset)
	{
		return substr($data, $offset, 1);
	}

	/**
	 * Read 16 bytes at offset
	 *
	 * @param mixed data stream
	 * @param number offset
	 * @return mixed data present at offset in data
	 */
	private static function read16($data, $offset)
	{
		return self::read8($data, $offset) . self::read8($data, $offset + 1);
	}

	/**
	 * Read 32 bytes at offset
	 *
	 * @param mixed data stream
	 * @param number offset
	 * @return mixed data present at offset in data
	 */
	private static function read32($data, $offset)
	{
		return self::read8($data, $offset) . self::read8($data, $offset + 1) . self::read8($data, $offset + 2) . self::read8($data, $offset + 3);
	}

	/**
	 * Takes in NBT data, parses it, and returns back an array structure
	 *
	 * @param mixed nbt_data binary nbt data
	 * @return array NBT data converted to PHP array from JSON
	 * @throws Exception something...
	 */
	private static function nbt2array($nbt_data = "")
	{
		if (strlen($nbt_data) === 0)
		{
			throw new Exception('parameter nbt_data is empty');
		}
		self::$nbt_data_length = strlen($nbt_data);
		self::$nbt_data_offset = 0;
		/* NBT Tags start off w/ Tag_Complex followed by length=0 */
		/* Check this, and then set nbt_data_offset to proper start */
		if (self::bin2dec(self::read8($nbt_data, 0)) != 0x0a)
		{
			throw new Exception('nbt Tag_Complex is not byte 0, it is ' . self::bin2dec($nbt_data, self::read8(0)));
		}
		if (self::bin2dec(self::read16($nbt_data, 1)) != 0)
		{
			throw new Exception('nbt Length of first Tag_Complex is not 0');
		}
		self::$nbt_data_offset = 3;

		self::$json = '{';
		while (self::$nbt_data_offset < self::$nbt_data_length)
		{
			/* This does all the work! */
			$data = self::NBT2JSON($nbt_data);
			if (strlen(self::$json) > 1)
			{
				self::$json .= ',';
			}
			self::$json .= $data;
		}
		self::$json .= '}';
		/* We're creating bad json with our above routines, so we clean it up */
		self::$json = str_replace(',}', '}', self::$json);
		self::$json = str_replace('],}', ']}', self::$json);
		$json = self::$json;
		self::$json = null;
		return json_decode($json, true);;
	}

	private static function NBT2JSON($nbt_data)
	{
		/* When this function is entered into, we're assuming the nbt_data_offset is pointed to a TAG_ID */
		$tag_id = self::read_Tag_ID($nbt_data);
		if ($tag_id == self::TAG_End)
		{
			return null;
		}
		$tag_name = self::read_Tag_Name($nbt_data);

		switch ($tag_id)
		{
			case self::TAG_Byte:
				$data = self::read_TAG_Byte($nbt_data);
				break;
			case self::TAG_Short:
				$data = self::read_TAG_Short($nbt_data);
				break;
			case self::TAG_Int:
				$data = self::read_TAG_Int($nbt_data);
				break;
			case self::TAG_Long:
				$data = self::read_TAG_Long($nbt_data);
				break;
			case self::TAG_Float:
				$data = self::read_TAG_Float($nbt_data);
				break;
			case self::TAG_Double:
				$data = self::read_TAG_Double($nbt_data);
				break;
			case self::TAG_Byte_Array:
				$data = self::read_TAG_Byte_Array($nbt_data);
				break;
			case self::TAG_String:
				$data = self::read_TAG_String($nbt_data);
				break;
			case self::TAG_List:
				$data = self::read_TAG_List($nbt_data);
				break;
			case self::TAG_Compound:
				$data = self::read_TAG_Compound($nbt_data);
				break;
			case self::TAG_Int_Array:
				$data = self::read_TAG_Int_Array($nbt_data);
				break;
			case self::TAG_Long_Array:
				$data = self::read_TAG_Long_Array($nbt_data);
				break;
			default:
				$data = 'unset';
				break;
		}

		$tag_data = '"' . $tag_name . '": ' . $data;
		return $tag_data;
	}

	/**
	 * Read 64 bytes at offset
	 */
	private static function read64($data, $offset)
	{
		return self::read8($data, $offset) . self::read8($data, $offset + 1) . self::read8($data, $offset + 2) . self::read8($data, $offset + 3) . self::read8($data, $offset + 4) . self::read8($data, $offset + 5) . self::read8($data, $offset + 6) . self::read8($data, $offset + 7);
	}

	/**
	 * Reads the current byte for the tag
	 * This should resolve to one of the above TAG_* constants
	 *
	 * @return integer the TAG_* constant
	 * @throws Exception if we don't support the TAG_* constant
	 */
	private static function read_Tag_ID($nbt_data)
	{
		$tag_id = self::bin2dec(self::read8($nbt_data, self::$nbt_data_offset));
		if (!(($tag_id >= self::TAG_End) && ($tag_id <= self::TAG_Long_Array)))
		{
			throw new Exception('read_Tag_ID, invalid tagid [' . $tag_id . '] at offset [' . self::$nbt_data_offset . ']');
		}
		self::$nbt_data_offset++;
		return $tag_id;
	}

	/**
	 * Tags (except for TAG_End) have names. Return the name.
	 *
	 * @return string Name of Tag
	 */
	private static function read_Tag_Name($nbt_data)
	{
		$tag_name = '';
		$tag_name_length = self::bin2dec(self::read16($nbt_data, self::$nbt_data_offset));
		self::$nbt_data_offset += 2;
		for ($x = 0; $x < $tag_name_length; $x++)
		{
			$tag_name .= self::read8($nbt_data, self::$nbt_data_offset + $x);
		}
		self::$nbt_data_offset += $tag_name_length;
		return $tag_name;
	}

	private static function read_TAG_Byte($nbt_data)
	{
		$val = 0;
		$val = self::bin2dec(self::read8($nbt_data, self::$nbt_data_offset));
		/* convert to unsigned */
		if ($val > (pow(2, 7) - 1))
		{
			$val -= pow(2, 8);
		}
		self::$nbt_data_offset += 1;
		return $val;
	}

	private static function read_TAG_Short($nbt_data)
	{
		$val = 0;
		$val = self::bin2dec(self::read16($nbt_data, self::$nbt_data_offset));
		/* convert to unsigned */
		if ($val > (pow(2, 15) - 1))
		{
			$val -= pow(2, 16);
		}
		self::$nbt_data_offset += 2;
		return $val;
	}

	private static function read_TAG_Int($nbt_data)
	{
		$val = 0;
		$val = intval(self::bin2dec(self::read32($nbt_data, self::$nbt_data_offset)));
		/* convert to unsigned */
		if ($val > (pow(2, 31) - 1))
		{
			$val = $val - pow(2, 32);
		}
		self::$nbt_data_offset += 4;
		return $val;
	}

	private static function read_TAG_Long($nbt_data)
	{
		$val = 0;
		$val = self::bin2dec(self::read64($nbt_data, self::$nbt_data_offset));
		/* TODO: In 64-bit systems this should automatically wrap. If not, we need to make it happen before bin2dec */
		self::$nbt_data_offset += 8;
		return $val;
	}

	private static function read_TAG_Float($nbt_data)
	{
		/* TODO: Integrate floating point */
		self::$nbt_data_offset += 4;
		return "0.0";
	}

	private static function read_TAG_Double($nbt_data)
	{
		/* TODO: Integrate floating point */
		self::$nbt_data_offset += 8;
		return "0.0";
	}

	private static function read_TAG_Byte_Array($nbt_data)
	{
		$payload_length = self::read_TAG_Int($nbt_data);
		/* An array of bytes */
		$array_of_bytes = [];
		for ($payload_index = 0; $payload_index < $payload_length; $payload_index++)
		{
			$val = self::read_TAG_Byte($nbt_data);
			$array_of_bytes[] = $val;
		}
		$payload_bytes = '[' . implode(',', $array_of_bytes) . ']';
		return $payload_bytes;
	}

	private static function read_TAG_String($nbt_data)
	{
		$payload_length = self::read_TAG_Short($nbt_data);
		/* UTF-8 String */
		$utf8_string = '';
		for ($payload_index = 0; $payload_index < $payload_length; $payload_index++)
		{
			$utf8_string .= self::read8($nbt_data, self::$nbt_data_offset);
			self::$nbt_data_offset++;
		}
		return '"' . $utf8_string . '"';
	}

	private static function read_TAG_Int_Array($nbt_data)
	{
		$payload_length = self::read_TAG_Int($nbt_data);
		/* An array of integers */
		$array_of_ints = [];
		for ($payload_index = 0; $payload_index < $payload_length; $payload_index++)
		{
			$val = self::read_TAG_Int($nbt_data);
			$array_of_ints[] = $val;
		}
		$payload_bytes = implode(',', $array_of_ints);
		return '[' . $payload_bytes . ']';
	}

	private static function read_TAG_Long_Array($nbt_data)
	{
		$payload_length = self::read_TAG_Int($nbt_data);
		/* An array of longs */
		$array_of_longs = [];
		for ($payload_index = 0; $payload_index < $payload_length; $payload_index++)
		{
			$val = self::read_TAG_Long($nbt_data);
			$array_of_longs[] = $val;
		}
		$payload_bytes = implode(',', $array_of_longs);
		return '[' . $payload_bytes . ']';
	}

	private static function read_TAG_Compound($nbt_data)
	{
		$compound_data = '';
		$recursive_data = self::NBT2JSON($nbt_data);
		while (!is_null($recursive_data))
		{
			if (!is_null($recursive_data))
			{
				if (strlen($compound_data) > 0)
				{
					$compound_data .= ',';
				}
				$compound_data .= $recursive_data;
			}
			$recursive_data = self::NBT2JSON($nbt_data);
		} 
		return '{' . $compound_data . '}';
	}

	private static function read_TAG_List($nbt_data)
	{
		$payload_tagid = self::read_TAG_Byte($nbt_data);
		$payload_size = self::read_TAG_Int($nbt_data);
		$payload_items = [];
		for ($payload_x = 0; $payload_x < $payload_size; $payload_x++)
		{
			switch ($payload_tagid)
			{
				case self::TAG_Byte:
					$data = self::read_TAG_Byte($nbt_data);
					break;
				case self::TAG_Short:
					$data = self::read_TAG_Short($nbt_data);
					break;
				case self::TAG_Int:
					$data = self::read_TAG_Int($nbt_data);
					break;
				case self::TAG_Long:
					$data = self::read_TAG_Long($nbt_data);
					break;
				case self::TAG_Float:
					$data = self::read_TAG_Float($nbt_data);
					break;
				case self::TAG_Double:
					$data = self::read_TAG_Double($nbt_data);
					break;
				case self::TAG_Byte_Array:
					$data = self::read_TAG_Byte_Array($nbt_data);
					break;
				case self::TAG_String:
					$data = self::read_TAG_String($nbt_data);
					break;
				case self::TAG_List:
					$data = self::read_TAG_List($nbt_data);
					break;
				case self::TAG_Compound:
					$data = self::read_TAG_Compound($nbt_data);
					break;
				case self::TAG_Int_Array:
					$data = self::read_TAG_Int_Array($nbt_data);
					break;
				case self::TAG_Long_Array:
					$data = self::read_TAG_Long_Array($nbt_data);
					break;
				default:
					$data = 'unset';
					break;
			}
			$payload_items[] = $data;
		}
		$payload_list = '[' . implode(',', $payload_items) . ']';
		return $payload_list;
	}

	/**
	 * Retrieves block data from a Chunk
	 *
	 * @see https://minecraft.gamepedia.com/Chunk_format
	 * @param array NBT data
	 * @param integer X-chunkof chunk
	 * @param integer Y-chunkof chunk
	 * @param integer Z-chunkof chunk
	 * @return array decoded data
	 * @throws Exception many, many things can go wrong
	 */
	private static function getChunkData($nbt_array = [], $x = 0, $y = 0, $z = 0)
	{
		/**
		 * We should have xPos and zPos of this chunk to compare with
		 */
		if (!array_key_exists('Level', $nbt_array))
		{
			throw new Exception('Level nbtblock not found');
		}
		$nbt_level = $nbt_array['Level'];
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