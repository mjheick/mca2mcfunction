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
			self::NBT2JSON(); /* This does all the work! */
		}
		return self::$json;
	}

	private static function NBT2JSON()
	{
		/* When this function is entered into, we're assuming the nbt_data_offset is pointed to a TAG_ID */
		$tag_id = self::bin2dec(self::read8(self::$nbt_data_offset));
		self::$nbt_data_offset++;
		echo '[' . $tag_id . ']';
		if ($tag_id == 0x00) /* TAG_End */
		{
			echo "\n";
			self::$json .= '}'; /* add json end_object tag */
			return;
		}
		$tag_name_length = self::bin2dec(self::read16(self::$nbt_data_offset));
		echo '[' . $tag_name_length . ']';
		self::$nbt_data_offset += 2;
		$tag_name = '';
		for ($x = 0; $x < $tag_name_length; $x++)
		{
			$tag_name .= self::read8(self::$nbt_data_offset + $x);
		}
		echo '[' . $tag_name . ']' . "\n";
		self::$nbt_data_offset += $tag_name_length;
		if ($tag_id == 0x01) /* TAG_Byte */
		{
			$val = 0;
			$val = self::bin2dec(self::read8(self::$nbt_data_offset));
			if ($val > (pow(2, 7) - 1))
			{
				$val -= pow(2, 8); /* convert to unsigned */
			}
			self::$json .= "\"$tag_name\": $val,";
			self::$nbt_data_offset += 1;
		}
		if ($tag_id == 0x02) /* TAG_Short */
		{
			$val = 0;
			$val = self::bin2dec(self::read16(self::$nbt_data_offset));
			if ($val > (pow(2, 15) - 1))
			{
				$val -= pow(2, 16); /* convert to unsigned */
			}
			self::$json .= "\"$tag_name\": $val,";
			self::$nbt_data_offset += 2;
		}
		if ($tag_id == 0x03) /* TAG_Int */
		{
			$val = 0;
			$val = intval(self::bin2dec(self::read32(self::$nbt_data_offset)));
			if ($val > (pow(2, 31) - 1))
			{
				$val = $val - pow(2, 32);
			}
			self::$json .= "\"$tag_name\": $val,";
			self::$nbt_data_offset += 4;
		}
		if ($tag_id == 0x04) /* TAG_Long */
		{
			/* TODO: In 64-bit systems this should automatically wrap. If not, we need to make it happen before bin2dec */
			$val = self::bin2dec(self::read64(self::$nbt_data_offset));
			self::$json .= "\"$tag_name\": $val,";
			self::$nbt_data_offset += 8;
		}
		if ($tag_id == 0x05) /* TAG_Float */
		{
			self::$json .= "\"$tag_name\": \"0x05,TODO\",";
			self::$nbt_data_offset += 4;
		}
		if ($tag_id == 0x06) /* TAG_Double */
		{
			self::$json .= "\"$tag_name\": \"0x06,TODO\",";
			self::$nbt_data_offset += 8;
		}
		if ($tag_id == 0x07) /* TAG_Byte_Array */
		{
			$payload_length = self::bin2dec(self::read32(self::$nbt_data_offset));
			self::$nbt_data_offset += 4;
			/* An array of bytes */
			$array_of_bytes = [];
			for ($payload_index = 0; $payload_index < $payload_length; $payload_index++)
			{
				$val = self::bin2dec(self::read8(self::$nbt_data_offset));
				if ($val > (pow(2, 7) - 1))
				{
					$val -= pow(2, 8); /* convert to unsigned */
				}
				$array_of_bytes[] = $val;
				self::$nbt_data_offset++;
			}
			$payload_bytes = implode(',', $array_of_bytes);
			self::$json .= "\"$tag_name\": [$payload_bytes],";
		}
		if ($tag_id == 0x08) /* TAG_String */
		{
			$payload_length = self::bin2dec(self::read16(self::$nbt_data_offset));
			self::$nbt_data_offset += 2;
			/* UTF-8 String */
			$utf8_string = '';
			for ($payload_index = 0; $payload_index < $payload_length; $payload_index++)
			{
				$utf8_string .= self::read8(self::$nbt_data_offset);
				self::$nbt_data_offset++;
			}
			self::$json .= "\"$tag_name\": \"$utf8_string\",";
		}
		if ($tag_id == 0x09) /* TAG_List */
		{
			self::$json .= "\"$tag_name\": \"0x09,TODO\",";
			/* TAG_Byte payload */
			$payload
		}
		if ($tag_id == 0x0a) /* TAG_Compound */
		{
			self::$json .= "\"$tag_name\": {";
			self::NBT2JSON();
			self::$json .= "},";
		}
		if ($tag_id == 0x0b) /* TAG_Int_Array */
		{
			$payload_length = self::bin2dec(self::read32(self::$nbt_data_offset));
			self::$nbt_data_offset += 4;
			/* An array of bytes */
			$array_of_ints = [];
			for ($payload_index = 0; $payload_index < $payload_length; $payload_index++)
			{
				$val = self::bin2dec(self::read32(self::$nbt_data_offset));
				if ($val > (pow(2, 31) - 1))
				{
					$val -= pow(2, 32); /* convert to unsigned */
				}
				$array_of_ints[] = $val;
				self::$nbt_data_offset += 4;
			}
			$payload_bytes = implode(',', $array_of_ints);
			self::$json .= "\"$tag_name\": [$payload_bytes],";
		}
		if ($tag_id == 0x0c) /* TAG_Long_Array */
		{
			$payload_length = self::bin2dec(self::read32(self::$nbt_data_offset));
			self::$nbt_data_offset += 4;
			/* An array of bytes */
			$array_of_longs = [];
			for ($payload_index = 0; $payload_index < $payload_length; $payload_index++)
			{
				$val = self::bin2dec(self::read64(self::$nbt_data_offset));
				$array_of_longs[] = $val;
				self::$nbt_data_offset += 8;
			}
			$payload_bytes = implode(',', $array_of_longs);
			self::$json .= "\"$tag_name\": [$payload_bytes],";
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
}