/**
 * Block editor PluginSidebar — page-level asset rules.
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, Button, SelectControl, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { fetchAssets, createRule, fetchDependencies } from '../admin/api';
import DependencyWarning from '../admin/components/DependencyWarning';
import {
	getActionOptionsForType,
	resolveActionForType,
} from '../admin/actionOptions';

apiFetch.use( ( options, next ) => {
	const nonce = window.assetpilotAdmin?.nonce;
	if ( nonce ) {
		options.headers = { ...options.headers, 'X-WP-Nonce': nonce };
	}
	return next( options );
} );

export default function EditorSidebar() {
	const [ assets, setAssets ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ selected, setSelected ] = useState( '' );
	const [ action, setAction ] = useState( 'disable' );
	const [ depWarning, setDepWarning ] = useState( null );
	const [ notice, setNotice ] = useState( '' );
	const postId = window.assetpilotAdmin?.postId;

	useEffect( () => {
		const scanUrl = postId
			? `${ window.assetpilotAdmin?.homeUrl || '' }?p=${ postId }`
			: window.assetpilotAdmin?.homeUrl;

		fetchAssets( { scan_url: scanUrl } )
			.then( ( res ) => setAssets( ( res.assets || [] ).filter( ( a ) => a.enqueued ) ) )
			.finally( () => setLoading( false ) );
	}, [ postId ] );

	useEffect( () => {
		const asset = assets.find( ( a ) => a.handle === selected );
		if ( ! asset || ! [ 'script', 'style' ].includes( asset.type ) ) {
			setDepWarning( null );
			return;
		}
		fetchDependencies( asset.handle, asset.type, action )
			.then( setDepWarning )
			.catch( () => setDepWarning( null ) );
	}, [ selected, action, assets ] );

	const selectedAsset = assets.find( ( a ) => a.handle === selected );
	const actionOptions = useMemo(
		() => getActionOptionsForType( selectedAsset?.type || '' ),
		[ selectedAsset?.type ]
	);

	useEffect( () => {
		if ( ! selectedAsset?.type ) {
			return;
		}
		const resolved = resolveActionForType( action, selectedAsset.type );
		if ( resolved !== action ) {
			setAction( resolved );
		}
	}, [ selectedAsset?.type, action ] );

	const handleApply = async () => {
		const asset = assets.find( ( a ) => a.handle === selected );
		if ( ! asset || ! postId ) {
			return;
		}
		await createRule( {
			asset_handle: asset.handle,
			asset_type: asset.type,
			action_type: action,
			condition_group: { include_ids: [ postId ] },
			priority: 10,
			enabled: true,
		} );
		setNotice( __( 'Rule created for this page.', 'assetpilot' ) );
	};

	return (
		<>
			<PluginSidebarMoreMenuItem target="assetpilot-sidebar">
				{ __( 'AssetPilot', 'assetpilot' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="assetpilot-sidebar"
				title={ __( 'AssetPilot', 'assetpilot' ) }
				icon="performance"
			>
				<PanelBody title={ __( 'Page Assets', 'assetpilot' ) }>
					{ notice && (
						<Notice status="success" isDismissible onRemove={ () => setNotice( '' ) }>
							{ notice }
						</Notice>
					) }
					{ loading ? (
						<Spinner />
					) : (
						<>
							<SelectControl
								label={ __( 'Asset', 'assetpilot' ) }
								value={ selected }
								options={ [
									{ label: __( 'Select…', 'assetpilot' ), value: '' },
									...assets.map( ( a ) => ( {
										label: `${ a.handle } (${ a.type })`,
										value: a.handle,
									} ) ),
								] }
								onChange={ setSelected }
							/>
							<SelectControl
								label={ __( 'Action', 'assetpilot' ) }
								value={ action }
								options={ actionOptions }
								onChange={ setAction }
								disabled={ ! selectedAsset }
							/>
							<DependencyWarning
								warnings={ depWarning?.warnings }
								dependents={ depWarning?.dependents }
							/>
							<Button variant="primary" onClick={ handleApply } disabled={ ! selected || ! postId }>
								{ __( 'Apply to this page', 'assetpilot' ) }
							</Button>
						</>
					) }
				</PanelBody>
			</PluginSidebar>
		</>
	);
}
