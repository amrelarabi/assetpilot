/**
 * Rules list screen with search, filters, bulk actions, and pagination.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner, ToggleControl, Notice, TextControl, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { fetchRules, deleteRule, duplicateRule, updateRule, verifyRules, bulkRulesAction } from '../api';
import VerificationBadge from '../components/VerificationBadge';
import { assetsExplorerUrl } from '../navigationUtils';
import { ruleAssetDisplay } from '../utils/ruleAssetUtils';

const CONDITION_LABELS = {
	global: __( 'Entire site', 'assetpilot' ),
	url: __( 'URL', 'assetpilot' ),
	query: __( 'Query string', 'assetpilot' ),
	role: __( 'User role', 'assetpilot' ),
	device: __( 'Device', 'assetpilot' ),
	auth: __( 'Auth', 'assetpilot' ),
	singular: __( 'Singular', 'assetpilot' ),
	archive: __( 'Archive', 'assetpilot' ),
	woocommerce: __( 'WooCommerce', 'assetpilot' ),
	post_type_archive: __( 'Post type archive', 'assetpilot' ),
	scan_page: __( 'Scanned page URL', 'assetpilot' ),
	conditional: __( 'Conditional', 'assetpilot' ),
};

function adminPageUrl( page ) {
	const base = window.assetpilotAdmin?.adminUrl || '/wp-admin/';
	return `${ base }admin.php?page=${ page }`;
}

function RulesEmptyState( { hasFilters } ) {
	const assetsUrl = assetsExplorerUrl();

	return (
		<div className="assetpilot-empty-state assetpilot-empty-state--rules">
			<div className="assetpilot-empty-state__icon" aria-hidden="true">
				<span className="dashicons dashicons-performance" />
			</div>
			<h2 className="assetpilot-empty-state__title">
				{ hasFilters ? __( 'No rules match your filters', 'assetpilot' ) : __( 'No rules yet', 'assetpilot' ) }
			</h2>
			{ ! hasFilters && (
				<>
					<p className="assetpilot-empty-state__lead">
						{ __(
							'Rules tell AssetPilot what to do with assets on your site — disable bloat, defer scripts, preload fonts, and more.',
							'assetpilot'
						) }
					</p>
					<ol className="assetpilot-empty-state__steps">
						<li>{ __( 'Scan a page in Assets Explorer to see what loads', 'assetpilot' ) }</li>
						<li>{ __( 'Pick an asset and choose an action (disable, defer, preload…)', 'assetpilot' ) }</li>
						<li>{ __( 'Set where the rule applies, then save', 'assetpilot' ) }</li>
					</ol>
				</>
			) }
			<div className="assetpilot-empty-state__actions">
				<Button variant="primary" href={ assetsUrl }>
					{ __( 'Browse assets', 'assetpilot' ) }
				</Button>
			</div>
		</div>
	);
}

function verificationFromRules( list ) {
	const map = {};
	list.forEach( ( rule ) => {
		if ( rule.verification?.status ) {
			map[ rule.id ] = rule.verification;
		}
	} );
	return map;
}

const defaultFilters = () => ( {
	search: '',
	action_type: '',
	asset_type: '',
	asset_handle: '',
	condition_type: '',
	enabled: '',
} );

const TEXT_FILTER_DEBOUNCE_MS = 400;

export default function RulesList( { onEdit } ) {
	const [ rules, setRules ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ filters, setFilters ] = useState( defaultFilters );
	const [ queryFilters, setQueryFilters ] = useState( defaultFilters );
	const [ loading, setLoading ] = useState( true );
	const [ selected, setSelected ] = useState( [] );
	const [ bulkBusy, setBulkBusy ] = useState( false );
	const [ showSavedNotice, setShowSavedNotice ] = useState(
		() => new URLSearchParams( window.location.search ).get( 'assetpilot_saved' ) === '1'
	);
	const [ verifyById, setVerifyById ] = useState( {} );
	const [ verifying, setVerifying ] = useState( false );
	const [ verifyError, setVerifyError ] = useState( '' );
	const [ listError, setListError ] = useState( '' );

	const perPage = 20;
	const hasFilters = Object.values( filters ).some( ( v ) => v !== '' );

	const runVerification = useCallback( () => {
		setVerifying( true );
		setVerifyError( '' );
		verifyRules( '' )
			.then( ( res ) => {
				if ( res.error ) {
					setVerifyError( res.error );
					return;
				}
				setVerifyById( res.by_id || {} );
				setRules( ( prev ) =>
					prev.map( ( rule ) => ( {
						...rule,
						verification: res.by_id?.[ rule.id ] ?? rule.verification,
					} ) )
				);
			} )
			.catch( ( err ) => {
				setVerifyError( err?.message || __( 'Verification failed.', 'assetpilot' ) );
			} )
			.finally( () => setVerifying( false ) );
	}, [] );

	const load = useCallback( () => {
		setLoading( true );
		setListError( '' );
		fetchRules( { page, per_page: perPage, ...queryFilters } )
			.then( ( res ) => {
				const list = res.rules || [];
				setRules( list );
				setTotal( res.total || 0 );
				setVerifyById( verificationFromRules( list ) );
				setSelected( [] );
			} )
			.catch( ( err ) => {
				setListError( err?.message || __( 'Failed to load rules.', 'assetpilot' ) );
				setRules( [] );
				setTotal( 0 );
			} )
			.finally( () => setLoading( false ) );
	}, [ page, queryFilters ] );

	useEffect( load, [ load ] );

	useEffect( () => {
		const timer = window.setTimeout( () => {
			let changed = false;
			setQueryFilters( ( prev ) => {
				if (
					prev.search === filters.search &&
					prev.asset_handle === filters.asset_handle
				) {
					return prev;
				}
				changed = true;
				return {
					...prev,
					search: filters.search,
					asset_handle: filters.asset_handle,
				};
			} );
			if ( changed ) {
				setPage( 1 );
			}
		}, TEXT_FILTER_DEBOUNCE_MS );

		return () => window.clearTimeout( timer );
	}, [ filters.search, filters.asset_handle ] );

	useEffect( () => {
		if ( ! showSavedNotice ) {
			return;
		}
		const url = new URL( window.location.href );
		url.searchParams.delete( 'assetpilot_saved' );
		url.searchParams.delete( 'rule_id' );
		window.history.replaceState( {}, '', url.toString() );
	}, [ showSavedNotice ] );

	const patchFilter = ( key, value ) => {
		setFilters( ( prev ) => ( { ...prev, [ key ]: value } ) );
		if ( 'search' !== key && 'asset_handle' !== key ) {
			setQueryFilters( ( prev ) => ( { ...prev, [ key ]: value } ) );
			setPage( 1 );
		}
	};

	const toggleSelect = ( id ) => {
		setSelected( ( prev ) =>
			prev.includes( id ) ? prev.filter( ( x ) => x !== id ) : [ ...prev, id ]
		);
	};

	const toggleSelectAll = () => {
		if ( selected.length === rules.length ) {
			setSelected( [] );
		} else {
			setSelected( rules.map( ( r ) => r.id ) );
		}
	};

	const runBulk = async ( action ) => {
		if ( ! selected.length ) {
			return;
		}
		if ( action === 'delete' && ! window.confirm( __( 'Delete selected rules?', 'assetpilot' ) ) ) {
			return;
		}
		setBulkBusy( true );
		try {
			await bulkRulesAction( action, selected );
			load();
		} catch ( err ) {
			setListError( err?.message || __( 'Bulk action failed.', 'assetpilot' ) );
		} finally {
			setBulkBusy( false );
		}
	};

	const handleDelete = async ( id ) => {
		if ( ! window.confirm( __( 'Delete this rule?', 'assetpilot' ) ) ) {
			return;
		}
		await deleteRule( id );
		load();
	};

	const handleDuplicate = async ( id ) => {
		await duplicateRule( id );
		load();
	};

	const handleToggle = async ( rule ) => {
		const nextEnabled = ! rule.enabled;
		try {
			const updated = await updateRule( rule.id, { enabled: nextEnabled } );
			setRules( ( prev ) =>
				prev.map( ( r ) =>
					r.id === rule.id
						? { ...r, enabled: nextEnabled, verification: updated?.verification ?? r.verification }
						: r
				)
			);
			if ( updated?.verification ) {
				setVerifyById( ( prev ) => ( { ...prev, [ rule.id ]: updated.verification } ) );
			}
		} catch ( err ) {
			setListError( err?.message || __( 'Could not update rule.', 'assetpilot' ) );
		}
	};

	const totalPages = Math.max( 1, Math.ceil( total / perPage ) );
	const hasStoredVerification = Object.keys( verifyById ).length > 0;

	if ( loading && rules.length === 0 && total === 0 ) {
		return (
			<div className="assetpilot-loading-panel">
				<Spinner />
				<p>{ __( 'Loading rules…', 'assetpilot' ) }</p>
			</div>
		);
	}

	if ( ! loading && total === 0 && ! hasFilters ) {
		return (
			<div className="assetpilot-rules-list">
				<RulesEmptyState hasFilters={ false } />
			</div>
		);
	}

	return (
		<div className="assetpilot-rules-list">
			{ showSavedNotice && (
				<div className="assetpilot-rules-list__banner">
					<Notice
						className="assetpilot-notice"
						status="success"
						isDismissible
						onRemove={ () => setShowSavedNotice( false ) }
					>
						{ __( 'Rule saved successfully.', 'assetpilot' ) }
					</Notice>
				</div>
			) }

			<div className="assetpilot-rules-list__header">
				<Button variant="primary" href={ assetsExplorerUrl() }>
					{ __( 'Add rule from assets', 'assetpilot' ) }
				</Button>
			</div>

			<div className="assetpilot-rules-filters">
				<div className="assetpilot-rules-filters__field assetpilot-rules-filters__field--search">
					<TextControl
						label={ __( 'Search', 'assetpilot' ) }
						value={ filters.search }
						onChange={ ( val ) => patchFilter( 'search', val ) }
						placeholder={ __( 'Label, handle, action…', 'assetpilot' ) }
						__nextHasNoMarginBottom
					/>
				</div>
				<div className="assetpilot-rules-filters__field">
					<TextControl
						label={ __( 'Asset handle', 'assetpilot' ) }
						value={ filters.asset_handle }
						onChange={ ( val ) => patchFilter( 'asset_handle', val ) }
						placeholder={ __( 'Partial handle match', 'assetpilot' ) }
						__nextHasNoMarginBottom
					/>
				</div>
				<div className="assetpilot-rules-filters__field">
				<SelectControl
					label={ __( 'Action', 'assetpilot' ) }
					value={ filters.action_type }
					options={ [
						{ label: __( 'All actions', 'assetpilot' ), value: '' },
						{ label: 'disable', value: 'disable' },
						{ label: 'defer', value: 'defer' },
						{ label: 'async', value: 'async' },
						{ label: 'preload', value: 'preload' },
						{ label: 'fetchpriority', value: 'fetchpriority' },
					] }
					onChange={ ( val ) => patchFilter( 'action_type', val ) }
					__nextHasNoMarginBottom
				/>
				</div>
				<div className="assetpilot-rules-filters__field">
				<SelectControl
					label={ __( 'Asset type', 'assetpilot' ) }
					value={ filters.asset_type }
					options={ [
						{ label: __( 'All types', 'assetpilot' ), value: '' },
						{ label: 'script', value: 'script' },
						{ label: 'style', value: 'style' },
						{ label: 'image', value: 'image' },
						{ label: 'font', value: 'font' },
					] }
					onChange={ ( val ) => patchFilter( 'asset_type', val ) }
					__nextHasNoMarginBottom
				/>
				</div>
				<div className="assetpilot-rules-filters__field">
				<SelectControl
					label={ __( 'Condition', 'assetpilot' ) }
					value={ filters.condition_type }
					options={ [
						{ label: __( 'All conditions', 'assetpilot' ), value: '' },
						{ label: CONDITION_LABELS.global, value: 'global' },
						{ label: CONDITION_LABELS.singular, value: 'singular' },
						{ label: CONDITION_LABELS.url, value: 'url' },
						{ label: CONDITION_LABELS.scan_page, value: 'scan_page' },
						{ label: CONDITION_LABELS.query, value: 'query' },
						{ label: CONDITION_LABELS.role, value: 'role' },
						{ label: CONDITION_LABELS.device, value: 'device' },
						{ label: CONDITION_LABELS.auth, value: 'auth' },
						{ label: CONDITION_LABELS.archive, value: 'archive' },
						{ label: CONDITION_LABELS.woocommerce, value: 'woocommerce' },
					] }
					onChange={ ( val ) => patchFilter( 'condition_type', val ) }
					__nextHasNoMarginBottom
				/>
				</div>
				<div className="assetpilot-rules-filters__field">
				<SelectControl
					label={ __( 'Status', 'assetpilot' ) }
					value={ filters.enabled }
					options={ [
						{ label: __( 'All', 'assetpilot' ), value: '' },
						{ label: __( 'Enabled', 'assetpilot' ), value: 'true' },
						{ label: __( 'Disabled', 'assetpilot' ), value: 'false' },
					] }
					onChange={ ( val ) => patchFilter( 'enabled', val ) }
					__nextHasNoMarginBottom
				/>
				</div>
			</div>

			<div className="assetpilot-rules-list__toolbar">
				<p className="assetpilot-rules-list__verify-hint">
					{ sprintf(
						/* translators: %d: total rule count */
						__( '%d rules total', 'assetpilot' ),
						total
					) }
					{ hasStoredVerification && (
						<>
							{ ' · ' }
							{ __(
								'Verification uses each rule’s target page. Re-run after site changes.',
								'assetpilot'
							) }
						</>
					) }
				</p>
				<Button variant="secondary" onClick={ runVerification } disabled={ verifying }>
					{ verifying ? __( 'Verifying…', 'assetpilot' ) : __( 'Re-verify all', 'assetpilot' ) }
				</Button>
			</div>

			{ selected.length > 0 && (
				<div className="assetpilot-rules-bulk">
					<span>
						{ sprintf(
							/* translators: %d: selected count */
							__( '%d selected', 'assetpilot' ),
							selected.length
						) }
					</span>
					<Button variant="secondary" onClick={ () => runBulk( 'enable' ) } disabled={ bulkBusy }>
						{ __( 'Enable', 'assetpilot' ) }
					</Button>
					<Button variant="secondary" onClick={ () => runBulk( 'disable' ) } disabled={ bulkBusy }>
						{ __( 'Disable', 'assetpilot' ) }
					</Button>
					<Button variant="secondary" isDestructive onClick={ () => runBulk( 'delete' ) } disabled={ bulkBusy }>
						{ __( 'Delete', 'assetpilot' ) }
					</Button>
				</div>
			) }

			{ listError && (
				<Notice className="assetpilot-notice" status="warning" isDismissible={ false }>
					{ listError }
				</Notice>
			) }
			{ verifyError && (
				<Notice className="assetpilot-notice" status="warning" isDismissible={ false }>
					{ verifyError }
				</Notice>
			) }

			{ ! loading && rules.length === 0 ? (
				<RulesEmptyState hasFilters={ hasFilters } />
			) : (
				<>
					<table className="wp-list-table widefat fixed striped assetpilot-rules-table">
						<thead>
							<tr>
								<td className="check-column">
									<input
										type="checkbox"
										checked={ rules.length > 0 && selected.length === rules.length }
										onChange={ toggleSelectAll }
										aria-label={ __( 'Select all on this page', 'assetpilot' ) }
									/>
								</td>
								<th>{ __( 'Label', 'assetpilot' ) }</th>
								<th>{ __( 'Asset', 'assetpilot' ) }</th>
								<th>{ __( 'Action', 'assetpilot' ) }</th>
								<th>{ __( 'Scope', 'assetpilot' ) }</th>
								<th>{ __( 'Verification', 'assetpilot' ) }</th>
								<th>{ __( 'Priority', 'assetpilot' ) }</th>
								<th>{ __( 'On', 'assetpilot' ) }</th>
								<th>{ __( 'Actions', 'assetpilot' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ rules.map( ( rule ) => (
								<tr key={ rule.id } className={ ! rule.enabled ? 'is-disabled' : '' }>
									<th scope="row" className="check-column">
										<input
											type="checkbox"
											checked={ selected.includes( rule.id ) }
											onChange={ () => toggleSelect( rule.id ) }
										/>
									</th>
									<td>
										<strong>{ rule.label || '—' }</strong>
										{ rule.notes && (
											<p className="assetpilot-rules-table__notes" title={ rule.notes }>
												{ rule.notes }
											</p>
										) }
									</td>
									<td>
										{ ( () => {
											const asset = ruleAssetDisplay( rule );
											return (
												<>
													<code title={ asset.title || undefined }>
														{ asset.label }
													</code>
													{ asset.isUrl && asset.url && (
														<a
															className="assetpilot-rules-table__asset-url"
															href={ asset.url }
															target="_blank"
															rel="noopener noreferrer"
															title={ asset.url }
														>
															{ __( 'URL', 'assetpilot' ) }
														</a>
													) }
													{ ! asset.isBulk && (
														<span className={ `assetpilot-badge assetpilot-badge--${ rule.asset_type }` }>
															{ rule.asset_type }
														</span>
													) }
													{ asset.isUrl && (
														<span className="assetpilot-badge assetpilot-badge--url">
															{ __( 'custom URL', 'assetpilot' ) }
														</span>
													) }
													{ asset.isBulk && (
														<span className="assetpilot-badge assetpilot-badge--bulk">
															{ __( 'bulk', 'assetpilot' ) }
														</span>
													) }
												</>
											);
										} )() }
									</td>
									<td>{ rule.action_type }</td>
									<td>
										{ CONDITION_LABELS[ rule.condition_scope ] || rule.condition_scope }
									</td>
									<td className="column-verify">
										{ verifying ? (
											<Spinner />
										) : (
											<VerificationBadge
												verification={ verifyById[ rule.id ] }
												showMessage
												showUrl
											/>
										) }
									</td>
									<td>{ rule.priority }</td>
									<td>
										<ToggleControl
											checked={ rule.enabled }
											onChange={ () => handleToggle( rule ) }
											__nextHasNoMarginBottom
										/>
									</td>
									<td className="assetpilot-rule-actions">
										<Button variant="link" onClick={ () => onEdit?.( rule ) }>
											{ __( 'Edit', 'assetpilot' ) }
										</Button>
										{ ' · ' }
										<Button variant="link" onClick={ () => handleDuplicate( rule.id ) }>
											{ __( 'Duplicate', 'assetpilot' ) }
										</Button>
										{ ' · ' }
										<Button variant="link" isDestructive onClick={ () => handleDelete( rule.id ) }>
											{ __( 'Delete', 'assetpilot' ) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>

					<div className="assetpilot-pagination">
						<Button variant="secondary" disabled={ page <= 1 || loading } onClick={ () => setPage( ( p ) => p - 1 ) }>
							{ __( 'Previous', 'assetpilot' ) }
						</Button>
						<span>
							{ sprintf( __( 'Page %1$d of %2$d', 'assetpilot' ), page, totalPages ) }
						</span>
						<Button
							variant="secondary"
							disabled={ page >= totalPages || loading }
							onClick={ () => setPage( ( p ) => p + 1 ) }
						>
							{ __( 'Next', 'assetpilot' ) }
						</Button>
					</div>
				</>
			) }
		</div>
	);
}
