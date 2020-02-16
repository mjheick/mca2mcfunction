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
	 * Class Constants
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
	 * Some place to store the data so we can work on it in the class
	 */
	private static $nbt_data = null;

	/**
	 * Some place to store the data so we can work on it in the class
	 */
	private static $nbt_data_length = 0;

	/**
	 * Some place to store the data so we can work on it in the class
	 */
	private static $nbt_data_offset = null;

	/**
	 * A place to make json data with a recursive internal private static function
	 */
	private static $json = '';

	/**
	 * Takes in NBT data, parses it, and returns back a json structure
	 */
	public static function getJSON($nbt_data = "")
	{
		if (PHP_INT_SIZE < 8)
		{
			throw new Exception('PHP_INT_SIZE < 8, this must be run on 64-bit system');
		}
		if (strlen($nbt_data) === 0)
		{
			throw new Exception('parameter nbt_data is empty');
		}
		self::$nbt_data = $nbt_data;
		self::$nbt_data_length = strlen($nbt_data);
		self::$nbt_data_offset = 0;
		/* NBT Tags start off w/ Tag_Complex followed by length=0 */
		/* Check this, and then set nbt_data_offset to proper start */
		if (self::bin2dec(self::read8(0)) != 0x0a)
		{
			throw new Exception('nbt Tag_Complex is not byte 0, it is ' . self::bin2dec(self::read8(0)));
		}
		if (self::bin2dec(self::read16(1)) != 0)
		{
			throw new Exception('nbt Length of first Tag_Complex is not 0');
		}
		self::$nbt_data_offset = 3;

		self::$json = '{';
		while (self::$nbt_data_offset < self::$nbt_data_length)
		{
			/* This does all the work! */
			self::NBT2JSON();
		}
		self::$json .= '}';
		self::Clean_JSON();
		return self::$json;
	}

	private static function NBT2JSON($tag_id_override = null)
	{
		/* When this function is entered into, we're assuming the nbt_data_offset is pointed to a TAG_ID */
		if (is_null($tag_id_override))
		{
			$tag_id = self::read_Tag_ID();
		}
		else
		{
			$tag_id = $tag_id_override;
		}
		if ($tag_id == self::TAG_End)
		{
			return;
		}
		$tag_name = self::read_Tag_Name();

		switch ($tag_id)
		{
			case self::TAG_Byte:
				$data = self::read_TAG_Byte();
				break;
			case self::TAG_Short:
				$data = self::read_TAG_Short();
				break;
			case self::TAG_Int:
				$data = self::read_TAG_Int();
				break;
			case self::TAG_Long:
				$data = self::read_TAG_Long();
				break;
			case self::TAG_Float:
				$data = self::read_TAG_Float();
				break;
			case self::TAG_Double:
				$data = self::read_TAG_Double();
				break;
			case self::TAG_Byte_Array:
				$data = self::read_TAG_Byte_Array();
				break;
			case self::TAG_String:
				$data = self::read_TAG_String();
				break;
			case self::TAG_List:
				$data = self::read_TAG_List();
				break;
			case self::TAG_Int_Array:
				$data = self::read_TAG_Int_Array();
				break;
			case self::TAG_Long_Array:
				$data = self::read_TAG_Long_Array();
				break;
		}
		if ($tag_id == self::TAG_Compound)
		{
			self::$json .= '"' . $tag_name . '": {';
			self::NBT2JSON();
			self::$json .= '},';
		}
		else
		{
			self::$json .= '"' . $tag_name . '": ' . $data . ',';
		}
	}

	/**
	 * Read 8 bytes at offset
	 */
	private static function read8($offset)
	{
		if (self::$nbt_data == null)
		{
			throw new Exception('nbt data not loaded');
		}
		return substr(self::$nbt_data, $offset, 1);
	}

	/**
	 * Read 16 bytes at offset
	 */
	private static function read16($offset)
	{
		return self::read8($offset) . self::read8($offset + 1);
	}

	/**
	 * Read 32 bytes at offset
	 */
	private static function read32($offset)
	{
		return self::read8($offset) . self::read8($offset + 1) . self::read8($offset + 2) . self::read8($offset + 3);
	}

	/**
	 * Read 64 bytes at offset
	 */
	private static function read64($offset)
	{
		return self::read8($offset) . self::read8($offset + 1) . self::read8($offset + 2) . self::read8($offset + 3) . self::read8($offset + 4) . self::read8($offset + 5) . self::read8($offset + 6) . self::read8($offset + 7);
	}

	/**
	 * Convert a binary "string" to a usable number
	 * big-endien
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
	 * Reads the current byte for the tag
	 * This should resolve to one of the above TAG_* constants
	 *
	 * @return integer the TAG_* constant
	 * @throws Exception if we don't support the TAG_* constant
	 */
	private static function read_Tag_ID()
	{
		$tag_id = self::bin2dec(self::read8(self::$nbt_data_offset));
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
	private static function read_Tag_Name()
	{
		$tag_name = '';
		$tag_name_length = self::bin2dec(self::read16(self::$nbt_data_offset));
		self::$nbt_data_offset += 2;
		for ($x = 0; $x < $tag_name_length; $x++)
		{
			$tag_name .= self::read8(self::$nbt_data_offset + $x);
		}
		self::$nbt_data_offset += $tag_name_length;
		return $tag_name;
	}

	private static function read_TAG_Byte()
	{
		$val = 0;
		$val = self::bin2dec(self::read8(self::$nbt_data_offset));
		/* convert to unsigned */
		if ($val > (pow(2, 7) - 1))
		{
			$val -= pow(2, 8);
		}
		self::$nbt_data_offset += 1;
		return $val;
	}

	private static function read_TAG_Short()
	{
		$val = 0;
		$val = self::bin2dec(self::read16(self::$nbt_data_offset));
		/* convert to unsigned */
		if ($val > (pow(2, 15) - 1))
		{
			$val -= pow(2, 16);
		}
		self::$nbt_data_offset += 2;
		return $val;
	}

	private static function read_TAG_Int()
	{
		$val = 0;
		$val = intval(self::bin2dec(self::read32(self::$nbt_data_offset)));
		/* convert to unsigned */
		if ($val > (pow(2, 31) - 1))
		{
			$val = $val - pow(2, 32);
		}
		self::$nbt_data_offset += 4;
		return $val;
	}

	private static function read_TAG_Long()
	{
		$val = 0;
		$val = self::bin2dec(self::read64(self::$nbt_data_offset));
		/* TODO: In 64-bit systems this should automatically wrap. If not, we need to make it happen before bin2dec */
		self::$nbt_data_offset += 8;
		return $val;
	}

	private static function read_TAG_Float()
	{
		/* TODO: Integrate floating point */
		self::$nbt_data_offset += 4;
		return "0.0";
	}

	private static function read_TAG_Double()
	{
		/* TODO: Integrate floating point */
		self::$nbt_data_offset += 8;
		return "0.0";
	}

	private static function read_TAG_Byte_Array()
	{
		$payload_length = self::read_TAG_Int();
		/* An array of bytes */
		$array_of_bytes = [];
		for ($payload_index = 0; $payload_index < $payload_length; $payload_index++)
		{
			$val = self::read_TAG_Byte();
			$array_of_bytes[] = $val;
		}
		$payload_bytes = '[' . implode(',', $array_of_bytes) . ']';
		return $payload_bytes;
	}

	private static function read_TAG_String()
	{
		$payload_length = self::read_TAG_Short();
		/* UTF-8 String */
		$utf8_string = '';
		for ($payload_index = 0; $payload_index < $payload_length; $payload_index++)
		{
			$utf8_string .= self::read8(self::$nbt_data_offset);
			self::$nbt_data_offset++;
		}
		return '"' . $utf8_string . '"';
	}

	private static function read_TAG_List()
	{
		/* TAG_Byte payload */
		$payload_tagid = self::read_TAG_Byte();
		$payload_size = self::read_TAG_Int();

		/* TODO: fix this mess */
		self::$json .= "\"$tag_name\": ["; /* Start of List */
		if ($payload_tagid == 0x0a)
		{
			for ($payload_loop = 0; $payload_loop < $payload_size; $payload_loop++)
			{
				echo " {TAG_List[$payload_loop/$payload_size]}\n";
				self::NBT2JSON();
			}
		}
		self::$json .= "\"$tag_name\": ],"; /* End of List */
		return '[]';
	}

	private static function read_TAG_Int_Array()
	{
		$payload_length = self::read_TAG_Int();
		/* An array of integers */
		$array_of_ints = [];
		for ($payload_index = 0; $payload_index < $payload_length; $payload_index++)
		{
			$val = self::read_TAG_Int();
			$array_of_ints[] = $val;
		}
		$payload_bytes = implode(',', $array_of_ints);
		return '[' . $payload_bytes . ']';
	}

	private static function read_TAG_Long_Array()
	{
		$payload_length = self::read_TAG_Int();
		/* An array of longs */
		$array_of_longs = [];
		for ($payload_index = 0; $payload_index < $payload_length; $payload_index++)
		{
			$val = self::read_TAG_Long();
			$array_of_longs[] = $val;
		}
		$payload_bytes = implode(',', $array_of_longs);
		return '[' . $payload_bytes . ']';
	}

	/**
	 * We're creating bad json with our above routines, so we clean it up
	 */
	private static function Clean_JSON()
	{
		self::$json = str_replace(',}', '}', self::$json);
		self::$json = str_replace('],}', ']}', self::$json);
	}
}