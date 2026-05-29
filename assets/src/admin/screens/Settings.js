/**
 * Settings screen.
 */
import { useState, useEffect } from '@wordpress/element';
import { Card, CardBody, CardHeader, ToggleControl, Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { fetchSettings, updateSettings } from '../api';

export default function Settings() {
	const [ settings, setSettings ] = useState( { debug_logging: false } );
	const [ loading, setLoading ] = useState( true );
	const [ saved, setSaved ] = useState( false );
	const logsUrl = window.assetpilotAdmin?.logsPageUrl || 'admin.php?page=assetpilot-logs';

	useEffect( () => {
		fetchSettings()
			.then( setSettings )
			.finally( () => setLoading( false ) );
	}, [] );

	const handleSave = async () => {
		await updateSettings( settings );
		setSaved( true );
		setTimeout( () => setSaved( false ), 3000 );
	};

	if ( loading ) {
		return null;
	}

	const safeModeActive = !! window.assetpilotAdmin?.safeModeActive;
	const runtimeAutoSuspended = !! window.assetpilotAdmin?.runtimeAutoSuspended;
	const runtimeDisabled = !! window.assetpilotAdmin?.runtimeDisabled;
	const suspendInfo = window.assetpilotAdmin?.runtimeSuspendInfo;
	const resumeRuntimeUrl = window.assetpilotAdmin?.resumeRuntimeUrl || '#';
	const recoveryUrl = window.assetpilotAdmin?.safeModeRecoveryUrl || '#';
	const safeModeToggleUrl = safeModeActive
		? window.assetpilotAdmin?.safeModeDisableUrl || window.assetpilotAdmin?.safeModeUrl || '#'
		: window.assetpilotAdmin?.safeModeEnableUrl || window.assetpilotAdmin?.safeModeUrl || '#';

	return (
		<div className="assetpilot-settings">
			{ saved && (
				<Notice status="success" isDismissible={ false }>
					{ __( 'Settings saved.', 'assetpilot' ) }
				</Notice>
			) }

			<Card>
				<CardHeader><h2>{ __( 'Data retention', 'assetpilot' ) }</h2></CardHeader>
				<CardBody>
					<p className="description">
						{ settings.scan_history_count != null
							? sprintf(
								/* translators: 1: stored count, 2: max rows, 3: retention days */
								__(
									'Scan history: %1$d snapshots stored (max %2$d, %3$d-day rotation). Older scans are removed automatically after each new scan or when you open Scan History.',
									'assetpilot'
								),
								settings.scan_history_count,
								settings.scan_history_max_rows || 200,
								settings.scan_history_retention_days || 90
							)
							: __(
								'Scan history rotates automatically (default: 200 snapshots or 90 days).',
								'assetpilot'
							) }
					</p>
				</CardBody>
			</Card>

			<Card>
				<CardHeader><h2>{ __( 'Debug', 'assetpilot' ) }</h2></CardHeader>
				<CardBody>
					<ToggleControl
						label={ __( 'Enable debug logging', 'assetpilot' ) }
						help={ __(
							'Records matched/skipped rules, validation conflicts, verification results, and runtime errors to the database (also mirrors to WP_DEBUG_LOG when WP_DEBUG is on).',
							'assetpilot'
						) }
						checked={ settings.debug_logging }
						onChange={ ( debug_logging ) => setSettings( { ...settings, debug_logging } ) }
					/>
					{ settings.debug_logging && (
						<p className="description">
							{ settings.log_count != null
								? sprintf(
									/* translators: 1: log count, 2: max rows */
									__( 'Stored log entries: %1$d (max %2$d, 14-day rotation).', 'assetpilot' ),
									settings.log_count,
									settings.log_max_rows || 5000
								)
								: null }{ ' ' }
							<a href={ logsUrl }>{ __( 'View debug logs', 'assetpilot' ) }</a>
						</p>
					) }
					<Button variant="primary" onClick={ handleSave } style={ { marginTop: '12px' } }>
						{ __( 'Save Settings', 'assetpilot' ) }
					</Button>
				</CardBody>
			</Card>

			<Card>
				<CardHeader><h2>{ __( 'Safe Mode & Recovery', 'assetpilot' ) }</h2></CardHeader>
				<CardBody>
					<p>
						{ __(
							'Safe Mode disables frontend asset modifications for the entire site (not just your browser). The admin UI, REST API, and asset scanning keep working.',
							'assetpilot'
						) }
					</p>
					<p className="description">
						{ __( 'Recovery URL (while logged in as admin):', 'assetpilot' ) }{ ' ' }
						<code>{ recoveryUrl }</code>
					</p>

					{ runtimeAutoSuspended && (
						<Notice className="assetpilot-notice" status="error" isDismissible={ false }>
							{ suspendInfo?.failure_count
								? sprintf(
									/* translators: %d: number of detected errors */
									__(
										'Runtime rules were paused automatically after %d frontend errors. Modifications stay off until the timer expires or you resume.',
										'assetpilot'
									),
									suspendInfo.failure_count
								)
								: __(
									'Runtime rules were paused automatically after repeated frontend errors.',
									'assetpilot'
								) }
						</Notice>
					) }

					{ safeModeActive && (
						<Notice className="assetpilot-notice" status="warning" isDismissible={ false }>
							{ __( 'Manual Safe Mode is active. Runtime asset modifications are disabled.', 'assetpilot' ) }
						</Notice>
					) }

					{ runtimeDisabled && ! safeModeActive && ! runtimeAutoSuspended && (
						<Notice className="assetpilot-notice" status="warning" isDismissible={ false }>
							{ __( 'Runtime modifications are currently disabled.', 'assetpilot' ) }
						</Notice>
					) }

					<div className="assetpilot-settings__actions">
						<Button
							variant={ safeModeActive ? 'primary' : 'secondary' }
							isDestructive={ safeModeActive }
							href={ safeModeToggleUrl }
						>
							{ safeModeActive
								? __( 'Disable Safe Mode', 'assetpilot' )
								: __( 'Enable Safe Mode', 'assetpilot' ) }
						</Button>
						{ runtimeAutoSuspended && (
							<Button variant="primary" href={ resumeRuntimeUrl }>
								{ __( 'Resume runtime now', 'assetpilot' ) }
							</Button>
						) }
					</div>
				</CardBody>
			</Card>
		</div>
	);
}
