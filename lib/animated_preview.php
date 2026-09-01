<?php

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

function animated_preview_inspect_raster($body){

	$limits = [
		Imagick::RESOURCETYPE_MEMORY => 67108864,
		Imagick::RESOURCETYPE_MAP => 67108864,
		Imagick::RESOURCETYPE_DISK => 0,
		Imagick::RESOURCETYPE_FILE => 8,
		Imagick::RESOURCETYPE_THREAD => 1,
		Imagick::RESOURCETYPE_TIME => 10,
		Imagick::RESOURCETYPE_WIDTH => 16384,
		Imagick::RESOURCETYPE_HEIGHT => 16384,
		Imagick::RESOURCETYPE_LISTLENGTH => 1000
	];
	$previous = [];
	$probe = null;

	try{

		$probe = new Imagick();
		foreach($limits as $resource => $limit){

			$current = $probe->getResourceLimit($resource);
			$previous[$resource] = $current >= PHP_INT_MAX ? PHP_INT_MAX : (int)$current;
			$probe->setResourceLimit($resource, $limit);
		}

		$probe->pingImageBlob($body);
		$width = 0;
		$height = 0;
		foreach($probe as $frame){

			$page = $frame->getImagePage();
			$width = max($width, $frame->getImageWidth(), (int)($page["width"] ?? 0));
			$height = max($height, $frame->getImageHeight(), (int)($page["height"] ?? 0));
		}
		return [
			"frames" => $probe->getNumberImages(),
			"width" => $width,
			"height" => $height
		];
	}finally{

		if($probe instanceof Imagick){

			$probe->clear();
			$probe->destroy();
			foreach($previous as $resource => $limit){

				$probe->setResourceLimit($resource, $limit);
			}
		}
	}
}

function admit_animated_preview(){

	if(!function_exists("apcu_enabled") || !apcu_enabled()){

		return true;
	}

	$client = isset($_SERVER["REMOTE_ADDR"]) ? (string)$_SERVER["REMOTE_ADDR"] : "unknown";
	$window_key = "securitysearch.motion.ip." . hash("sha256", $client) . "." . intdiv(time(), 60);
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

	// A normal client loads at most three at once. This wider fixed window still
	// permits scrolling while bounding repeated 20 MB validation probes.
	if(is_int($requests) && $requests > 30){

		return false;
	}

	$generation_key = "securitysearch.motion.generation";
	$generation = apcu_inc($generation_key, 1, $generated);
	if(!$generated){

		if(apcu_add($generation_key, 1)){

			$generation = 1;
		}else{

			$generation = apcu_inc($generation_key, 1, $generated);
			if(!$generated){

				return false;
			}
		}
	}

	// Each request owns a generation-tagged semaphore slot. A stale shutdown
	// callback cannot decrement or delete a slot that expired and was reacquired.
	$slot_key = null;
	$slot_count = 3;
	$start = $generation % $slot_count;
	for($offset = 0; $offset < $slot_count; $offset++){

		$candidate = "securitysearch.motion.slot." . (($start + $offset) % $slot_count);
		if(apcu_add($candidate, $generation, 120)){

			$slot_key = $candidate;
			break;
		}
	}

	if($slot_key === null){

		return false;
	}

	register_shutdown_function(function() use ($slot_key, $generation){

		// CAS changes only the generation owned by this request. Keeping a zero
		// sentinel until delete also prevents another request entering mid-release.
		if(apcu_cas($slot_key, $generation, 0)){

			apcu_delete($slot_key);
		}
	});

	return true;
}
