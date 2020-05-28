<?php
namespace AppZz\Http\Helpers;

/**
 * Get type & size from remote image
 * @package AppZz\Http\Helpers
 * @author CoolSwitcher
 */
class FastImage {

	private $_pos = 0;
	private $_response;
	private $_type;
	private $_handle;

	public function __construct ($url = null)
	{
		if ( ! empty ($url)) {
			$this->load($url);
		}
	}

	public function load ($url)
	{
		if ($this->_handle) {
			$this->close();
		}

		$this->_handle = fopen($url, 'r');
	}

	public function close()
	{
		if ($this->_handle)
		{
			fclose($this->_handle);
			$this->_handle   = null;
			$this->_type     = null;
			$this->_response = null;
			$this->_pos      = 0;
		}
	}

	public function __destruct()
	{
		$this->close();
	}

	public function get_size()
	{
		$this->_pos = 0;
		$this->get_type ();
		$size = false;

		if ($this->_type) {
			$parse_size_method = '_parse_size_for_'.$this->_type;
			if (method_exists($this, $parse_size_method)) {
				$size = $this->$parse_size_method();
				if (is_array($size)) {
					$size = array_values($size);
				}
			}
		}

		return $size;
	}


	public function get_type()
	{
		$this->_pos = 0;

		if (empty($this->_type))
		{
			switch ($this->_get_chars(2))
			{
				case "BM":
					$this->_type = 'bmp';
					break;
				case "GI":
					$this->_type = 'gif';
					break;
				case chr(0xFF).chr(0xd8):
					$this->_type = 'jpeg';
					break;
				case chr(0x89).'P':
					$this->_type = 'png';
					break;
				default:
					$this->_type = false;
			}
		}

		return $this->_type;
	}

	private function _parse_size_for_png ()
	{
		$chars = $this->_get_chars(25);
		return unpack("N*", substr($chars, 16, 8));
	}


	private function _parse_size_for_gif ()
	{
		$chars = $this->_get_chars(11);
		return unpack("S*", substr($chars, 6, 4));
	}


	private function _parse_size_for_bmp ()
	{
		$chars = $this->_get_chars(29);
	 	$chars = substr($chars, 14, 14);
		$type = unpack('C', $chars);
		return (reset($type) == 40) ? unpack('L*', substr($chars, 4)) : unpack('L*', substr($chars, 4, 8));
	}


	private function _parse_size_for_jpeg ()
	{
		$state = null;

		while (true)
		{
			switch ($state)
			{
				default:
					$this->_get_chars (2);
					$state = 'started';
					break;

				case 'started':
					$b = $this->_get_byte();

					if ($b === false) {
						return false;
					}

					$state = $b == 0xFF ? 'sof' : 'started';
					break;

				case 'sof':
					$b = $this->_get_byte();

					if (in_array($b, range(0xe0, 0xef))) {
						$state = 'skipframe';
					}
					elseif (in_array($b, array_merge(range(0xC0,0xC3), range(0xC5,0xC7), range(0xC9,0xCB), range(0xCD,0xCF)))) {
						$state = 'readsize';
					}
					elseif ($b == 0xFF) {
						$state = 'sof';
					} else {
						$state = 'skipframe';
					}
					break;

				case 'skipframe':
					$skip = $this->_read_int($this->_get_chars(2)) - 2;
					$state = 'doskip';
					break;

				case 'doskip':
					$this->_get_chars($skip);
					$state = 'started';
					break;

				case 'readsize':
					$c = $this->_get_chars(7);
					return array($this->_read_int(substr($c, 5, 2)), $this->_read_int(substr($c, 3, 2)));
			}
		}
	}


	private function _get_chars ($n)
	{
		$response = null;

		if ($this->_pos + $n -1 >= strlen($this->_response)) {
			$end = ($this->_pos + $n);

			while (strlen($this->_response) < $end && $response !== false) {
				$need = $end - ftell($this->_handle);

				if ($need > 0) {
					if ($response = fread($this->_handle, $need)) {
						$this->_response .= $response;
					} else {
						return false;
					}
				} else {
					return false;
				}
			}
		}

		$result = substr ($this->_response, $this->_pos, $n);
		$this->_pos += $n;

		return $result;
	}

	private function _get_byte()
	{
		$c = $this->_get_chars(1);

		if ($c) {
			$b = unpack("C", $c);

			if ($b) {
				return reset($b);
			}
		}

		return false;
	}


	private function _read_int ($str)
	{
		$size = unpack("C*", $str);
		return ($size[1] << 8) + $size[2];
	}
}
