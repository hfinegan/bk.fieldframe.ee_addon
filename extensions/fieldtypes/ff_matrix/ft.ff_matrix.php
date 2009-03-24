<?php

if ( ! defined('EXT')) exit('Invalid file request');


/**
 * FF Matrix Class
 *
 * @package   FieldFrame
 * @author    Brandon Kelly <me@brandon-kelly.com>
 * @copyright Copyright (c) 2009 Brandon Kelly
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */
class Ff_matrix extends Fieldframe_Fieldtype {

	/**
	 * Fieldtype Info
	 * @var array
	 */
	var $info = array(
		'name'     => 'FF Matrix',
		'version'  => FF_VERSION,
		'desc'     => 'Provides a tabular data fieldtype',
		'docs_url' => 'http://wiki.github.com/brandonkelly/bk.fieldframe.ee_addon/ff-checkbox'
	);

	/**
	 * Default Field Settings
	 * @var array
	 */
	var $default_field_settings = array(
		'cols' => array(
			'1' => array('name' => 'cell_1', 'label' => 'Cell 1', 'type' => 'ff_matrix_text', 'new' => 'y'),
			'2' => array('name' => 'cell_2', 'label' => 'Cell 2', 'type' => 'ff_matrix_textarea', 'new' => 'y')
		)
	);

	/**
	 * Get Fieldtypes
	 *
	 * @access private
	 */
	function _get_ftypes()
	{
		global $FF;

		if ( ! isset($this->ftypes))
		{
			$this->ftypes = array();

			// Add the included celltypes
			$this->ftypes['ff_matrix_text'] = new Ff_matrix_text();
			$this->ftypes['ff_matrix_textarea'] = new Ff_matrix_textarea();
			$this->ftypes['ff_matrix_select'] = new Ff_matrix_select();
			$this->ftypes['ff_matrix_multiselect'] = new ff_matrix_multiselect();

			// Get the FF fieldtyes with display_cell
			$ftypes = array();
			foreach($FF->_get_ftypes() as $class_name => $ftype)
			{
				if (method_exists($ftype, 'display_cell'))
				{
					$ftypes[$class_name] = $ftype;
				}
			}
			$FF->_sort_ftypes($ftypes);

			// Combine with the included celltypes
			$this->ftypes = array_merge($this->ftypes, $ftypes);
		}

		return $this->ftypes;
	}

	/**
	 * Display Field Settings
	 * 
	 * @param  array  $field_settings  The field's settings
	 * @return array  Settings HTML (cell1, cell2, rows)
	 */
	function display_field_settings($field_settings)
	{
		global $DSP, $LANG;

		$this->include_css('styles/ff_matrix.css');
		$this->include_js('scripts/jquery.sorttable.js');
		$this->include_js('scripts/jquery.ff_matrix_conf.js');

		$ftypes = $this->_get_ftypes();

		$cell_types = array();
		foreach($ftypes as $class_name => $ftype)
		{
			$cell_settings = isset($ftype->default_cell_settings) ? $ftype->default_cell_settings : array();

			if (method_exists($ftype, 'display_cell_settings'))
			{
				if (substr($ftype->_class_name, 0, 10) != 'ff_matrix_') $LANG->fetch_language_file($class_name);
				$settings_display = $ftype->display_cell_settings($cell_settings);
			}
			else
			{
				$settings_display = '';
			}

			$cell_types[$class_name] = array(
				'name' => $ftype->info['name'],
				'preview' => $ftype->display_cell('', '', $cell_settings),
				'settings' => $settings_display
			);
		}

		$cols = array();
		foreach($field_settings['cols'] as $col_id => $col)
		{
			$ftype = $ftypes[$col['type']];
			$cell_settings = array_merge(
				(isset($ftype->default_cell_settings) ? $ftype->default_cell_settings : array()),
				(isset($col['settings']) ? $col['settings'] : array())
			);

			$cols[$col_id] = array(
				'name' => $col['name'],
				'label' => $col['label'],
				'type' => $col['type'],
				'preview' => $ftype->display_cell('', '', $cell_settings),
				'settings' => (method_exists($ftype, 'display_cell_settings') ? $ftype->display_cell_settings($cell_settings) : ''),
				'isNew' => isset($col['new'])
			);
		}

		$js = 'jQuery(window).bind("load", function() {' . NL
		    . '  jQuery.fn.ffMatrixConf.lang.colName = "'.$LANG->line('col_name').'";' . NL
		    . '  jQuery.fn.ffMatrixConf.lang.colLabel = "'.$LANG->line('col_label').'";' . NL
		    . '  jQuery.fn.ffMatrixConf.lang.cellType = "'.$LANG->line('cell_type').'";' . NL
		    . '  jQuery.fn.ffMatrixConf.lang.cell = "'.$LANG->line('cell').'";' . NL
		    . '  jQuery.fn.ffMatrixConf.lang.deleteColumn = "'.$LANG->line('delete_column').'";' . NL
		    . '  jQuery.fn.ffMatrixConf.lang.confirmDeleteColumn = "'.$LANG->line('confirm_delete_column').'";' . NL
		    . NL
		    . '  jQuery.fn.ffMatrixConf.cellTypes = '.json_encode($cell_types).';' . NL
		    . NL
		    . '  jQuery(".ff_matrix_conf").ffMatrixConf('.$this->_fieldtype_id.', '.json_encode($cols).');' . NL
		    . '});';

		$this->insert_js($js);

		// display the config skeleton
		$preview = $DSP->qdiv('defaultBold', $LANG->line('conf_label'))
                 . $DSP->qdiv('itemWrapper', $LANG->line('conf_subtext'))
		         . $DSP->div('ff_matrix ff_matrix_conf')
		         .   '<a class="button add" title="'.$LANG->line('add_column').'"></a>'
		         .   '<table cellspacing="0" cellpadding="0">'
		         .     '<tr class="tableHeading"></tr>'
		         .     '<tr class="preview"></tr>'
		         .     '<tr class="conf col"></tr>'
		         .     '<tr class="conf celltype"></tr>'
		         .     '<tr class="conf cellsettings"></tr>'
		         .     '<tr class="delete"></tr>'
		         .   '</table>'
		         . $DSP->div_c();

		return array('rows' => array(array($preview)));
	}

	/**
	 * Save Field Settings
	 *
	 * Turn the options textarea value into an array of option names and labels
	 * 
	 * @param  array  $settings  The user-submitted settings, pulled from $_POST
	 * @return array  Modified $settings
	 */
	function save_field_settings($field_settings)
	{
		$ftypes = $this->_get_ftypes();

		foreach($field_settings['cols'] as $col_id => &$col)
		{
			$ftype = $ftypes[$col['type']];
			if (method_exists($ftype, 'save_cell_settings'))
			{
				$col['settings'] = $ftype->save_cell_settings($col['settings']);
			}
		}

		return $field_settings;
	}

	/**
	 * Display Field
	 * 
	 * @param  string  $field_name      The field's name
	 * @param  mixed   $field_data      The field's current value
	 * @param  array   $field_settings  The field's settings
	 * @return string  The field's HTML
	 */
	function display_field($field_name, $field_data, $field_settings)
	{
		global $DSP, $REGX, $FF, $LANG;

		$ftypes = $this->_get_ftypes();

		$this->include_css('styles/ff_matrix.css');
		$this->include_js('scripts/jquery.ff_matrix.js');

		$cell_defaults = array();
		$r = '<div class="ff_matrix" id="'.$field_name.'">'
		   .   '<table cellspacing="0" cellpadding="0">'
		   .     '<tr class="tableHeading">';
		foreach($field_settings['cols'] as $col_id => $col)
		{
			// add the header
			$r .=  '<th>'.$col['label'].'</th>';

			// get the default state
			$ftype = $ftypes[$col['type']];
			$cell_settings = array_merge(
				(isset($ftype->default_cell_settings) ? $ftype->default_cell_settings : array()),
				(isset($col['settings']) ? $col['settings'] : array())
			);
			$cell_defaults[] = array(
				'type' => $col['type'],
				'cell' => $ftype->display_cell($field_name.'[0]['.$col_id.']', '', $cell_settings)
			);
		}
		$r .=    '</tr>';

		if ( ! $field_data)
		{
			$field_data = array(array());
		}

		$num_cols = count($field_settings['cols']);
		foreach($field_data as $row_count => $row)
		{
			$r .= '<tr>';
			$col_count = 0;
			foreach($field_settings['cols'] as $col_id => $col)
			{
				$ftype = $ftypes[$col['type']];
				$cell_name = $field_name.'['.$row_count.']['.$col_id.']';
				$cell_settings = array_merge(
					(isset($ftype->default_cell_settings) ? $ftype->default_cell_settings : array()),
					(isset($col['settings']) ? $col['settings'] : array())
				);
				$cell_data = isset($row[$col_id]) ? $row[$col_id] : '';
				$r .= '<td class="'.($row_count % 2 ? 'tableCellTwo' : 'tableCellOne').' '.$col['type'].'">'
				    .   $ftype->display_cell($cell_name, $cell_data, $cell_settings)
				    . '</td>';
				$col_count++;
			}
			$r .= '</tr>';
		}

		$r .=   '</table>'
		    . '</div>';

		$LANG->fetch_language_file('ff_matrix');

		$js = 'jQuery(window).bind("load", function() {' . NL
		    . '  jQuery.fn.ffMatrix.lang.addRow = "'.$LANG->line('add_row').'";' . NL
		    . '  jQuery.fn.ffMatrix.lang.deleteRow = "'.$LANG->line('delete_row').'";' . NL
		    . '  jQuery.fn.ffMatrix.lang.confirmDeleteRow = "'.$LANG->line('confirm_delete_row').'";' . NL
		    . '  jQuery.fn.ffMatrix.lang.sortRow = "'.$LANG->line('sort_row').'";' . NL
		    . '  jQuery("#'.$field_name.'").ffMatrix("'.$field_name.'", '.json_encode($cell_defaults).');' . NL
		    . '});';

		$this->insert_js($js);

		return $r;
	}

	/**
	 * Save Field
	 * 
	 * @param  mixed  $field_data      The field's current value
	 * @param  array  $field_settings  The field's settings
	 * @return array  Modified $field_settings
	 */
	function save_field($field_data, $field_settings)
	{
		$ftypes = $this->_get_ftypes();

		foreach($field_data as $row_count => &$row)
		{
			foreach($row as $col_id => &$cell_data)
			{
				$col = $field_settings['cols'][$col_id];
				$ftype = $ftypes[$col['type']];
				if (method_exists($ftype, 'save_cell'))
				{
					$cell_settings = array_merge(
						(isset($ftype->default_cell_settings) ? $ftype->default_cell_settings : array()),
						(isset($col['settings']) ? $col['settings'] : array())
					);
					$cell_data = $ftype->save_cell($cell_data, $cell_settings);
				}
			}
		}

		return $field_data;
	}

	/**
	 * Display Tag
	 *
	 * @param  array   $params          Name/value pairs from the opening tag
	 * @param  string  $tagdata         Chunk of tagdata between field tag pairs
	 * @param  string  $field_data      Currently saved field value
	 * @param  array   $field_settings  The field's settings
	 * @return string  relationship references
	 */
	function display_tag($params, $tagdata, $field_data, $field_settings)
	{
		global $FF;

		$r = '';

		$ftypes = $this->_get_ftypes();

		foreach($field_data as $row_count => $row)
		{
			$row_tagdata = $tagdata;

			foreach($field_settings['cols'] as $col_id => $col)
			{
				$ftype = $ftypes[$col['type']];
				$cell_data = isset($row[$col_id]) ? $row[$col_id] : '';
				$cell_settings = array_merge(
					(isset($ftype->default_cell_settings) ? $ftype->default_cell_settings : array()),
					(isset($col['settings']) ? $col['settings'] : array())
				);
				$FF->_parse_tagdata($row_tagdata, $col['name'], $cell_data, $cell_settings, $ftype);

				// conditionals
				if (is_array($cell_data)) $cell_data = $cell_data ? '1' : '0';
				$row_tagdata = preg_replace('/('.LD.'if(:elseif)?\s+(.*\s+)?)('.$col['name'].')((\s+.*)?'.RD.')/isU', '$1"'.$cell_data.'"$5', $row_tagdata);
			}

			$r .= $row_tagdata;
		}

		return $r;
	}

}


class Ff_matrix_text extends Fieldframe_Fieldtype {

	var $_class_name = 'ff_matrix_text';

	var $info = array(
		'name' => 'Text'
	);

	var $default_cell_settings = array(
		'maxl' => '128'
	);

	function display_cell_settings($cell_settings)
	{
		global $DSP, $LANG;

		$r = '<label class="itemWrapper">'
		   . $DSP->input_text('maxl', $cell_settings['maxl'], '3', '3', 'input', '30px') . NBS
		   . $LANG->line('field_max_length')
		   . '</label>';

		return $r;
	}

	function display_cell($cell_name, $cell_value, $cell_settings)
	{
		global $DSP;
		return $DSP->input_text($cell_name, $cell_value, '', $cell_settings['maxl'], '', '95%');
	}

}


class Ff_matrix_textarea extends Fieldframe_Fieldtype {

	var $_class_name = 'ff_matrix_textarea';

	var $info = array(
		'name' => 'Textarea'
	);

	var $default_cell_settings = array(
		'rows' => '2'
	);

	function display_cell_settings($cell_settings)
	{
		global $DSP, $LANG;

		$r = '<label class="itemWrapper">'
		   . $DSP->input_text('rows', $cell_settings['rows'], '3', '3', 'input', '30px') . NBS
		   . $LANG->line('textarea_rows')
		   . '</label>';

		return $r;
	}

	function display_cell($cell_name, $cell_value, $cell_settings)
	{
		global $DSP;
		return $DSP->input_textarea($cell_name, $cell_value, $cell_settings['rows'], '', '95%');
	}

}


class Ff_matrix_select extends Fieldframe_Fieldtype {

	var $_class_name = 'ff_matrix_select';

	var $info = array(
		'name' => 'Select'
	);

	var $default_cell_settings = array(
		'options' => array(
			'Opt 1' => 'Opt 1',
			'Opt 2' => 'Opt 2'
		)
	);

	function display_cell_settings($cell_settings)
	{
		global $DSP, $LANG;

		$r = '<label class="itemWrapper">'
		   . $DSP->qdiv('defaultBold', $LANG->line('field_list_items'))
		   . $DSP->input_textarea('options', $this->options_setting($cell_settings['options']), '3', 'textarea', '140px')
		   . '</label>';

		return $r;
	}

	function save_cell_settings($cell_settings)
	{
		$cell_settings['options'] = $this->save_options_setting($cell_settings['options']);
		return $cell_settings;
	}

	function display_cell($cell_name, $cell_value, $cell_settings)
	{
		$SD = new Fieldframe_SettingsDisplay();
		return $SD->select($cell_name, $cell_value, $cell_settings['options']);
	}

}


class Ff_matrix_multiselect extends Fieldframe_Fieldtype {

	var $_class_name = 'ff_matrix_multiselect';

	var $info = array(
		'name' => 'Multi-select'
	);

	var $default_cell_settings = array(
		'options' => array(
			'Opt 1' => 'Opt 1',
			'Opt 2' => 'Opt 2'
		)
	);

	function display_cell_settings($cell_settings)
	{
		global $DSP, $LANG;

		$r = '<label class="itemWrapper">'
		   . $DSP->qdiv('defaultBold', $LANG->line('field_list_items'))
		   . $DSP->input_textarea('options', $this->options_setting($cell_settings['options']), '3', 'textarea', '140px')
		   . '</label>';

		return $r;
	}

	function save_cell_settings($cell_settings)
	{
		$cell_settings['options'] = $this->save_options_setting($cell_settings['options']);
		return $cell_settings;
	}

	function display_cell($cell_name, $cell_value, $cell_settings)
	{
		$SD = new Fieldframe_SettingsDisplay();
		return $SD->multiselect($cell_name, $cell_value, $cell_settings['options'], array('width' => '145px'));
	}

	function display_tag($params, $tagdata, $field_data, $field_settings)
	{
		global $TMPL;

		$r = '';

		if ($field_settings['options'])
		{
			// option template
			if ( ! $field_data) $field_data = array();

			// optional sorting
			if ($sort = strtolower($params['sort']))
			{
				if ($sort == 'asc')
				{
					sort($field_data);
				}
				else if ($sort == 'desc')
				{
					rsort($field_data);
				}
			}

			// replace switch tags with {SWITCH[abcdefgh]SWITCH} markers
			$this->switches = array();
			$tagdata = preg_replace_callback('/'.LD.'switch\s*=\s*[\'\"]([^\'\"]+)[\'\"]'.RD.'/sU', array(&$this, '_get_switch_options'), $tagdata);

			$count = 0;
			foreach($field_data as $option_name)
			{
				if (isset($field_settings['options'][$option_name]))
				{
					// copy $tagdata
					$option_tagdata = $tagdata;

					// simple var swaps
					$option_tagdata = $TMPL->swap_var_single('option', $field_settings['options'][$option_name], $option_tagdata);
					$option_tagdata = $TMPL->swap_var_single('option_name', $option_name, $option_tagdata);
					$option_tagdata = $TMPL->swap_var_single('count', $count+1, $option_tagdata);

					// switch tags
					foreach($this->switches as $i => $switch)
					{
						$option = $count % count($switch['options']);
						$option_tagdata = str_replace($switch['marker'], $switch['options'][$option], $option_tagdata);
					}

					$r .= $option_tagdata;

					$count++;
				}
			}
		}

		if ($params['backspace'])
		{
			$r = substr($r, 0, -$params['backspace']);
		}

		return $r;
	}

}


/* End of file ft.ff_matrix.php */
/* Location: ./system/fieldtypes/ff_matrix/ft.ff_matrix.php */