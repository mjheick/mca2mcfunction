<?php

/**
 * An MCA file (Minecraft Anvil Format) contains all the world data in one file.
 * A chunk is a 16x16x16 cubic area of world. MCA file contains 32x32 chunks X-Z chunks
 */

class MCA {
	/**
	 * All the mca binary data
	 */
	private static $mca_data = null;
	
	/**
	 * Load a MCA file into the class
	 */
	public static function readMCA($filename)
	{
		$handle = fopen($filename, 'rb');
		if (!$handle)
		{
			throw new Exception('file ' . $filename . ' cannot be opened for read');
		}
		self::$mca_data = fread($handle, filesize($filename));
		fclose($handle);
	}

	/**
	 * Read 8 bytes at offset
	 */
	public static function read8($offset)
	{
		if (self::$mca_data == null)
		{
			throw new Exception('mca file not loaded');
		}
		return substr(self::$mca_data, $offset, 1);
	}

	/**
	 * Reads chunk data
	 *
	 * @see https://minecraft.gamepedia.com/Region_file_format
	 */
	public static function readChunk($x, $z)
	{
		/* calculate the offset (0 >= x|z >= 31) */
		if ((($x < 0) || ($x > 31)) || (($z < 0) || ($z > 31)))
		{
			throw new Exception('chunk parameter out of range 0-31, x=' . $x . ',z=' . $z);
		}
		$chunkOffset = self::chunkOffset($x, $z);

		/* First 3 bytes is offset of chunk data in 4k sectors from start of file */
		/* byte 4 is length of chunk in 4k sectors */
		$chunkDataStart = self::bin2dec(self::read8($chunkOffset) . self::read8($chunkOffset + 1) . self::read8($chunkOffset + 2)) * 4096;
		$chunkDataLength = self::bin2dec(self::read8($chunkOffset + 3)) * 4096;

		if ($chunkDataStart > strlen(self::$mca_data))
		{
			throw new Exception('chunk offset dataStart past end of chunk, x=' . $x . ',z=' . $z . ',chunkStart' . $chunkStart . ',mca_length' . strlen(self::$mca_data));
		}

		/* Chunk does not exist */
		if (($chunkDataStart == 0) && ($chunkDataLength == 0))
		{
			return null;
		}

		/* Working on the actual chunk now */
		$chunk_length = self::bin2dec(self::read32($chunkDataStart)); /* 4 bytes: length of chunk */
		$chunk_compression = self::bin2dec(self::read8($chunkDataStart + 4)); /* 1 byte: compression of data */
		$chunk_data = substr(self::$mca_data, $chunkDataStart + 5, $chunk_length);
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
	 * Convert a binary "string" to a usable number
	 * big-endien
	 */
	public static function bin2dec($data)
	{
		$decimal = 0;
		while (strlen($data) > 0)
		{
			$decimal *= 256; /* shift result left by 8 bytes */
			$byte = substr($data, 0, 1);
			$byte = hexdec('0x' . bin2hex($byte));
			if (strlen($data) > 1)
			{
				$data = substr($data, 1);
			}
			else
			{
				$data = "";
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
		return $decimal;
	}

	/**
	 * Location of chunk data in mca file
	 *
	 * @see https://minecraft.gamepedia.com/Region_file_format
	 */
	private static function chunkOffset($x, $z)
	{
		return (4 * (($x % 32) + ($z % 32) * 32));
	}

	/**
	 * Location of chunk timestamp in mca file
	 * Not really needed for project.
	 */
	private static function chunkTimestampOffset($x, $z)
	{
		return (self::chunkOffset($x, $z) + 0x1000);
	}

	/**
	 * Read 16 bytes at offset
	 */
	public static function read16($offset)
	{
		return self::read8($offset) . self::read8($offset + 1);
	}

	/**
	 * Read 32 bytes at offset
	 */
	public static function read32($offset)
	{
		return self::read8($offset) . self::read8($offset + 1) . self::read8($offset + 2) . self::read8($offset + 3);
	}
}