<?php

// Match the animated endpoint's 32 MiB download ceiling and keep the bound
// local so these helpers remain safe if they are reused elsewhere.
const ANIMATED_PREVIEW_MAX_RASTER_BYTES = 33554432;
const ANIMATED_PREVIEW_MAX_FRAMES = 1000;
const ANIMATED_PREVIEW_MAX_CONTAINER_CHUNKS = 8192;
const ANIMATED_PREVIEW_MAX_GIF_SUB_BLOCKS = 131072;

// At most three requests may perform the upstream fetch/validation work. A
// bounded queue absorbs short bursts without allowing unbounded PHP workers.
const ANIMATED_PREVIEW_ACTIVE_SLOTS = 3;
const ANIMATED_PREVIEW_QUEUE_SLOTS = 9;
const ANIMATED_PREVIEW_SLOT_TTL = 120;
const ANIMATED_PREVIEW_QUEUE_TTL = 10;
const ANIMATED_PREVIEW_QUEUE_WAIT_MICROSECONDS = 3000000;
const ANIMATED_PREVIEW_CLIENT_REQUESTS_PER_MINUTE = 900;

function animated_preview_apng_frame_count($body){

	if(!is_string($body) || substr($body, 0, 8) !== "\x89PNG\r\n\x1a\n"){

		return 0;
	}

	$offset = 8;
	$total = strlen($body);
	$canvas_width = 0;
	$canvas_height = 0;
	$seen_ihdr = false;
	$seen_plte = false;
	$seen_actl = false;
	$seen_idat = false;
	$idat_finished = false;
	$declared_frames = 0;
	$frame_controls = 0;
	$next_sequence = 0;
	$current_frame_has_data = false;
	$default_frame_animated = false;
	$chunk_count = 0;

	while($offset + 12 <= $total){

		$chunk_count++;
		if($chunk_count > 8192){

			return 0;
		}

		$length_data = unpack("Nlength", substr($body, $offset, 4));
		if(!is_array($length_data) || !isset($length_data["length"])){

			return 0;
		}

		$chunk_length = (int)$length_data["length"];
		if($chunk_length < 0 || $chunk_length > $total - $offset - 12){

			return 0;
		}

		$chunk_type = substr($body, $offset + 4, 4);
		if(
			preg_match('/\A[A-Za-z]{4}\z/D', $chunk_type) !== 1 ||
			ord($chunk_type[2]) < 65 ||
			ord($chunk_type[2]) > 90
		){

			return 0;
		}

		$stored_crc = substr($body, $offset + 8 + $chunk_length, 4);
		$crc = hash_init("crc32b");
		hash_update($crc, $chunk_type);
		$data_offset = $offset + 8;
		$data_remaining = $chunk_length;
		while($data_remaining > 0){

			$slice_length = min(65536, $data_remaining);
			hash_update($crc, substr($body, $data_offset, $slice_length));
			$data_offset += $slice_length;
			$data_remaining -= $slice_length;
		}
		if(!hash_equals(hash_final($crc, true), $stored_crc)){

			return 0;
		}

		$next_offset = $offset + 12 + $chunk_length;
		if(!$seen_ihdr){

			if($chunk_type !== "IHDR" || $chunk_length !== 13 || $offset !== 8){

				return 0;
			}

			$header = unpack(
				"Nwidth/Nheight/Cbit_depth/Ccolor_type/Ccompression/Cfilter/Cinterlace",
				substr($body, $offset + 8, 13)
			);
			if(
				!is_array($header) ||
				!isset($header["width"], $header["height"]) ||
				$header["width"] < 1 ||
				$header["height"] < 1
			){

				return 0;
			}

			$canvas_width = (int)$header["width"];
			$canvas_height = (int)$header["height"];
			$seen_ihdr = true;
			$offset = $next_offset;
			continue;
		}

		if($seen_idat && $chunk_type !== "IDAT"){

			$idat_finished = true;
		}

		switch($chunk_type){

			case "IHDR":
				return 0;

			case "PLTE":
				if($seen_plte || $seen_idat || $chunk_length === 0 || $chunk_length % 3 !== 0){

					return 0;
				}
				$seen_plte = true;
				break;

			case "acTL":
				if($seen_actl || $seen_idat || $frame_controls !== 0 || $chunk_length !== 8){

					return 0;
				}

				$animation = unpack("Nframes/Nplays", substr($body, $offset + 8, 8));
				if(
					!is_array($animation) ||
					!isset($animation["frames"]) ||
					$animation["frames"] < 2 ||
					$animation["frames"] > 1000
				){

					return 0;
				}

				$declared_frames = (int)$animation["frames"];
				$seen_actl = true;
				break;

			case "fcTL":
				if(
					!$seen_actl ||
					$chunk_length !== 26 ||
					($frame_controls > 0 && !$current_frame_has_data)
				){

					return 0;
				}

				$control = unpack(
					"Nsequence/Nwidth/Nheight/Nx_offset/Ny_offset/ndelay_num/ndelay_den/Cdispose/Cblend",
					substr($body, $offset + 8, 26)
				);
				if(
					!is_array($control) ||
					!isset(
						$control["sequence"],
						$control["width"],
						$control["height"],
						$control["x_offset"],
						$control["y_offset"],
						$control["dispose"],
						$control["blend"]
					) ||
					$control["sequence"] !== $next_sequence ||
					$control["width"] < 1 ||
					$control["height"] < 1 ||
					$control["width"] > $canvas_width ||
					$control["height"] > $canvas_height ||
					$control["x_offset"] > $canvas_width - $control["width"] ||
					$control["y_offset"] > $canvas_height - $control["height"] ||
					$control["dispose"] > 2 ||
					$control["blend"] > 1
				){

					return 0;
				}

				$next_sequence++;
				$frame_controls++;
				if($frame_controls > $declared_frames){

					return 0;
				}

				$current_frame_has_data = false;
				if($frame_controls === 1){

					$default_frame_animated = !$seen_idat;
					if(
						$control["dispose"] === 2 ||
						(
							$default_frame_animated &&
							(
								$control["width"] !== $canvas_width ||
								$control["height"] !== $canvas_height ||
								$control["x_offset"] !== 0 ||
								$control["y_offset"] !== 0
							)
						)
					){

						return 0;
					}
				}
				break;

			case "IDAT":
				if($idat_finished){

					return 0;
				}
				$seen_idat = true;
				if($default_frame_animated && $frame_controls === 1 && $chunk_length > 0){

					$current_frame_has_data = true;
				}
				break;

			case "fdAT":
				if(
					!$seen_actl ||
					!$seen_idat ||
					$frame_controls === 0 ||
					($default_frame_animated && $frame_controls === 1) ||
					$chunk_length <= 4
				){

					return 0;
				}

				$sequence = unpack("Nsequence", substr($body, $offset + 8, 4));
				if(
					!is_array($sequence) ||
					!isset($sequence["sequence"]) ||
					$sequence["sequence"] !== $next_sequence
				){

					return 0;
				}

				$next_sequence++;
				$current_frame_has_data = true;
				break;

			case "IEND":
				if(
					$chunk_length !== 0 ||
					!$seen_actl ||
					!$seen_idat ||
					!$current_frame_has_data ||
					$frame_controls !== $declared_frames ||
					$next_offset !== $total
				){

					return 0;
				}

				return $declared_frames;

			default:
				// Unknown critical PNG chunks are invalid. Ancillary metadata is
				// still accepted after its CRC and structural bounds are checked.
				if((ord($chunk_type[0]) & 32) === 0){

					return 0;
				}
		}

		$offset = $next_offset;
	}

	return 0;
}

function animated_preview_read_le16($body, $offset){

	if($offset < 0 || $offset + 2 > strlen($body)){

		return null;
	}

	return ord($body[$offset]) | (ord($body[$offset + 1]) << 8);
}

function animated_preview_read_le24($body, $offset){

	if($offset < 0 || $offset + 3 > strlen($body)){

		return null;
	}

	return ord($body[$offset]) |
		(ord($body[$offset + 1]) << 8) |
		(ord($body[$offset + 2]) << 16);
}

function animated_preview_read_le32($body, $offset){

	if($offset < 0 || $offset + 4 > strlen($body)){

		return null;
	}

	$value = unpack("Vvalue", substr($body, $offset, 4));
	return is_array($value) && isset($value["value"]) ? (int)$value["value"] : null;
}

function animated_preview_skip_gif_sub_blocks($body, $offset, &$payload_bytes){

	$total = strlen($body);
	$blocks = 0;
	$payload_bytes = 0;

	while($offset < $total){

		$blocks++;
		if($blocks > ANIMATED_PREVIEW_MAX_GIF_SUB_BLOCKS){

			return null;
		}

		$length = ord($body[$offset]);
		$offset++;
		if($length === 0){

			return $offset;
		}

		if($length > $total - $offset){

			return null;
		}

		$payload_bytes += $length;
		$offset += $length;
	}

	return null;
}

function animated_preview_inspect_gif($body){

	if(
		!is_string($body) ||
		strlen($body) < 14 ||
		strlen($body) > ANIMATED_PREVIEW_MAX_RASTER_BYTES ||
		(substr($body, 0, 6) !== "GIF87a" && substr($body, 0, 6) !== "GIF89a")
	){

		return null;
	}

	$total = strlen($body);
	$width = animated_preview_read_le16($body, 6);
	$height = animated_preview_read_le16($body, 8);
	if($width === null || $height === null || $width < 1 || $height < 1){

		return null;
	}

	$packed = ord($body[10]);
	$offset = 13;
	if(($packed & 0x80) !== 0){

		$color_table_bytes = 3 * (1 << (($packed & 0x07) + 1));
		if($color_table_bytes > $total - $offset){

			return null;
		}
		$offset += $color_table_bytes;
	}

	$frames = 0;
	$blocks = 0;
	while($offset < $total){

		$blocks++;
		if($blocks > ANIMATED_PREVIEW_MAX_CONTAINER_CHUNKS){

			return null;
		}

		$introducer = ord($body[$offset]);
		$offset++;

		if($introducer === 0x3b){

			if($offset !== $total || $frames < 2){

				return null;
			}

			return [
				"frames" => $frames,
				"width" => $width,
				"height" => $height
			];
		}

		if($introducer === 0x21){

			// Every extension has a label followed by a terminated sub-block chain.
			if($offset >= $total){

				return null;
			}
			$offset++;
			$offset = animated_preview_skip_gif_sub_blocks($body, $offset, $extension_bytes);
			if($offset === null){

				return null;
			}
			continue;
		}

		if($introducer !== 0x2c || $offset + 9 > $total){

			return null;
		}

		$left = animated_preview_read_le16($body, $offset);
		$top = animated_preview_read_le16($body, $offset + 2);
		$frame_width = animated_preview_read_le16($body, $offset + 4);
		$frame_height = animated_preview_read_le16($body, $offset + 6);
		$frame_packed = ord($body[$offset + 8]);
		if(
			$left === null ||
			$top === null ||
			$frame_width === null ||
			$frame_height === null ||
			$frame_width < 1 ||
			$frame_height < 1 ||
			$left > $width - $frame_width ||
			$top > $height - $frame_height
		){

			return null;
		}

		$offset += 9;
		if(($frame_packed & 0x80) !== 0){

			$color_table_bytes = 3 * (1 << (($frame_packed & 0x07) + 1));
			if($color_table_bytes > $total - $offset){

				return null;
			}
			$offset += $color_table_bytes;
		}

		if($offset >= $total){

			return null;
		}
		$lzw_code_size = ord($body[$offset]);
		$offset++;
		if($lzw_code_size < 1 || $lzw_code_size > 8){

			return null;
		}

		$offset = animated_preview_skip_gif_sub_blocks($body, $offset, $image_bytes);
		if($offset === null || $image_bytes === 0){

			return null;
		}

		$frames++;
		if($frames > ANIMATED_PREVIEW_MAX_FRAMES){

			return null;
		}
	}

	return null;
}

function animated_preview_inspect_webp_frame($body, $offset, $length, $frame_width, $frame_height){

	$end = $offset + $length;
	$seen_alpha = false;
	$seen_image = false;
	$image_type = null;
	$chunks = 0;

	while($offset + 8 <= $end){

		$chunks++;
		if($chunks > 64){

			return false;
		}

		$type = substr($body, $offset, 4);
		$chunk_length = animated_preview_read_le32($body, $offset + 4);
		if($chunk_length === null || $chunk_length > $end - $offset - 8){

			return false;
		}

		$data_offset = $offset + 8;
		$next_offset = $data_offset + $chunk_length + ($chunk_length & 1);
		if($next_offset > $end){

			return false;
		}
		if(($chunk_length & 1) !== 0 && ord($body[$data_offset + $chunk_length]) !== 0){

			return false;
		}

		if($type === "ALPH"){

			if($seen_alpha || $seen_image || $chunk_length < 1){

				return false;
			}

			$alpha_header = ord($body[$data_offset]);
			// The two high bits are reserved; only compression 0 and 1 exist.
			if(($alpha_header & 0xc0) !== 0 || ($alpha_header & 0x03) > 1){

				return false;
			}
			$seen_alpha = true;
		}elseif($type === "VP8 "){

			if($seen_image || $chunk_length < 10 || (ord($body[$data_offset]) & 1) !== 0){

				return false;
			}
			if(substr($body, $data_offset + 3, 3) !== "\x9d\x01\x2a"){

				return false;
			}

			$bitstream_width = animated_preview_read_le16($body, $data_offset + 6);
			$bitstream_height = animated_preview_read_le16($body, $data_offset + 8);
			if(
				$bitstream_width === null ||
				$bitstream_height === null ||
				($bitstream_width & 0x3fff) !== $frame_width ||
				($bitstream_height & 0x3fff) !== $frame_height
			){

				return false;
			}
			$seen_image = true;
			$image_type = $type;
		}elseif($type === "VP8L"){

			if($seen_image || $chunk_length < 5 || ord($body[$data_offset]) !== 0x2f){

				return false;
			}

			$bits = animated_preview_read_le32($body, $data_offset + 1);
			if(
				$bits === null ||
				(($bits & 0x3fff) + 1) !== $frame_width ||
				((($bits >> 14) & 0x3fff) + 1) !== $frame_height ||
				(($bits >> 29) & 0x07) !== 0
			){

				return false;
			}
			$seen_image = true;
			$image_type = $type;
		}

		$offset = $next_offset;
	}

	return $offset === $end && $seen_image && !($seen_alpha && $image_type === "VP8L");
}

function animated_preview_inspect_webp($body){

	if(
		!is_string($body) ||
		strlen($body) < 30 ||
		strlen($body) > ANIMATED_PREVIEW_MAX_RASTER_BYTES ||
		substr($body, 0, 4) !== "RIFF" ||
		substr($body, 8, 4) !== "WEBP"
	){

		return null;
	}

	$total = strlen($body);
	$riff_length = animated_preview_read_le32($body, 4);
	if($riff_length === null || $riff_length + 8 !== $total){

		return null;
	}

	$offset = 12;
	$chunks = 0;
	$width = 0;
	$height = 0;
	$frames = 0;
	$seen_vp8x = false;
	$seen_anim = false;

	while($offset + 8 <= $total){

		$chunks++;
		if($chunks > ANIMATED_PREVIEW_MAX_CONTAINER_CHUNKS){

			return null;
		}

		$type = substr($body, $offset, 4);
		if(preg_match('/\A[\x20-\x7e]{4}\z/D', $type) !== 1){

			return null;
		}

		$chunk_length = animated_preview_read_le32($body, $offset + 4);
		if($chunk_length === null || $chunk_length > $total - $offset - 8){

			return null;
		}

		$data_offset = $offset + 8;
		$next_offset = $data_offset + $chunk_length + ($chunk_length & 1);
		if($next_offset > $total){

			return null;
		}
		if(($chunk_length & 1) !== 0 && ord($body[$data_offset + $chunk_length]) !== 0){

			return null;
		}

		switch($type){

			case "VP8X":
				if($seen_vp8x || $offset !== 12 || $chunk_length !== 10){

					return null;
				}

				$flags = ord($body[$data_offset]);
				if(
					($flags & 0xc1) !== 0 ||
					($flags & 0x02) === 0 ||
					substr($body, $data_offset + 1, 3) !== "\x00\x00\x00"
				){

					return null;
				}

				$width_minus_one = animated_preview_read_le24($body, $data_offset + 4);
				$height_minus_one = animated_preview_read_le24($body, $data_offset + 7);
				if($width_minus_one === null || $height_minus_one === null){

					return null;
				}
				$width = $width_minus_one + 1;
				$height = $height_minus_one + 1;
				$seen_vp8x = true;
				break;

			case "ANIM":
				if(!$seen_vp8x || $seen_anim || $frames !== 0 || $chunk_length !== 6){

					return null;
				}
				$seen_anim = true;
				break;

			case "ANMF":
				if(!$seen_vp8x || !$seen_anim || $chunk_length < 24){

					return null;
				}

				$x_raw = animated_preview_read_le24($body, $data_offset);
				$y_raw = animated_preview_read_le24($body, $data_offset + 3);
				$frame_width_minus_one = animated_preview_read_le24($body, $data_offset + 6);
				$frame_height_minus_one = animated_preview_read_le24($body, $data_offset + 9);
				$frame_flags = ord($body[$data_offset + 15]);
				if(
					$x_raw === null ||
					$y_raw === null ||
					$frame_width_minus_one === null ||
					$frame_height_minus_one === null ||
					($frame_flags & 0xfc) !== 0
				){

					return null;
				}

				$x = $x_raw * 2;
				$y = $y_raw * 2;
				$frame_width = $frame_width_minus_one + 1;
				$frame_height = $frame_height_minus_one + 1;
				if(
					$x > $width - $frame_width ||
					$y > $height - $frame_height ||
					!animated_preview_inspect_webp_frame(
						$body,
						$data_offset + 16,
						$chunk_length - 16,
						$frame_width,
						$frame_height
					)
				){

					return null;
				}

				$frames++;
				if($frames > ANIMATED_PREVIEW_MAX_FRAMES){

					return null;
				}
				break;

			case "VP8 ":
			case "VP8L":
			case "ALPH":
				// Animated payloads carry each image bitstream inside an ANMF chunk.
				return null;
		}

		$offset = $next_offset;
	}

	if($offset !== $total || !$seen_vp8x || !$seen_anim || $frames < 2){

		return null;
	}

	return [
		"frames" => $frames,
		"width" => $width,
		"height" => $height
	];
}

function animated_preview_inspect_raster($body){

	if(!is_string($body) || strlen($body) > ANIMATED_PREVIEW_MAX_RASTER_BYTES){

		throw new UnexpectedValueException("Animated preview exceeds parser limits");
	}

	if(substr($body, 0, 6) === "GIF87a" || substr($body, 0, 6) === "GIF89a"){

		$inspection = animated_preview_inspect_gif($body);
	}elseif(substr($body, 0, 4) === "RIFF" && substr($body, 8, 4) === "WEBP"){

		$inspection = animated_preview_inspect_webp($body);
	}elseif(substr($body, 0, 8) === "\x89PNG\r\n\x1a\n"){

		$frames = animated_preview_apng_frame_count($body);
		$dimensions = strlen($body) >= 24 ? unpack("Nwidth/Nheight", substr($body, 16, 8)) : false;
		$inspection = $frames >= 2 && is_array($dimensions) ? [
			"frames" => $frames,
			"width" => (int)$dimensions["width"],
			"height" => (int)$dimensions["height"]
		] : null;
	}else{

		$inspection = null;
	}

	if(!is_array($inspection)){

		throw new UnexpectedValueException("Animated preview failed structural validation");
	}

	return $inspection;
}

function animated_preview_next_generation(){

	$generation_key = "securitysearch.motion.generation";
	$generation = apcu_inc($generation_key, 1, $generated);
	if($generated){

		return is_int($generation) ? $generation : null;
	}

	if(apcu_add($generation_key, 1)){

		return 1;
	}

	$generation = apcu_inc($generation_key, 1, $generated);
	return $generated && is_int($generation) ? $generation : null;
}

function animated_preview_acquire_slot($prefix, $slot_count, $generation, $ttl){

	$start = $generation % $slot_count;
	for($offset = 0; $offset < $slot_count; $offset++){

		$candidate = $prefix . (($start + $offset) % $slot_count);
		if(apcu_add($candidate, $generation, $ttl)){

			return $candidate;
		}
	}

	return null;
}

function animated_preview_release_slot($slot_key, $generation){

	// CAS changes only the generation owned by this request. Keeping a zero
	// sentinel until delete prevents another request entering mid-release.
	if(is_string($slot_key) && apcu_cas($slot_key, $generation, 0)){

		apcu_delete($slot_key);
	}
}

function animated_preview_charge_client_quota($window_key){

	$requests = apcu_inc($window_key, 1, $incremented);
	if(!$incremented){

		if(apcu_add($window_key, 1, 90)){

			$requests = 1;
		}else{

			$requests = apcu_inc($window_key, 1, $incremented);
			if(!$incremented){

				return false;
			}
		}
	}

	return is_int($requests) && $requests <= ANIMATED_PREVIEW_CLIENT_REQUESTS_PER_MINUTE;
}

function admit_animated_preview(){

	if(!function_exists("apcu_enabled") || !apcu_enabled()){

		return true;
	}

	$client = isset($_SERVER["REMOTE_ADDR"]) ? (string)$_SERVER["REMOTE_ADDR"] : "unknown";
	$window_key = "securitysearch.motion.ip." . hash("sha256", $client) . "." . intdiv(time(), 60);
	$current_requests = apcu_fetch($window_key, $quota_found);
	if(
		$quota_found &&
		is_int($current_requests) &&
		$current_requests >= ANIMATED_PREVIEW_CLIENT_REQUESTS_PER_MINUTE
	){

		return false;
	}

	$generation = animated_preview_next_generation();
	if($generation === null){

		return false;
	}

	$active_prefix = "securitysearch.motion.slot.";
	$slot_key = animated_preview_acquire_slot(
		$active_prefix,
		ANIMATED_PREVIEW_ACTIVE_SLOTS,
		$generation,
		ANIMATED_PREVIEW_SLOT_TTL
	);

	if($slot_key === null){

		$queue_key = animated_preview_acquire_slot(
			"securitysearch.motion.queue.",
			ANIMATED_PREVIEW_QUEUE_SLOTS,
			$generation,
			ANIMATED_PREVIEW_QUEUE_TTL
		);
		if($queue_key === null){

			// A request rejected only because all active and waiting slots are busy
			// has not consumed its per-client quota.
			return false;
		}

		try{

			$deadline = hrtime(true) + (ANIMATED_PREVIEW_QUEUE_WAIT_MICROSECONDS * 1000);
			do{

				$slot_key = animated_preview_acquire_slot(
					$active_prefix,
					ANIMATED_PREVIEW_ACTIVE_SLOTS,
					$generation,
					ANIMATED_PREVIEW_SLOT_TTL
				);
				if($slot_key !== null){

					break;
				}

				usleep(50000 + (($generation % 5) * 5000));
			}while(hrtime(true) < $deadline);
		}finally{

			animated_preview_release_slot($queue_key, $generation);
		}

		if($slot_key === null){

			// Timing out in the bounded queue is also a busy rejection and remains
			// uncharged, so a retry can use the same page allowance.
			return false;
		}
	}

	if(!animated_preview_charge_client_quota($window_key)){

		animated_preview_release_slot($slot_key, $generation);
		return false;
	}

	register_shutdown_function(function() use ($slot_key, $generation){

		animated_preview_release_slot($slot_key, $generation);
	});

	return true;
}
