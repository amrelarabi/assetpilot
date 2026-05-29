/**
 * Multiselect (Select2-style) using react-select.
 */
import { BaseControl } from '@wordpress/components';
import Select from 'react-select';
import { wpSelectStyles } from '../selectStyles';

/**
 * @param {Object} props
 * @param {string} props.label
 * @param {string} [props.help]
 * @param {{ value: string, label: string }[]} props.options
 * @param {string[]} props.value
 * @param {(values: string[]) => void} props.onChange
 * @param {string} [props.placeholder]
 * @param {boolean} [props.isDisabled]
 */
export default function MultiSelect( {
	label,
	help,
	options,
	value = [],
	onChange,
	placeholder,
	isDisabled = false,
} ) {
	const selected = options.filter( ( opt ) => value.includes( opt.value ) );

	return (
		<BaseControl
			label={ label }
			help={ help }
			className="assetpilot-multiselect"
			__nextHasNoMarginBottom
		>
			<div className="assetpilot-select-wrap">
				<Select
					isMulti
					isClearable
					isDisabled={ isDisabled }
					closeMenuOnSelect={ false }
					hideSelectedOptions={ false }
					options={ options }
					value={ selected }
					onChange={ ( chosen ) =>
						onChange( ( chosen || [] ).map( ( item ) => item.value ) )
					}
					placeholder={ placeholder }
					classNamePrefix="assetpilot-select"
					styles={ wpSelectStyles }
					menuPortalTarget={ document.body }
					menuPosition="fixed"
				/>
			</div>
		</BaseControl>
	);
}
