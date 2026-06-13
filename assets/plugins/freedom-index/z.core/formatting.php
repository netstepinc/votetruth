<?php if(!defined('ABSPATH')) exit;


/*
Unslash POST data: string (editor content) gets unslash + wp_kses_post; array is recursed and each string unslashed (like vote meta).
*/
function fi_prepare_richedit_save($raw): string|array {
	if (is_string($raw)) {
		return wp_kses_post(wp_unslash($raw));
	}
	if (is_array($raw)) {
		return array_map(static function ($item) {
			return is_string($item) ? wp_unslash($item) : (is_array($item) ? fi_prepare_richedit_save($item) : $item);
		}, $raw);
	}
	return '';
}



/**
* Clean vote description text
*/
function fi_clean_content(string $content,$args = []): string {
	$allowed_html = array(
		'p'          => array('class' => true, 'id' => true), // No attributes allowed (removes style, class, etc.)
		'br'         => array(),
		'b'          => array(),
		'i'          => array(),
		'strong'     => array(),
		'em'         => array(),
		'u'          => array(),
		'a'          => array( 'href' => true, 'title' => true, 'class' => true ,'target' => true, 'id' => true), // Allow only links
		'ul'         => array('class' => true, 'id' => true),
		'ol'         => array('class' => true, 'id' => true),
		'li'         => array('class' => true, 'id' => true),
		'h2'         => array('class' => true, 'id' => true),
		'h3'         => array('class' => true, 'id' => true),
		'h4'         => array('class' => true, 'id' => true),
		'blockquote' => array('class' => true, 'id' => true),
		'hr'         => array('class' => true, 'id' => true),
		//'span'       => array('class' => true, 'id' => true),
	);

	$allowed_tags = array(
		'p'          => '<p>',
		'br'         => '<br>',
		'b'          => '<b>',
		'i'          => '<i>',
		'strong'     => '<strong>',
		'em'         => '<em>',
		'u'          => '<u>',
		'a'          => '<a>',
		'ul'         => '<ul>',
		'ol'         => '<ol>',
		'li'         => '<li>',
		'h2'         => '<h2>',
		'h3'         => '<h3>',
		'h4'         => '<h4>',
		'blockquote' => '<blockquote>',
		'hr'         => '<hr>',
		//'span'       => '<span>',
	);

	if(isset($args['exclude'])){
		foreach($args['exclude'] as $exclude){
			unset($allowed_tags[$exclude]);
		}
	}

	$clean_content = strip_tags($content, implode('', $allowed_tags)); //<span>

	$clean_content = wp_kses( $clean_content, $allowed_html );
	if(!isset($args['autop']) || (isset($args['autop']) && $args['autop'] == true) ){
		$clean_content = wpautop($clean_content);
	}
	if (strpos($clean_content, '<p>') === false) {
		//Remove double line breaks then NL2BR
		$clean_content = str_replace("\n\n", "\n", $clean_content);
		$clean_content = nl2br($clean_content, false);
	}
	return $clean_content;
}


/*
There's some janky tags like <p style="--tw-scale-x: 1;--tw-scale-y: 1;--tw-scroll-snap-strictness: proximity;--tw-ring-offset-width: 0px;--tw-ring-offset-color: #fff;--tw-ring-color: #3b82f680;--tw-ring-offset-shadow: 0 0 #0000;--tw-ring-shadow: 0 0 #0000;--tw-shadow: 0 0 #0000;--tw-shadow-colored: 0 0 #0000">
Remove all style attributes from <p> tags, preserving the <p> tags and their contents.
*/
function fi_trim_text_chars($max_length = 624, $text = '') {
	// Remove style from <p> tags but preserve <p>
	$text = preg_replace('/<p\s+[^>]*style="[^"]*"([^>]*)>/i', '<p$1>', $text);

	// Only allow <p>, <b>, <strong> tags
	$text = strip_tags($text, '<p><b><strong>');

	// Efficient trim to character limit, preserving <p> blocks and closing integrity
	$curr_len = 0;
	$output   = '';
	$truncated = false;

	// Match all <p> blocks
	if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $text, $matches)) {
		foreach ($matches[1] as $body) {
			// Allow <b>, <strong> only inside <p>
			$body_clean   = strip_tags($body, '<b><strong>');
			$body_text    = trim(strip_tags($body_clean));
			$body_length  = mb_strlen($body_text);

			// Already at/over limit: break out
			if ($curr_len >= $max_length) break;

			// If fits entirely, include as-is
			if (($curr_len + $body_length) <= $max_length) {
				$output .= "<p>{$body_clean}</p>";
				$curr_len += $body_length;
			} else {
				// Need to truncate inside this <p>; preserve partial words
				$remaining = $max_length - $curr_len;
				$body_stripped = strip_tags($body_clean);
				$trimmed = '';
				$added = 0;

				// Efficiently trim by chars, but don't break HTML entities mid-way
				$offset = 0;
				$inside_tag = false;
				$body_clean_len = mb_strlen($body_clean);
				while ($added < $remaining && $offset < $body_clean_len) {
					$char = mb_substr($body_clean, $offset, 1);
					if ($char === '<') $inside_tag = true;
					if (!$inside_tag) $added++;
					$trimmed .= $char;
					if ($char === '>') $inside_tag = false;
					$offset++;
				}
				// If inside a tag, close it (never break HTML)
				if($inside_tag) {
					$trimmed .= '>';
				}
				// Remove any open tag, sanitize
				$trimmed = preg_replace('/(<[a-z][^>]*>)?$/i', '', $trimmed);

				// Close tags (ensure no unclosed <b>/<strong>)
				$trimmed .= '...';
				$output  .= "<p>{$trimmed}</p>";
				$truncated = true;
				break;
			}
		}
		// If nothing got in (possibly because the text has no <p> or is empty), fallback
		if (trim($output) === '') {
			$stripped = mb_substr(trim(strip_tags($text)), 0, $max_length) . '...';
			$output = "<p>{$stripped}</p>";
		}
	} else {
		// No <p> tags, fallback to simple trimming and wrap with <p>
		$stripped = trim(strip_tags($text));
		if (mb_strlen($stripped) > $max_length) {
			$stripped = mb_substr($stripped, 0, $max_length) . '...';
		}
		$output = "<p>{$stripped}</p>";
	}

	return $output;
}

// Trim to X words but preserve <p> tags and close with </p> if needed.
function fi_trim_text_words($max_words = 94,$text = ''){
	$text = preg_replace('/<p\s+[^>]*style="[^"]*"([^>]*)>/i', '<p$1>', $text);
	$word_count = 0;
	$result = '';
	if (stripos($text, '<p') !== false) {
		// Split into paragraphs
		preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $text, $matches);
		foreach ($matches[1] as $idx => $paragraph_body) {
			// Count words in this paragraph
			$words = preg_split('/\s+/', strip_tags($paragraph_body), -1, PREG_SPLIT_NO_EMPTY);
			$p_word_count = count($words);
			if ($word_count + $p_word_count > $max_words) {
				$remaining = $max_words - $word_count;
				if ($remaining > 0) {
					$trimmed_paragraph_text = implode(' ', array_slice($words, 0, $remaining)) . '...';
					$result .= '<p>' . $trimmed_paragraph_text . '</p>';
				}
				$word_count = $max_words;
				break;
			} else {
				// Add entire paragraph
				$result .= '<p>' . $paragraph_body . '</p>';
				$word_count += $p_word_count;
			}
		}
		// If there were no matches (possibly stray <p>), fallback to text
		if (trim($result) === '') {
			// Remove all tags to be safe if input was broken
			$words = preg_split('/\s+/', strip_tags($text), -1, PREG_SPLIT_NO_EMPTY);
			$result = implode(' ', array_slice($words, 0, $max_words));
			if (count($words) > $max_words) $result .= '...';
		}
		// Ensure ends with closing </p>
		if (substr(trim($result), -4) !== '</p>') {
			$result .= '</p>';
		}
		$content = $result;
	} else {
		// No paragraph tags, fallback to straight trim, add <p> wrapper
		$words = preg_split('/\s+/', strip_tags($text), -1, PREG_SPLIT_NO_EMPTY);
		$result = implode(' ', array_slice($words, 0, $max_words));
		if(count($words) > $max_words) $result .= '...';
		$content = '<p>' . $result . '</p>';
	}

	return $content;
}