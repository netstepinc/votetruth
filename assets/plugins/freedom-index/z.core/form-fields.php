<?php
namespace FI\Core {

	if (!defined('ABSPATH')) {
		exit;
	}

	/**
	* Simple form field helper functions
	* Similar to CMB2's approach - use HTML for layout, functions for fields
	* 
	* Usage:
	* <div class="row">
	*     <div class="col-md-6">
	*         <?php fi_form_field('display_name', ['label' => 'Display Name', 'value' => $legislator->display_name, 'required' => true]); ?>
	*     </div>
	* </div>
	*/

	/**
	* Render a form field
	* 
	* @param string $name Field name
	* @param array $args Field arguments:
	*   - label: Field label
	*   - type: Field type (text, email, textarea, select, checkbox, switch, html, etc.)
	*   - value: Current value (for select multiple, use array)
	*   - placeholder: Placeholder text
	*   - required: Whether field is required
	*   - help: Help text
	*   - options: For select fields, array of options
	*   - multiple: For select fields, enable multiple selection
	*   - checked_value: For checkbox/switch, value when checked
	*   - unchecked_value: For checkbox/switch, value when unchecked
	*   - html: For html type, custom HTML to render
	*   - attributes: Additional HTML attributes
	*   - wrapper_class: Additional wrapper classes
	*   - readonly: Make field readonly
	*   - disabled: Disable field
	*   - no_wrapper: Skip wrapper div (for custom layouts)
	*   - label_class: Custom label classes (default: 'form-label')
	*   - input_size: Input size variant ('sm', 'lg', or empty for default)
	*   - label_html: HTML label (not escaped). If provided, overrides 'label'
	*   - size: For select fields, number of visible rows (height multiplier)
	*/
	function fi_form_field(string $id, array $args = []): void {
		$type = $args['type'] ?? 'text';
		$value = $args['value'] ?? '';
		// Normalize stored values so quotes render correctly.
		if (is_string($value)) {
			$value = wp_unslash($value);
		} elseif (is_array($value)) {
			$value = array_map(static function ($item) {
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
				$value_attr = esc_attr((string) $value);
				echo '<input type="hidden" id="' . $id . '" name="' . $name_attr . '" value="' . $value_attr . '">';
				return;

			case 'wysiwyg':
				if (!$no_wrapper) {
					echo '<div class="mb-3' . ($wrapper_class ? ' ' . esc_attr((string) $wrapper_class) : '') . '">';
				}
				$label_text = $label_html !== null ? $label_html : $label;
				if ($label_text) {
					echo '<label for="' . $id . '" class="fw-bold text-muted' . esc_attr((string) $label_class) . '">';
					if ($label_html !== null) {
						echo $label_html; // HTML label (already escaped by caller)
					} else {
						echo esc_html((string) ($label ?? ''));
					}
					if ($required) {
						echo ' <span class="text-danger">*</span>';
					}
					echo '</label>';
				}

				// Base editor args
				$editor_settings = $args['editor_settings'] ?? [];
				$editor_args = array_replace_recursive([
					'textarea_name' => $name_attr,
					'editor_class'  => $base_class,
					'textarea_rows' => 4,          // Text view height
					'media_buttons' => false,
					'teeny'         => false,
					'tinymce'       => [
						'toolbar1'            => 'formatselect,bold,italic,underline,|,bullist,numlist,|,blockquote,|,undo,redo,|,code',
						'toolbar2'            => '',
						'plugins'             => 'lists,paste,wordpress,wpeditimage,tabfocus', // paste = paste-from-Word cleanup; server-side also normalizes on save
						'wpautop'             => true,
						'indent'              => true,
						'elementpath'         => false,
						'resize'              => true,
						'height'              => 220,
						'forced_root_block'   => 'p',     // Essential: wraps all text in <p>
						'force_p_newlines'    => true,
						'force_br_newlines'   => false,
					],
					'quicktags'     => [
						'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li',
					],
				], $editor_settings);
				wp_editor( $value, $id, $editor_args );
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
							echo $label_html; // HTML label
						} else {
							echo esc_html((string) ($label ?? ''));
						}
						if ($required) {
							echo ' <span class="text-danger">*</span>';
						}
						echo '</label>';
					}
				}
				echo $html; // HTML is already escaped by caller
				if ($help) {
					echo '<div class="form-text">' . wp_kses_post($help) . '</div>';
				}
				if (!$no_wrapper && ($label_text || $help)) {
					echo '</div>';
				}
				return;

			default:
				if (!$no_wrapper) {
					echo '<div class="mb-3' . ($wrapper_class ? ' ' . esc_attr((string) $wrapper_class) : '') . '">';
				}

				$label_text = $label_html !== null ? $label_html : $label;
				if (
					$label_text
					&& $type !== 'checkbox'
					&& $type !== 'switch'
				) {
					echo '<label for="' . $id . '" class="fw-bold text-muted' . esc_attr((string) $label_class) . '">';
					if ($label_html !== null) {
						echo $label_html; // HTML label (already escaped by caller)
					} else {
						echo esc_html((string) ($label ?? ''));
					}
					if ($required) {
						echo ' <span class="text-danger">*</span>';
					}
					echo '</label>';
				}

			$is_checkbox_or_switch = ($type === 'checkbox' || $type === 'switch');

			switch ($type) {
				case 'radio-group':
					// Button group radio buttons (Bootstrap 5 style)
					// Supports conditional button classes via 'button_classes' array
					$button_classes = $args['button_classes'] ?? [];
					$button_size = $args['button_size'] ?? ''; // 'sm' or 'lg'
					$size_class = $button_size ? ' btn-group-' . esc_attr($button_size) : '';
					
					echo '<div class="btn-group w-100' . $size_class . '" role="group" aria-label="' . esc_attr($label) . '">';
					foreach ($options as $opt_value => $opt_label) {
						$opt_value_str = (string) $opt_value;
						$opt_label_str = is_array($opt_label) ? ($opt_label['name'] ?? $opt_value_str) : (string) $opt_label;
						$input_id = $id . '-' . sanitize_key($opt_value_str);
						$checked = checked($value, $opt_value_str, false);
						
						// Get button class for this option (default to btn-outline-secondary)
						$btn_class = 'btn-outline-secondary';
						if (isset($button_classes[$opt_value_str])) {
							if (is_array($button_classes[$opt_value_str])) {
								// Conditional classes based on current value
								$btn_class = $button_classes[$opt_value_str][$value] ?? $btn_class;
							} else {
								// Static class for this option
								$btn_class = $button_classes[$opt_value_str];
							}
						}
						
						echo '<input type="radio" class="btn-check" name="' . $name_attr . '" id="' . esc_attr($input_id) . '" value="' . esc_attr($opt_value_str) . '"' . $checked . ($required ? ' required' : '') . '>';
						echo '<label class="btn ' . esc_attr($btn_class) . '" for="' . esc_attr($input_id) . '">' . esc_html($opt_label_str) . '</label>';
					}
					echo '</div>';
					break;

				case 'textarea':
					$value_attr = esc_textarea((string) $value);
					echo '<textarea id="' . $id . '" name="' . $name_attr . '"' . $attr_string . '>' . $value_attr . '</textarea>';
					break;

				case 'select':
						$size_attr = '';
						if ($select_size !== null && is_numeric($select_size)) {
							$size_attr = ' size="' . esc_attr((int) $select_size) . '"';
						}
						echo '<select id="' . $id . '" name="' . $name_attr . '"' . $attr_string . $size_attr . '>';
						if (!empty($options)) {
							if ($multiple && is_array($value)) {
								foreach ($options as $opt_value => $opt_label) {
									// Ensure opt_label is a string (handle arrays from options and null values)
									if (is_array($opt_label)) {
										$opt_label_str = $opt_label['name'] ?? $opt_label['short'] ?? (string) $opt_value;
									} elseif ($opt_label === null) {
										$opt_label_str = (string) $opt_value;
									} else {
										$opt_label_str = (string) $opt_label;
									}
									// Ensure we never pass null to esc_html
									$opt_label_str = $opt_label_str ?? (string) $opt_value;
									$selected = in_array((string) $opt_value, array_map('strval', $value), true) ? ' selected' : '';
									echo '<option value="' . esc_attr($opt_value) . '"' . $selected . '>' . esc_html($opt_label_str) . '</option>';
								}
							} else {
								foreach ($options as $opt_value => $opt_label) {
									// Ensure opt_label is a string (handle arrays from options and null values)
									if (is_array($opt_label)) {
										$opt_label_str = $opt_label['name'] ?? $opt_label['short'] ?? (string) $opt_value;
									} elseif ($opt_label === null) {
										$opt_label_str = (string) $opt_value;
									} else {
										$opt_label_str = (string) $opt_label;
									}
									// Ensure we never pass null to esc_html
									$opt_label_str = $opt_label_str ?? (string) $opt_value;
									$selected = selected($value, $opt_value, false);
									echo '<option value="' . esc_attr($opt_value) . '"' . $selected . '>' . esc_html($opt_label_str) . '</option>';
								}
							}
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
						$value_attr = esc_attr((string) $value);
						echo '<input type="number" id="' . $id . '" name="' . $name_attr . '" value="' . $value_attr . '"' . $attr_string . '>';
						break;

					case 'email':
						$value_attr = esc_attr((string) $value);
						echo '<input type="email" id="' . $id . '" name="' . $name_attr . '" value="' . $value_attr . '"' . $attr_string . '>';
						break;

					case 'url':
						$value_attr = esc_attr((string) $value);
						echo '<input type="url" id="' . $id . '" name="' . $name_attr . '" value="' . $value_attr . '"' . $attr_string . '>';
						break;

					case 'date':
						$value_attr = esc_attr((string) $value);
						echo '<input type="date" id="' . $id . '" name="' . $name_attr . '" value="' . $value_attr . '"' . $attr_string . '>';
						break;

					default: // text
						$value_attr = esc_attr((string) $value);
						echo '<input type="text" id="' . $id . '" name="' . $name_attr . '" value="' . $value_attr . '"' . $attr_string . '>';
						break;
				}

				if ($help) {
					echo '<div class="form-text">' . wp_kses_post($help) . '</div>';
				}

				if (!$no_wrapper) {
					echo '</div>';
				}
				break;
		}
	}

} // End FI\Core namespace

/**
 * Global helper functions (outside namespace)
 */
namespace {
    if (!function_exists('fi_form_field')) {
        function fi_form_field(string $name, array $args = []): void {
            \FI\Core\fi_form_field($name, $args);
        }
    }

    /**
     * State selector field (US states only, excludes Congress)
     */
    if (!function_exists('fi_form_field_state')) {
        function fi_form_field_state(string $name, array $args = []): void {
            $state_options = \FI\Core\Governments::get_state_options();
            // Ensure District of Columbia appears near the top (unless already included)
            $dc = ['DC' => 'District of Columbia'];
            if (isset($state_options['DC'])) {
                unset($state_options['DC']);
            }
            $state_options = array_merge(['' => 'Select State'], $dc, $state_options);
            $args['type'] = 'select';
            $args['options'] = $state_options;
            fi_form_field($name, $args);
        }
    }

    /**
     * Government selector field (Congress + all states)
     */
    if (!function_exists('fi_form_field_government')) {
        function fi_form_field_government(string $name, array $args = []): void {
            $gov_options = \FI\Core\Governments::get_options();
            $args['type'] = 'select';
            $args['options'] = $gov_options;
            fi_form_field($name, $args);
        }
    }

    /**
     * Party selector field
     */
    if (!function_exists('fi_form_field_party')) {
        function fi_form_field_party(string $name, array $args = []): void {
            $parties = \FI\Core\Parties::get_all();
            $party_options = [];
            foreach ($parties as $abbr => $data) {
                $party_options[strtoupper($abbr)] = $data['name'] ?? strtoupper($abbr);
            }
            $args['type'] = 'select';
            $args['options'] = array_merge(['' => 'Select Party'], $party_options);
            fi_form_field($name, $args);
        }
    }

    /**
     * Chamber selector field (varies by government)
     * 
     * @param string $name Field name
     * @param string $gov Government code (e.g., 'US', 'TX')
     * @param array $args Additional field arguments
     */
    if (!function_exists('fi_form_field_chamber')) {
        function fi_form_field_chamber(string $name, string $gov, array $args = []): void {
            $chamber_options_raw = fi_chamber_options($gov);
            // Convert array values to strings (use 'name' key if available, otherwise 'short')
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

    /**
     * Vote status selector field
     */
    if (!function_exists('fi_form_field_status')) {
        function fi_form_field_status(string $name, array $args = []): void {
            $status_options = \FI\Core\Votes::get_status_options();
            $args['type'] = 'select';
            $args['options'] = $status_options;
            fi_form_field($name, $args);
        }
    }

    /**
     * Address type selector field
     */
    if (!function_exists('fi_form_field_address_type')) {
        function fi_form_field_address_type(string $name, array $args = []): void {
            $type_options = [
                'capitol' => 'Capitol',
                'district' => 'District',
                'local' => 'Local',
                'other' => 'Other'
            ];
            $args['type'] = 'select';
            $args['options'] = $type_options;
            fi_form_field($name, $args);
        }
    }

    /**
     * Constitutional position selector field
     */
    if (!function_exists('fi_form_field_constitutional')) {
        function fi_form_field_constitutional(string $name, array $args = []): void {
            $options = [
				'U' => 'Unknown (U)',
                'Y' => 'Constitutional (Y)',
                'N' => 'Unconstitutional (N)'
            ];
            $args['type'] = 'select';
            $args['options'] = $options;
            fi_form_field($name, $args);
        }
    }
}

