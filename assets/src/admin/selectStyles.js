/**
 * react-select styles aligned with WordPress admin form controls.
 */

const colors = {
	text: '#1d2327',
	muted: '#646970',
	border: '#8c8f94',
	borderFocus: '#2271b1',
	bg: '#fff',
	chip: '#f0f0f1',
	chipHover: '#dcdcde',
	optionHover: '#f0f6fc',
	primary: '#2271b1',
};

/** @type {import('react-select').StylesConfig} */
export const wpSelectStyles = {
	control: ( base, state ) => ( {
		...base,
		minHeight: 36,
		backgroundColor: colors.bg,
		borderColor: state.isFocused ? colors.borderFocus : colors.border,
		borderRadius: 4,
		borderWidth: 1,
		boxShadow: state.isFocused ? `0 0 0 1px ${ colors.borderFocus }` : 'none',
		cursor: 'pointer',
		'&:hover': {
			borderColor: state.isFocused ? colors.borderFocus : colors.border,
		},
	} ),
	valueContainer: ( base ) => ( {
		...base,
		padding: '2px 8px',
		gap: 4,
	} ),
	input: ( base ) => ( {
		...base,
		margin: 0,
		padding: 0,
		color: colors.text,
		fontSize: 13,
	} ),
	placeholder: ( base ) => ( {
		...base,
		color: colors.muted,
		fontSize: 13,
	} ),
	singleValue: ( base ) => ( {
		...base,
		color: colors.text,
		fontSize: 13,
	} ),
	multiValue: ( base ) => ( {
		...base,
		backgroundColor: colors.chip,
		borderRadius: 2,
		margin: 2,
	} ),
	multiValueLabel: ( base ) => ( {
		...base,
		color: colors.text,
		fontSize: 13,
		padding: '2px 4px',
	} ),
	multiValueRemove: ( base ) => ( {
		...base,
		color: colors.muted,
		borderRadius: 0,
		':hover': {
			backgroundColor: colors.chipHover,
			color: colors.text,
		},
	} ),
	indicatorsContainer: ( base ) => ( {
		...base,
		paddingRight: 4,
	} ),
	indicatorSeparator: () => ( {
		display: 'none',
	} ),
	dropdownIndicator: ( base, state ) => ( {
		...base,
		color: colors.muted,
		padding: 6,
		':hover': {
			color: colors.text,
		},
		transform: state.selectProps.menuIsOpen ? 'rotate(180deg)' : undefined,
	} ),
	clearIndicator: ( base ) => ( {
		...base,
		color: colors.muted,
		padding: 6,
		':hover': {
			color: colors.text,
		},
	} ),
	menu: ( base ) => ( {
		...base,
		zIndex: 100000,
		marginTop: 4,
		borderRadius: 4,
		border: `1px solid ${ colors.border }`,
		boxShadow: '0 1px 2px rgba(0, 0, 0, 0.05)',
		overflow: 'hidden',
	} ),
	menuList: ( base ) => ( {
		...base,
		padding: 4,
		maxHeight: 240,
	} ),
	option: ( base, state ) => ( {
		...base,
		fontSize: 13,
		padding: '8px 12px',
		borderRadius: 2,
		cursor: 'pointer',
		backgroundColor: state.isSelected
			? colors.primary
			: state.isFocused
				? colors.optionHover
				: colors.bg,
		color: state.isSelected ? '#fff' : colors.text,
		':active': {
			backgroundColor: state.isSelected ? colors.primary : colors.optionHover,
		},
	} ),
	noOptionsMessage: ( base ) => ( {
		...base,
		fontSize: 13,
		color: colors.muted,
		padding: 8,
	} ),
	loadingMessage: ( base ) => ( {
		...base,
		fontSize: 13,
		color: colors.muted,
		padding: 8,
	} ),
	menuPortal: ( base ) => ( {
		...base,
		zIndex: 100001,
	} ),
};
