<?php
/*
 * Freedom Index Form Field Helpers
 *
 * Straight function version of the former namespaced FICore form helper file.
 * Similar to CMB2's approach: use HTML for layout, functions for fields.
 * Key adjustments:

Removed the FICore namespace wrapper.
Removed the global proxy wrapper for fi_form_field() because the main function is now global.
Added fi_form_option_label() to avoid repeating option-label normalization.
Replaced old class references with function-based equivalents where available:
\FICore\Governments::get_state_options() → fi_state_options()
\FICore\Governments::get_options() → fi_gov_options()
\FICore\Parties::get_all() → fi_parties_get_all()
\FICore\Votes::get_status_options() → fi_votes_status_options()
Added safe fallbacks for dependent helper functions that may not be refactored yet.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('fi_form_field')) {
	/**
	 * Render a form field.
	 *
	 * @param string $id Field ID.
	 * @param array $args Field arguments.
	 * @return void
	 */
	function fi_form_field(string $id, array $args = []): void {
		$type = $args['type'] ?? 'text';
		$value = $args['value'] ?? '';

		if (is_string($value)) {
			$value = wp_unslash($value);
		} elseif (is_array($value)) {
			$value = array_map(static function($item) {
				return is_string($item) ? wp_unslash($item) : $item;
			}, $value);
		}

		$label_html = $args['label_html'] ?? null;
		$label = $label_html !== null ? '' : ($args['label'] ?? '');
		$placeholder = $args['placeholder'] ?? '';
		$required = !empty($args['required']);
		$help = $args['help'] ?? '';
		$options = $args['options'] ?? [];
		$multiple = !empty($args['multiple']);
		$attributes = $args['attributes'] ?? [];
		$wrapper_class = $args['wrapper_class'] ?? '';
		$readonly = !empty($args['readonly']);
		$disabled = !empty($args['disabled']);
		$html = $args['html'] ?? '';
		$no_wrapper = !empty($args['no_wrapper']);
		$label_class = $args['label_class'] ?? 'form-label';
		$input_size = $args['input_size'] ?? '';
		$select_size = $args['size'] ?? null;

		$id = esc_attr((string) $id);
		$name_attr = esc_attr((string) ($args['name'] ?? $id));

		$checked_value = $args['checked_value'] ?? '1';
		$unchecked_value = $args['unchecked_value'] ?? '0';

		$base_class = '';
		if ($type === 'select') {
			$base_class = 'form-select';
		} elseif ($type !== 'checkbox' && $type !== 'switch' && $type !== 'hidden') {
			$base_class = 'form-control';
		}

		if ($input_size && in_array($input_size, ['sm', 'lg'], true)) {
			$base_class .= ' form-' . ($type === 'select' ? 'select' : 'control') . '-' . $input_size;
		}

		$attr_string = '';
		if ($placeholder) {
			$attr_string .= ' placeholder="' . esc_attr((string) $placeholder) . '"';
		}
		if ($required) {
			$attr_string .= ' required';
		}
		if ($readonly) {
			$attr_string .= ' readonly';
		}
		if ($disabled) {
			$attr_string .= ' disabled';
		}
		if ($multiple && $type === 'select') {
			$attr_string .= ' multiple';
		}

		if ($base_class) {
			if (isset($attributes['class'])) {
				$attributes['class'] = $base_class . ' ' . $attributes['class'];
			} else {
				$attributes['class'] = $base_class;
			}
		}

		foreach ($attributes as $key => $val) {
			if ($val !== null && $val !== '') {
				$attr_string .= ' ' . esc_attr((string) $key) . '="' . esc_attr((string) $val) . '"';
			} else {
				$attr_string .= ' ' . esc_attr((string) $key);
			}
		}

		switch ($type) {
			case 'hidden':
				echo '<input type="hidden" id="' . $id . '" name="' . $name_attr . '" value="' . esc_attr((string) $value) . '">';
				return;

			case 'wysiwyg':
				if (!$no_wrapper) {
					echo '<div class="mb-3' . ($wrapper_class ? ' ' . esc_attr((string) $wrapper_class) : '') . '">';
				}

				$label_text = $label_html !== null ? $label_html : $label;
				if ($label_text) {
					echo '<label for="' . $id . '" class="fw-bold text-muted ' . esc_attr((string) $label_class) . '">';
					if ($label_html !== null) {
						echo $label_html;
					} else {
						echo esc_html((string) ($label ?? ''));
					}
					if ($required) {
						echo ' <span class="text-danger">*</span>';
					}
					echo '</label>';
				}

				$editor_settings = $args['editor_settings'] ?? [];
				$editor_args = array_replace_recursive([
					'textarea_name' => $name_attr,
					'editor_class'  => $base_class,
					'textarea_rows' => 4,
					'media_buttons' => false,
					'teeny'         => false,
					'tinymce'       => [
						'toolbar1'          => 'formatselect,bold,italic,underline,|,bullist,numlist,|,blockquote,|,undo,redo,|,code',
						'toolbar2'          => '',
						'plugins'           => 'lists,paste,wordpress,wpeditimage,tabfocus',
						'wpautop'           => true,
						'indent'            => true,
						'elementpath'       => false,
						'resize'            => true,
						'height'            => 220,
						'forced_root_block' => 'p',
						'force_p_newlines'  => true,
						'force_br_newlines' => false,
					],
					'quicktags'     => [
						'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li',
					],
				], $editor_settings);

				wp_editor($value, $id, $editor_args);

				if ($help) {
					echo '<div class="form-text">' . wp_kses_post($help) . '</div>';
				}
				if (!$no_wrapper) {
					echo '</div>';
				}
				return;

			case 'html':
				$label_text = $label_html !== null ? $label_html : $label;
				if (!$no_wrapper && ($label_text || $help)) {
					echo '<div class="mb-3' . ($wrapper_class ? ' ' . esc_attr((string) $wrapper_class) : '') . '">';
					if ($label_text) {
						echo '<label class="form-label">';
						if ($label_html !== null) {
							echo $label_html;
						} else {
							echo esc_html((string) ($label ?? ''));
						}
						if ($required) {
							echo ' <span class="text-danger">*</span>';
						}
						echo '</label>';
					}
				}

				echo $html;

				if ($help) {
					echo '<div class="form-text">' . wp_kses_post($help) . '</div>';
				}
				if (!$no_wrapper && ($label_text || $help)) {
					echo '</div>';
				}
				return;
		}

		if (!$no_wrapper) {
			echo '<div class="mb-3' . ($wrapper_class ? ' ' . esc_attr((string) $wrapper_class) : '') . '">';
		}

		$label_text = $label_html !== null ? $label_html : $label;
		if ($label_text && $type !== 'checkbox' && $type !== 'switch') {
			echo '<label for="' . $id . '" class="fw-bold text-muted ' . esc_attr((string) $label_class) . '">';
			if ($label_html !== null) {
				echo $label_html;
			} else {
				echo esc_html((string) ($label ?? ''));
			}
			if ($required) {
				echo ' <span class="text-danger">*</span>';
			}
			echo '</label>';
		}

		switch ($type) {
			case 'radio-group':
				$button_classes = $args['button_classes'] ?? [];
				$button_size = $args['button_size'] ?? '';
				$size_class = $button_size ? ' btn-group-' . esc_attr($button_size) : '';

				echo '<div class="btn-group w-100' . $size_class . '" role="group" aria-label="' . esc_attr((string) $label) . '">';
				foreach ($options as $opt_value => $opt_label) {
					$opt_value_str = (string) $opt_value;
					$opt_label_str = is_array($opt_label) ? ($opt_label['name'] ?? $opt_value_str) : (string) $opt_label;
					$input_id = $id . '-' . sanitize_key($opt_value_str);
					$checked = checked($value, $opt_value_str, false);

					$btn_class = 'btn-outline-secondary';
					if (isset($button_classes[$opt_value_str])) {
						if (is_array($button_classes[$opt_value_str])) {
							$btn_class = $button_classes[$opt_value_str][$value] ?? $btn_class;
						} else {
							$btn_class = $button_classes[$opt_value_str];
						}
					}

					echo '<input type="radio" class="btn-check" name="' . $name_attr . '" id="' . esc_attr($input_id) . '" value="' . esc_attr($opt_value_str) . '"' . $checked . ($required ? ' required' : '') . '>';
					echo '<label class="btn ' . esc_attr($btn_class) . '" for="' . esc_attr($input_id) . '">' . esc_html($opt_label_str) . '</label>';
				}
				echo '</div>';
				break;

			case 'textarea':
				echo '<textarea id="' . $id . '" name="' . $name_attr . '"' . $attr_string . '>' . esc_textarea((string) $value) . '</textarea>';
				break;

			case 'select':
				$size_attr = '';
				if ($select_size !== null && is_numeric($select_size)) {
					$size_attr = ' size="' . esc_attr((int) $select_size) . '"';
				}

				echo '<select id="' . $id . '" name="' . $name_attr . '"' . $attr_string . $size_attr . '>';
				foreach ($options as $opt_value => $opt_label) {
					$opt_label_str = fi_form_option_label($opt_value, $opt_label);
					if ($multiple && is_array($value)) {
						$selected = in_array((string) $opt_value, array_map('strval', $value), true) ? ' selected' : '';
					} else {
						$selected = selected($value, $opt_value, false);
					}
					echo '<option value="' . esc_attr($opt_value) . '"' . $selected . '>' . esc_html($opt_label_str) . '</option>';
				}
				echo '</select>';
				break;

			case 'checkbox':
			case 'switch':
				$is_switch = $type === 'switch';
				$checked = ((string) $value === (string) $checked_value);
				echo '<div class="form-check' . ($is_switch ? ' form-switch' : '') . '">';
				echo '<input type="hidden" name="' . $name_attr . '" value="' . esc_attr($unchecked_value) . '">';
				echo '<input class="form-check-input" type="checkbox" id="' . $id . '" name="' . $name_attr . '" value="' . esc_attr($checked_value) . '"' . ($checked ? ' checked' : '') . $attr_string . '>';
				if ($label) {
					echo '<label class="form-check-label" for="' . $id . '">' . esc_html($label);
					if ($required) {
						echo ' <span class="text-danger">*</span>';
					}
					echo '</label>';
				}
				echo '</div>';
				break;

			case 'number':
			case 'email':
			case 'url':
			case 'date':
				$value_attr = esc_attr((string) $value);
				echo '<input type="' . esc_attr($type) . '" id="' . $id . '" name="' . $name_attr . '" value="' . $value_attr . '"' . $attr_string . '>';
				break;

			default:
				echo '<input type="text" id="' . $id . '" name="' . $name_attr . '" value="' . esc_attr((string) $value) . '"' . $attr_string . '>';
				break;
		}

		if ($help) {
			echo '<div class="form-text">' . wp_kses_post($help) . '</div>';
		}

		if (!$no_wrapper) {
			echo '</div>';
		}
	}
}

if (!function_exists('fi_form_option_label')) {
	/**
	 * Normalize a select/radio option label.
	 *
	 * @param string|int $opt_value Option value.
	 * @param mixed $opt_label Option label data.
	 * @return string
	 */
	function fi_form_option_label($opt_value, $opt_label): string {
		if (is_array($opt_label)) {
			return (string) ($opt_label['name'] ?? $opt_label['short'] ?? $opt_value);
		}

		if ($opt_label === null) {
			return (string) $opt_value;
		}

		return (string) $opt_label;
	}
}

if (!function_exists('fi_form_field_state')) {
	/**
	 * State selector field.
	 *
	 * @param string $name Field name.
	 * @param array $args Field args.
	 * @return void
	 */
	function fi_form_field_state(string $name, array $args = []): void {
		$state_options = function_exists('fi_state_options') ? fi_state_options() : [];

		$dc = ['DC' => 'District of Columbia'];
		if (isset($state_options['DC'])) {
			unset($state_options['DC']);
		}

		$args['type'] = 'select';
		$args['options'] = array_merge(['' => 'Select State'], $dc, $state_options);

		fi_form_field($name, $args);
	}
}

if (!function_exists('fi_form_field_government')) {
	/**
	 * Government selector field.
	 *
	 * @param string $name Field name.
	 * @param array $args Field args.
	 * @return void
	 */
	function fi_form_field_government(string $name, array $args = []): void {
		$gov_options = function_exists('fi_gov_options') ? fi_gov_options() : (defined('FI_GOVERNMENTS') ? FI_GOVERNMENTS : []);

		$args['type'] = 'select';
		$args['options'] = $gov_options;

		fi_form_field($name, $args);
	}
}

if (!function_exists('fi_form_field_party')) {
	/**
	 * Party selector field.
	 *
	 * @param string $name Field name.
	 * @param array $args Field args.
	 * @return void
	 */
	function fi_form_field_party(string $name, array $args = []): void {
		$parties = function_exists('fi_parties_get_all') ? fi_parties_get_all() : [];
		$party_options = [];

		foreach ($parties as $abbr => $data) {
			$party_options[strtoupper((string) $abbr)] = is_array($data) ? ($data['name'] ?? strtoupper((string) $abbr)) : (string) $data;
		}

		$args['type'] = 'select';
		$args['options'] = array_merge(['' => 'Select Party'], $party_options);

		fi_form_field($name, $args);
	}
}

if (!function_exists('fi_form_field_chamber')) {
	/**
	 * Chamber selector field.
	 *
	 * @param string $name Field name.
	 * @param string $gov Government code.
	 * @param array $args Field args.
	 * @return void
	 */
	function fi_form_field_chamber(string $name, string $gov, array $args = []): void {
		$chamber_options_raw = function_exists('fi_chamber_options') ? fi_chamber_options($gov) : [];
		$chamber_options = [];

		foreach ($chamber_options_raw as $key => $value) {
			if (is_array($value)) {
				$chamber_options[$key] = $value['chamber'] ?? $value['name'] ?? $value['short'] ?? $key;
			} else {
				$chamber_options[$key] = $value;
			}
		}

		$args['type'] = 'select';
		$args['options'] = array_merge(['' => 'Select Chamber'], $chamber_options);

		fi_form_field($name, $args);
	}
}

if (!function_exists('fi_form_field_status')) {
	/**
	 * Vote status selector field.
	 *
	 * @param string $name Field name.
	 * @param array $args Field args.
	 * @return void
	 */
	function fi_form_field_status(string $name, array $args = []): void {
		$args['type']    = 'select';
		$args['options'] = FI_VOTE_STATUSES;

		fi_form_field($name, $args);
	}
}

if (!function_exists('fi_form_field_address_type')) {
	/**
	 * Address type selector field.
	 *
	 * @param string $name Field name.
	 * @param array $args Field args.
	 * @return void
	 */
	function fi_form_field_address_type(string $name, array $args = []): void {
		$args['type'] = 'select';
		$args['options'] = [
			'capitol'  => 'Capitol',
			'district' => 'District',
			'local'    => 'Local',
			'other'    => 'Other',
		];

		fi_form_field($name, $args);
	}
}

if (!function_exists('fi_form_field_constitutional')) {
	/**
	 * Constitutional position selector field.
	 *
	 * @param string $name Field name.
	 * @param array $args Field args.
	 * @return void
	 */
	function fi_form_field_constitutional(string $name, array $args = []): void {
		$args['type'] = 'select';
		$args['options'] = [
			'U' => 'Unknown (U)',
			'Y' => 'Constitutional (Y)',
			'N' => 'Unconstitutional (N)',
		];

		fi_form_field($name, $args);
	}
}
