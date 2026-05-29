/**
 * Condition multiselect fields for Create Rule step 3.
 */
import { __ } from '@wordpress/i18n';
import MultiSelect from './MultiSelect';
import PostIdMultiSelect from './PostIdMultiSelect';

const getOptions = () => window.assetpilotAdmin?.conditionOptions || {};

/**
 * @param {Object} props
 * @param {Object} props.conditions
 * @param {(next: Object) => void} props.onChange
 */
export default function ConditionFields( { conditions, onChange } ) {
	const { postTypes = [], archives = [], wcPages = [] } = getOptions();

	const patch = ( key, val ) => onChange( { ...conditions, [ key ]: val } );

	return (
		<div className="assetpilot-condition-fields">
			<MultiSelect
				label={ __( 'Post type archives', 'assetpilot' ) }
				help={ __( 'Apply on archive and listing views for these post types.', 'assetpilot' ) }
				options={ postTypes }
				value={ conditions.post_type || [] }
				onChange={ ( post_type ) => patch( 'post_type', post_type ) }
				placeholder={ __( 'Select post types…', 'assetpilot' ) }
			/>
			<MultiSelect
				label={ __( 'Single pages', 'assetpilot' ) }
				help={ __( 'Apply when viewing a single post of these types.', 'assetpilot' ) }
				options={ postTypes }
				value={ conditions.singular_type || [] }
				onChange={ ( singular_type ) => patch( 'singular_type', singular_type ) }
				placeholder={ __( 'Select post types…', 'assetpilot' ) }
			/>

			<p className="assetpilot-field-group-label">
				{ __( 'Specific posts or pages', 'assetpilot' ) }
			</p>

			<PostIdMultiSelect
				label={ __( 'Only on these pages', 'assetpilot' ) }
				help={ __( 'Rule applies only here. Search by page title.', 'assetpilot' ) }
				value={ conditions.include_ids || [] }
				onChange={ ( include_ids ) => patch( 'include_ids', include_ids ) }
			/>
			<PostIdMultiSelect
				label={ __( 'Exclude these pages', 'assetpilot' ) }
				help={ __( 'Rule never applies on these pages, even if other conditions match.', 'assetpilot' ) }
				value={ conditions.exclude_ids || [] }
				onChange={ ( exclude_ids ) => patch( 'exclude_ids', exclude_ids ) }
			/>

			<MultiSelect
				label={ __( 'Archive pages', 'assetpilot' ) }
				help={ __( 'Category, tag, author, date, search, home, and taxonomy archives.', 'assetpilot' ) }
				options={ archives }
				value={ conditions.archive || [] }
				onChange={ ( archive ) => patch( 'archive', archive ) }
				placeholder={ __( 'Select archive types…', 'assetpilot' ) }
			/>
			{ wcPages.length > 0 && (
				<MultiSelect
					label={ __( 'WooCommerce pages', 'assetpilot' ) }
					help={ __( 'Shop, cart, checkout, account, and product pages.', 'assetpilot' ) }
					options={ wcPages }
					value={ conditions.woocommerce || [] }
					onChange={ ( woocommerce ) => patch( 'woocommerce', woocommerce ) }
					placeholder={ __( 'Select WooCommerce pages…', 'assetpilot' ) }
				/>
			) }
		</div>
	);
}
