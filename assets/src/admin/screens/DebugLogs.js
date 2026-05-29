/**
 * Debug logs viewer with filters.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner, Notice, TextControl, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { fetchLogs, clearLogs } from '../api';

const SEVERITY_OPTIONS = [
	{ label: __( 'All severities', 'assetpilot' ), value: '' },
	{ label: __( 'Debug', 'assetpilot' ), value: 'debug' },
	{ label: __( 'Info', 'assetpilot' ), value: 'info' },
	{ label: __( 'Warning', 'assetpilot' ), value: 'warning' },
	{ label: __( 'Error', 'assetpilot' ), value: 'error' },
];

function formatDate( value ) {
	if ( ! value ) {
		return '—';
	}
	try {
		return new Date( value.replace( ' ', 'T' ) + 'Z' ).toLocaleString();
	} catch {
		return value;
	}
}

function severityClass( severity ) {
	return `assetpilot-log-severity assetpilot-log-severity--${ severity || 'info' }`;
}

export default function DebugLogs() {
	const [ logs, setLogs ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ types, setTypes ] = useState( [] );
	const [ logCount, setLogCount ] = useState( 0 );
	const [ debugEnabled, setDebugEnabled ] = useState( true );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ clearing, setClearing ] = useState( false );

	const [ severity, setSeverity ] = useState( '' );
	const [ type, setType ] = useState( '' );
	const [ ruleId, setRuleId ] = useState( '' );
	const [ assetHandle, setAssetHandle ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ dateFrom, setDateFrom ] = useState( '' );
	const [ dateTo, setDateTo ] = useState( '' );

	const perPage = 50;
	const settingsUrl = window.assetpilotAdmin?.settingsPageUrl || 'admin.php?page=assetpilot-settings';

	const load = useCallback( () => {
		setLoading( true );
		setError( '' );
		fetchLogs( {
			page,
			per_page: perPage,
			severity: severity || undefined,
			type: type || undefined,
			rule_id: ruleId || undefined,
			asset_handle: assetHandle || undefined,
			search: search || undefined,
			date_from: dateFrom || undefined,
			date_to: dateTo || undefined,
		} )
			.then( ( res ) => {
				setLogs( res.logs || [] );
				setTotal( res.total || 0 );
				setTypes( res.types || [] );
				setLogCount( res.log_count ?? 0 );
				setDebugEnabled( res.debug_enabled !== false );
			} )
			.catch( ( err ) => {
				setError( err?.message || __( 'Failed to load logs.', 'assetpilot' ) );
				setLogs( [] );
			} )
			.finally( () => setLoading( false ) );
	}, [ page, severity, type, ruleId, assetHandle, search, dateFrom, dateTo ] );

	useEffect( load, [ load ] );

	const typeOptions = [
		{ label: __( 'All types', 'assetpilot' ), value: '' },
		...types.map( ( t ) => ( { label: t, value: t } ) ),
	];

	const handleClear = async () => {
		if ( ! window.confirm( __( 'Delete all debug logs?', 'assetpilot' ) ) ) {
			return;
		}
		setClearing( true );
		try {
			await clearLogs();
			setPage( 1 );
			load();
		} catch ( err ) {
			setError( err?.message || __( 'Failed to clear logs.', 'assetpilot' ) );
		} finally {
			setClearing( false );
		}
	};

	const totalPages = Math.max( 1, Math.ceil( total / perPage ) );

	if ( ! debugEnabled ) {
		return (
			<div className="assetpilot-debug-logs">
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Debug logging is disabled. Enable it in Settings to record matched rules, skips, validation issues, and verification results.',
						'assetpilot'
					) }{ ' ' }
					<a href={ settingsUrl }>{ __( 'Open Settings', 'assetpilot' ) }</a>
				</Notice>
			</div>
		);
	}

	return (
		<div className="assetpilot-debug-logs">
			<p className="description">
				{ sprintf(
					/* translators: 1: current log count, 2: max rows */
					__(
						'Showing runtime and admin events. Logs rotate automatically (max %2$s rows, 14-day retention). Stored: %1$s entries.',
						'assetpilot'
					),
					logCount,
					window.assetpilotAdmin?.logMaxRows || 5000
				) }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false } onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }

			<div className="assetpilot-debug-logs__filters assetpilot-rules-filters">
				<div className="assetpilot-rules-filters__field">
					<TextControl
						label={ __( 'Search', 'assetpilot' ) }
						value={ search }
						onChange={ ( v ) => {
							setSearch( v );
							setPage( 1 );
						} }
					/>
				</div>
				<div className="assetpilot-rules-filters__field">
					<SelectControl
						label={ __( 'Severity', 'assetpilot' ) }
						value={ severity }
						options={ SEVERITY_OPTIONS }
						onChange={ ( v ) => {
							setSeverity( v );
							setPage( 1 );
						} }
					/>
				</div>
				<div className="assetpilot-rules-filters__field">
					<SelectControl
						label={ __( 'Type', 'assetpilot' ) }
						value={ type }
						options={ typeOptions }
						onChange={ ( v ) => {
							setType( v );
							setPage( 1 );
						} }
					/>
				</div>
				<div className="assetpilot-rules-filters__field">
					<TextControl
						label={ __( 'Rule ID', 'assetpilot' ) }
						value={ ruleId }
						onChange={ ( v ) => {
							setRuleId( v.replace( /\D/g, '' ) );
							setPage( 1 );
						} }
					/>
				</div>
				<div className="assetpilot-rules-filters__field">
					<TextControl
						label={ __( 'Asset handle', 'assetpilot' ) }
						value={ assetHandle }
						onChange={ ( v ) => {
							setAssetHandle( v );
							setPage( 1 );
						} }
					/>
				</div>
				<div className="assetpilot-rules-filters__field">
					<TextControl
						label={ __( 'From date', 'assetpilot' ) }
						type="date"
						value={ dateFrom }
						onChange={ ( v ) => {
							setDateFrom( v );
							setPage( 1 );
						} }
					/>
				</div>
				<div className="assetpilot-rules-filters__field">
					<TextControl
						label={ __( 'To date', 'assetpilot' ) }
						type="date"
						value={ dateTo }
						onChange={ ( v ) => {
							setDateTo( v );
							setPage( 1 );
						} }
					/>
				</div>
			</div>

			<div className="assetpilot-debug-logs__toolbar">
				<Button variant="secondary" onClick={ load } disabled={ loading }>
					{ __( 'Refresh', 'assetpilot' ) }
				</Button>
				<Button variant="secondary" isDestructive onClick={ handleClear } disabled={ clearing || logCount === 0 }>
					{ clearing ? <Spinner /> : __( 'Clear all logs', 'assetpilot' ) }
				</Button>
			</div>

			{ loading ? (
				<Spinner />
			) : logs.length === 0 ? (
				<p>{ __( 'No log entries match your filters.', 'assetpilot' ) }</p>
			) : (
				<table className="wp-list-table widefat fixed striped assetpilot-debug-logs__table">
					<thead>
						<tr>
							<th>{ __( 'Time', 'assetpilot' ) }</th>
							<th>{ __( 'Severity', 'assetpilot' ) }</th>
							<th>{ __( 'Type', 'assetpilot' ) }</th>
							<th>{ __( 'Message', 'assetpilot' ) }</th>
							<th>{ __( 'Rule', 'assetpilot' ) }</th>
							<th>{ __( 'Asset', 'assetpilot' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ logs.map( ( log ) => (
							<tr key={ log.id }>
								<td className="assetpilot-debug-logs__time">{ formatDate( log.logged_at ) }</td>
								<td>
									<span className={ severityClass( log.severity ) }>{ log.severity }</span>
								</td>
								<td><code>{ log.type }</code></td>
								<td className="assetpilot-debug-logs__message">
									{ log.message }
									{ log.context && Object.keys( log.context ).length > 0 && (
										<details className="assetpilot-debug-logs__context">
											<summary>{ __( 'Context', 'assetpilot' ) }</summary>
											<pre>{ JSON.stringify( log.context, null, 2 ) }</pre>
										</details>
									) }
								</td>
								<td>{ log.rule_id || '—' }</td>
								<td>{ log.asset_handle || '—' }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ totalPages > 1 && (
				<div className="assetpilot-pagination">
					<Button disabled={ page <= 1 } onClick={ () => setPage( ( p ) => p - 1 ) }>
						{ __( 'Previous', 'assetpilot' ) }
					</Button>
					<span>
						{ sprintf(
							/* translators: 1: current page, 2: total pages */
							__( 'Page %1$d of %2$d', 'assetpilot' ),
							page,
							totalPages
						) }
					</span>
					<Button disabled={ page >= totalPages } onClick={ () => setPage( ( p ) => p + 1 ) }>
						{ __( 'Next', 'assetpilot' ) }
					</Button>
				</div>
			) }
		</div>
	);
}
