/**
 * Asset recommendations screen.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, TextControl, Spinner, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { fetchRecommendations } from '../api';

const TYPE_LABELS = {
	large_asset: __( 'Large asset', 'assetpilot' ),
	render_blocking: __( 'Render blocking', 'assetpilot' ),
	duplicate_library: __( 'Duplicate library', 'assetpilot' ),
	low_usage: __( 'Low usage', 'assetpilot' ),
};

const CONFIDENCE_LABELS = {
	high: __( 'High confidence', 'assetpilot' ),
	medium: __( 'Medium confidence', 'assetpilot' ),
	low: __( 'Low confidence', 'assetpilot' ),
};

const formatSize = ( bytes ) => {
	if ( ! bytes ) {
		return '';
	}
	if ( bytes >= 1024 * 1024 ) {
		return `${ ( bytes / ( 1024 * 1024 ) ).toFixed( 1 ) } MB`;
	}
	return `${ ( bytes / 1024 ).toFixed( 1 ) } KB`;
};

const getInitialParams = () => {
	const params = new URLSearchParams( window.location.search );
	return {
		scanUrl:
			params.get( 'scan_url' ) ||
			params.get( 'page_url' ) ||
			window.assetpilotAdmin?.homeUrl ||
			'',
		scanId: parseInt( params.get( 'scan_id' ) || '0', 10 ) || 0,
	};
};

export const buildCreateRuleUrl = ( { handle, type, action, pageUrl } ) => {
	if ( ! handle || ! type ) {
		return '#';
	}

	const base =
		window.assetpilotAdmin?.createPageUrl ||
		`${ window.assetpilotAdmin?.adminUrl || '/wp-admin/' }admin.php?page=assetpilot-create`;
	const url = new URL( base, window.location.origin );
	url.searchParams.set( 'handle', handle );
	url.searchParams.set( 'type', type );
	if ( action ) {
		url.searchParams.set( 'action', action );
	}
	if ( pageUrl ) {
		url.searchParams.set( 'page_url', pageUrl );
	}
	return url.toString();
};

export default function Recommendations() {
	const initial = getInitialParams();
	const [ scanUrl, setScanUrl ] = useState( initial.scanUrl );
	const [ scanId ] = useState( initial.scanId );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );

	const load = useCallback( async () => {
		setLoading( true );
		setError( '' );
		try {
			const result = await fetchRecommendations( {
				scan_url: scanUrl,
				...( scanId ? { scan_id: scanId } : {} ),
			} );
			setData( result );
		} catch ( err ) {
			setData( null );
			setError( err?.message || __( 'Could not load recommendations.', 'assetpilot' ) );
		} finally {
			setLoading( false );
		}
	}, [ scanUrl, scanId ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const items = data?.recommendations || [];
	const meta = data?.meta || {};

	return (
		<div className="assetpilot-recommendations">
			<p className="assetpilot-hint">
				{ __(
					'Suggestions are based on scan data and history. Nothing is applied automatically — review each item and create a rule when ready.',
					'assetpilot'
				) }
			</p>

			<div className="assetpilot-toolbar assetpilot-recommendations__toolbar">
				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Scan URL', 'assetpilot' ) }
					value={ scanUrl }
					onChange={ setScanUrl }
					placeholder={ window.assetpilotAdmin?.homeUrl || 'https://example.com/' }
					className="assetpilot-recommendations__url"
				/>
				<Button variant="primary" onClick={ load } disabled={ loading }>
					{ __( 'Analyze', 'assetpilot' ) }
				</Button>
			</div>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ loading && (
				<div className="assetpilot-loading-panel">
					<Spinner />
					<p>{ __( 'Analyzing assets…', 'assetpilot' ) }</p>
				</div>
			) }

			{ ! loading && data && (
				<>
					<div className="assetpilot-summary assetpilot-recommendations__summary">
						<div className="assetpilot-summary__stat">
							<span className="assetpilot-summary__value">{ items.length }</span>
							<span className="assetpilot-summary__label">
								{ __( 'Recommendations', 'assetpilot' ) }
							</span>
						</div>
						<div className="assetpilot-summary__stat">
							<span className="assetpilot-summary__value">{ meta.asset_count ?? 0 }</span>
							<span className="assetpilot-summary__label">
								{ __( 'Assets scanned', 'assetpilot' ) }
							</span>
						</div>
						<div className="assetpilot-summary__stat">
							<span className="assetpilot-summary__value">{ meta.distinct_scans ?? 0 }</span>
							<span className="assetpilot-summary__label">
								{ __( 'URLs in scan history', 'assetpilot' ) }
							</span>
						</div>
					</div>

					{ items.length === 0 ? (
						<div className="assetpilot-card assetpilot-recommendations__empty">
							<p>
								{ __(
									'No recommendations for this page. Try another URL or save more scans in Scan History for low-usage suggestions.',
									'assetpilot'
								) }
							</p>
						</div>
					) : (
						<ul className="assetpilot-recommendation-list">
							{ items.map( ( item ) => (
								<li key={ item.id } className="assetpilot-card assetpilot-recommendation-card">
									<div className="assetpilot-recommendation-card__head">
										<div>
											<h3 className="assetpilot-recommendation-card__title">
												{ item.title || TYPE_LABELS[ item.type ] || item.type }
											</h3>
											<div className="assetpilot-recommendation-card__tags">
												<span className="assetpilot-badge assetpilot-badge--muted">
													{ TYPE_LABELS[ item.type ] || item.type }
												</span>
												{ item.handle && (
													<code className="assetpilot-handle">{ item.handle }</code>
												) }
												{ item.asset_type && (
													<span
														className={ `assetpilot-badge assetpilot-badge--${ item.asset_type }` }
													>
														{ item.asset_type }
													</span>
												) }
												{ item.size > 0 && (
													<span className="assetpilot-badge assetpilot-badge--muted">
														{ formatSize( item.size ) }
													</span>
												) }
											</div>
										</div>
										<span
											className={ `assetpilot-badge assetpilot-badge--confidence assetpilot-badge--confidence-${ item.confidence || 'medium' }` }
										>
											{ CONFIDENCE_LABELS[ item.confidence ] ||
												item.confidence }
										</span>
									</div>
									<p className="assetpilot-recommendation-card__reason">{ item.reason }</p>
									<div className="assetpilot-recommendation-card__actions">
										<span className="assetpilot-recommendation-card__suggested">
											{ sprintf(
												/* translators: %s: action name */
												__( 'Suggested: %s', 'assetpilot' ),
												item.suggested_action
											) }
										</span>
										{ item.type === 'duplicate_library' &&
										Array.isArray( item.related_handles ) ? (
											item.related_handles.map( ( handle ) => (
												<Button
													key={ handle }
													variant="secondary"
													href={ buildCreateRuleUrl( {
														handle,
														type: item.asset_type || 'script',
														action: item.suggested_action,
														pageUrl: data.scan_url,
													} ) }
												>
													{ sprintf(
														/* translators: %s: handle */
														__( 'Rule for %s', 'assetpilot' ),
														handle
													) }
												</Button>
											) )
										) : (
											<Button
												variant="primary"
												href={ buildCreateRuleUrl( {
													handle: item.handle,
													type: item.asset_type,
													action: item.suggested_action,
													pageUrl: data.scan_url,
												} ) }
											>
												{ __( 'Create rule', 'assetpilot' ) }
											</Button>
										) }
									</div>
								</li>
							) ) }
						</ul>
					) }
				</>
			) }
		</div>
	);
}
