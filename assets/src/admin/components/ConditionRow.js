/**
 * Single condition row (Elementor-style chained selects).
 */
import { useMemo } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import AsyncSelect from 'react-select/async';
import apiFetch from '@wordpress/api-fetch';
import { wpSelectStyles } from '../selectStyles';

const getOptions = () => window.assetpilotAdmin?.conditionOptions || {};

async function searchPosts( input ) {
	const term = ( input || '' ).trim();
	if ( term.length < 2 ) {
		return [];
	}
	const batches = await Promise.all( [
		apiFetch( {
			path: `/wp/v2/posts?search=${ encodeURIComponent( term ) }&per_page=12&_fields=id,title&status=publish`,
		} ).catch( () => [] ),
		apiFetch( {
			path: `/wp/v2/pages?search=${ encodeURIComponent( term ) }&per_page=12&_fields=id,title&status=publish`,
		} ).catch( () => [] ),
	] );
	const seen = new Set();
	return batches
		.flat()
		.filter( ( post ) => {
			if ( seen.has( post.id ) ) {
				return false;
			}
			seen.add( post.id );
			return true;
		} )
		.map( ( post ) => {
			const title = ( post.title?.rendered || '' ).replace( /<[^>]+>/g, '' ).trim();
			return {
				value: post.id,
				label: title ? `${ title } (#${ post.id })` : `#${ post.id }`,
			};
		} );
}

async function loadPostOption( id ) {
	if ( ! id ) {
		return null;
	}
	const batches = await Promise.all( [
		apiFetch( { path: `/wp/v2/posts/${ id }?_fields=id,title` } ).catch( () => null ),
		apiFetch( { path: `/wp/v2/pages/${ id }?_fields=id,title` } ).catch( () => null ),
	] );
	const post = batches.find( Boolean );
	if ( ! post ) {
		return { value: id, label: `#${ id }` };
	}
	const title = ( post.title?.rendered || '' ).replace( /<[^>]+>/g, '' ).trim();
	return { value: id, label: title ? `${ title } (#${ id })` : `#${ id }` };
}

function FieldSelect( { value, onChange, options, className = '', 'aria-label': ariaLabel } ) {
	return (
		<select
			className={ `assetpilot-condition-row__select assetpilot-toolbar-field ${ className }`.trim() }
			value={ value }
			onChange={ ( e ) => onChange( e.target.value ) }
			aria-label={ ariaLabel }
		>
			{ options.map( ( opt ) => (
				<option key={ opt.value } value={ opt.value }>
					{ opt.label }
				</option>
			) ) }
		</select>
	);
}

/**
 * @param {Object} props
 * @param {Object} props.row
 * @param {(row: Object) => void} props.onChange
 * @param {() => void} props.onRemove
 * @param {boolean} props.canRemove
 */
export default function ConditionRow( { row, onChange, onRemove, canRemove, defaultScanUrl = '' } ) {
	const { postTypes = [], archives = [], wcPages = [], userRoles = [], urlMatchModes = [] } =
		getOptions();

	const includeScopes = useMemo(
		() => [
			{ value: 'entire_site', label: __( 'Entire site', 'assetpilot' ) },
			{ value: 'singular', label: __( 'Singular', 'assetpilot' ) },
			{ value: 'archive', label: __( 'Archives', 'assetpilot' ) },
			{ value: 'post_type_archive', label: __( 'Post type archive', 'assetpilot' ) },
			...( wcPages.length
				? [ { value: 'woocommerce', label: __( 'WooCommerce', 'assetpilot' ) } ]
				: [] ),
			{ value: 'scan_page', label: __( 'Scanned page URL', 'assetpilot' ) },
			{ value: 'url', label: __( 'URL path', 'assetpilot' ) },
			{ value: 'query', label: __( 'Query string', 'assetpilot' ) },
			{ value: 'role', label: __( 'User role', 'assetpilot' ) },
			{ value: 'device', label: __( 'Device', 'assetpilot' ) },
			{ value: 'auth', label: __( 'Logged-in status', 'assetpilot' ) },
		],
		[ wcPages.length ]
	);

	const excludeScopes = useMemo(
		() => [ { value: 'singular', label: __( 'Specific page', 'assetpilot' ) } ],
		[]
	);

	const scopeOptions = row.mode === 'exclude' ? excludeScopes : includeScopes;

	const patch = ( updates ) => onChange( { ...row, ...updates } );

	const onModeChange = ( mode ) => {
		if ( mode === 'exclude' ) {
			onChange( {
				...row,
				mode,
				scope: 'singular',
				target: 'specific',
				detail: '',
				postId: null,
			} );
			return;
		}
		onChange( {
			...row,
			mode,
			scope: row.scope === 'singular' && row.mode === 'exclude' ? 'singular' : row.scope,
			target: row.scope === 'entire_site' ? '' : row.target || 'all',
		} );
	};

	const onScopeChange = ( scope ) => {
		const defaults = {
			entire_site: { target: '', detail: '', postId: null },
			singular: { target: 'all', detail: '', postId: null },
			archive: { target: archives[ 0 ]?.value || 'home', detail: '', postId: null },
			post_type_archive: { target: postTypes[ 0 ]?.value || 'post', detail: '', postId: null },
			woocommerce: { target: wcPages[ 0 ]?.value || 'shop', detail: '', postId: null },
			scan_page: {
				target: defaultScanUrl || '',
				detail: defaultScanUrl || '',
				postId: null,
			},
			url: { target: '', detail: 'contains', postId: null },
			query: { target: '', detail: '', postId: null },
			role: { target: userRoles[ 0 ]?.value || 'administrator', detail: '', postId: null },
			device: { target: 'mobile', detail: '', postId: null },
			auth: { target: '1', detail: '', postId: null },
		};
		onChange( { ...row, scope, ...defaults[ scope ] } );
	};

	const singularTargets = [
		{ value: 'all', label: __( 'All singular', 'assetpilot' ) },
		{ value: 'post_type', label: __( 'Post type', 'assetpilot' ) },
		{ value: 'specific', label: __( 'Specific page', 'assetpilot' ) },
	];

	const deviceTargets = [
		{ value: 'mobile', label: __( 'Mobile', 'assetpilot' ) },
		{ value: 'desktop', label: __( 'Desktop', 'assetpilot' ) },
	];

	const authTargets = [
		{ value: '1', label: __( 'Logged in', 'assetpilot' ) },
		{ value: '0', label: __( 'Logged out', 'assetpilot' ) },
	];

	const postValue = row.postId
		? { value: row.postId, label: row.postLabel || `#${ row.postId }` }
		: null;

	return (
		<div className="assetpilot-condition-row">
			<div className="assetpilot-condition-row__fields">
				<FieldSelect
					value={ row.mode }
					onChange={ onModeChange }
					options={ [
						{ value: 'include', label: __( 'Include', 'assetpilot' ) },
						{ value: 'exclude', label: __( 'Exclude', 'assetpilot' ) },
					] }
					className="assetpilot-condition-row__select--mode"
					aria-label={ __( 'Include or exclude', 'assetpilot' ) }
				/>

				<FieldSelect
					value={ row.scope }
					onChange={ onScopeChange }
					options={ scopeOptions }
					aria-label={ __( 'Condition type', 'assetpilot' ) }
				/>

				{ row.scope === 'singular' && row.mode === 'include' && (
					<FieldSelect
						value={ row.target }
						onChange={ ( target ) =>
							patch( {
								target,
								detail: target === 'post_type' ? postTypes[ 0 ]?.value || '' : '',
								postId: target === 'specific' ? row.postId : null,
							} )
						}
						options={ singularTargets }
						aria-label={ __( 'Singular scope', 'assetpilot' ) }
					/>
				) }

				{ row.scope === 'singular' &&
					row.mode === 'include' &&
					row.target === 'post_type' && (
					<FieldSelect
						value={ row.detail || postTypes[ 0 ]?.value }
						onChange={ ( detail ) => patch( { detail } ) }
						options={ postTypes }
						aria-label={ __( 'Post type', 'assetpilot' ) }
					/>
				) }

				{ row.scope === 'singular' && row.target === 'specific' && (
					<div className="assetpilot-condition-row__search">
						<AsyncSelect
							cacheOptions
							defaultOptions={ false }
							loadOptions={ searchPosts }
							value={ postValue }
							onChange={ ( opt ) =>
								patch( {
									postId: opt ? opt.value : null,
									postLabel: opt ? opt.label : '',
								} )
							}
							placeholder={ __( 'Search pages…', 'assetpilot' ) }
							noOptionsMessage={ () =>
								__( 'Type at least 2 characters', 'assetpilot' )
							}
							styles={ wpSelectStyles }
							classNamePrefix="assetpilot-select"
							isClearable
						/>
					</div>
				) }

				{ row.scope === 'archive' && (
					<FieldSelect
						value={ row.target }
						onChange={ ( target ) => patch( { target, detail: target } ) }
						options={ archives }
						aria-label={ __( 'Archive type', 'assetpilot' ) }
					/>
				) }

				{ row.scope === 'post_type_archive' && (
					<FieldSelect
						value={ row.target }
						onChange={ ( target ) => patch( { target, detail: target } ) }
						options={ postTypes }
						aria-label={ __( 'Post type archive', 'assetpilot' ) }
					/>
				) }

				{ row.scope === 'woocommerce' && (
					<FieldSelect
						value={ row.target }
						onChange={ ( target ) => patch( { target, detail: target } ) }
						options={ wcPages }
						aria-label={ __( 'WooCommerce page', 'assetpilot' ) }
					/>
				) }

				{ row.scope === 'scan_page' && (
					<input
						type="url"
						className="assetpilot-condition-row__input assetpilot-toolbar-field assetpilot-condition-row__input--url"
						value={ row.target || '' }
						onChange={ ( e ) =>
							patch( { target: e.target.value, detail: e.target.value } )
						}
						placeholder={ defaultScanUrl || 'https://example.com/page/' }
						aria-label={ __( 'Scanned page URL', 'assetpilot' ) }
					/>
				) }

				{ row.scope === 'url' && (
					<>
						<FieldSelect
							value={ row.detail || 'contains' }
							onChange={ ( detail ) => patch( { detail } ) }
							options={ urlMatchModes }
							aria-label={ __( 'URL match type', 'assetpilot' ) }
						/>
						<input
							type="text"
							className="assetpilot-condition-row__input assetpilot-toolbar-field"
							value={ row.target || '' }
							onChange={ ( e ) => patch( { target: e.target.value } ) }
							placeholder="/contact/"
							aria-label={ __( 'URL path', 'assetpilot' ) }
						/>
					</>
				) }

				{ row.scope === 'query' && (
					<input
						type="text"
						className="assetpilot-condition-row__input assetpilot-toolbar-field"
						value={ row.target || '' }
						onChange={ ( e ) => patch( { target: e.target.value, detail: e.target.value } ) }
						placeholder="preview=true"
						aria-label={ __( 'Query string contains', 'assetpilot' ) }
					/>
				) }

				{ row.scope === 'role' && (
					<FieldSelect
						value={ row.target || userRoles[ 0 ]?.value }
						onChange={ ( target ) => patch( { target, detail: target } ) }
						options={ userRoles }
						aria-label={ __( 'User role', 'assetpilot' ) }
					/>
				) }

				{ row.scope === 'device' && (
					<FieldSelect
						value={ row.target }
						onChange={ ( target ) => patch( { target, detail: target } ) }
						options={ deviceTargets }
						aria-label={ __( 'Device', 'assetpilot' ) }
					/>
				) }

				{ row.scope === 'auth' && (
					<FieldSelect
						value={ row.target }
						onChange={ ( target ) => patch( { target, detail: target } ) }
						options={ authTargets }
						aria-label={ __( 'Login status', 'assetpilot' ) }
					/>
				) }
			</div>

			<Button
				icon="no-alt"
				label={ __( 'Remove condition', 'assetpilot' ) }
				onClick={ onRemove }
				disabled={ ! canRemove }
				className="assetpilot-condition-row__remove"
			/>
		</div>
	);
}
