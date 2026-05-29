/**
 * Multiselect post/page IDs with async search (react-select).
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { BaseControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import AsyncSelect from 'react-select/async';
import { wpSelectStyles } from '../selectStyles';

const formatOption = ( post ) => {
	const title = post.title?.rendered || post.title || '';
	const text = title.replace( /<[^>]+>/g, '' ).trim() || __( '(No title)', 'assetpilot' );
	return {
		value: post.id,
		label: `${ text } (#${ post.id })`,
	};
};

async function searchContent( input ) {
	const term = ( input || '' ).trim();
	if ( term.length < 2 ) {
		return [];
	}

	const batches = await Promise.all( [
		apiFetch( {
			path: `/wp/v2/posts?search=${ encodeURIComponent(
				term
			) }&per_page=15&_fields=id,title&status=publish`,
		} ).catch( () => [] ),
		apiFetch( {
			path: `/wp/v2/pages?search=${ encodeURIComponent(
				term
			) }&per_page=15&_fields=id,title&status=publish`,
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
		.map( formatOption );
}

async function fetchByIds( ids ) {
	if ( ! ids.length ) {
		return [];
	}

	const include = ids.join( ',' );
	const batches = await Promise.all( [
		apiFetch( {
			path: `/wp/v2/posts?include=${ include }&per_page=100&_fields=id,title`,
		} ).catch( () => [] ),
		apiFetch( {
			path: `/wp/v2/pages?include=${ include }&per_page=100&_fields=id,title`,
		} ).catch( () => [] ),
	] );

	const found = new Map();
	batches.flat().forEach( ( post ) => {
		found.set( post.id, formatOption( post ) );
	} );

	return ids.map( ( id ) => found.get( id ) || { value: id, label: `#${ id }` } );
}

/**
 * @param {Object} props
 * @param {string} props.label
 * @param {string} [props.help]
 * @param {number[]} props.value
 * @param {(ids: number[]) => void} props.onChange
 */
export default function PostIdMultiSelect( { label, help, value = [], onChange } ) {
	const [ selected, setSelected ] = useState( [] );
	const [ loading, setLoading ] = useState( !! value.length );

	useEffect( () => {
		let cancelled = false;

		if ( ! value.length ) {
			setSelected( [] );
			setLoading( false );
			return;
		}

		setLoading( true );
		fetchByIds( value ).then( ( options ) => {
			if ( ! cancelled ) {
				setSelected( options );
				setLoading( false );
			}
		} );

		return () => {
			cancelled = true;
		};
	}, [ value.join( ',' ) ] );

	const loadOptions = useCallback( ( inputValue ) => searchContent( inputValue ), [] );

	return (
		<BaseControl
			label={ label }
			help={ help }
			className="assetpilot-multiselect"
			__nextHasNoMarginBottom
		>
			<div className="assetpilot-select-wrap">
				{ loading ? (
					<div className="assetpilot-select-loading">
						<Spinner />
					</div>
				) : (
					<AsyncSelect
						isMulti
						isClearable
						cacheOptions
						defaultOptions={ false }
						loadOptions={ loadOptions }
						value={ selected }
						onChange={ ( chosen ) => {
							const next = ( chosen || [] ).map( ( item ) => Number( item.value ) );
							setSelected( chosen || [] );
							onChange( next );
						} }
						placeholder={ __( 'Search posts or pages…', 'assetpilot' ) }
						noOptionsMessage={ ( { inputValue } ) =>
							( inputValue || '' ).length < 2
								? __( 'Type at least 2 characters to search.', 'assetpilot' )
								: __( 'No results found.', 'assetpilot' )
						}
						loadingMessage={ () => __( 'Searching…', 'assetpilot' ) }
						classNamePrefix="assetpilot-select"
						styles={ wpSelectStyles }
						menuPortalTarget={ document.body }
						menuPosition="fixed"
					/>
				) }
			</div>
		</BaseControl>
	);
}
