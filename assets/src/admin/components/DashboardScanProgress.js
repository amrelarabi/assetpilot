/**
 * Homepage scan progress for the dashboard first load.
 */
import { useState, useEffect } from '@wordpress/element';
import { ProgressBar, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const STEP_MS = 2800;

const getSteps = () => [
	{
		id: 'prepare',
		label: __( 'Preparing scan environment', 'assetpilot' ),
	},
	{
		id: 'render',
		label: __( 'Rendering your homepage', 'assetpilot' ),
	},
	{
		id: 'collect',
		label: __( 'Collecting scripts and styles', 'assetpilot' ),
	},
	{
		id: 'measure',
		label: __( 'Measuring asset sizes', 'assetpilot' ),
	},
	{
		id: 'finalize',
		label: __( 'Building dashboard summary', 'assetpilot' ),
	},
];

export default function DashboardScanProgress( { scanUrl, scanning, error = '' } ) {
	const steps = getSteps();
	const [ activeStep, setActiveStep ] = useState( 0 );
	const [ progress, setProgress ] = useState( 8 );

	useEffect( () => {
		if ( ! scanning ) {
			setActiveStep( steps.length );
			setProgress( 100 );
			return undefined;
		}

		setActiveStep( 0 );
		setProgress( 8 );

		const timers = steps.map( ( _, index ) => {
			if ( 0 === index ) {
				return null;
			}
			return window.setTimeout( () => {
				setActiveStep( index );
				setProgress( Math.min( 92, Math.round( ( index / steps.length ) * 100 ) ) );
			}, index * STEP_MS );
		} );

		return () => {
			timers.forEach( ( timer ) => {
				if ( timer ) {
					window.clearTimeout( timer );
				}
			} );
		};
	}, [ scanning, steps.length ] );

	if ( ! scanning && ! error ) {
		return null;
	}

	return (
		<div
			className={ `assetpilot-dashboard-scan${ error ? ' assetpilot-dashboard-scan--error' : '' }` }
			role="status"
			aria-live="polite"
			aria-busy={ scanning ? 'true' : 'false' }
		>
			<div className="assetpilot-dashboard-scan__header">
				{ scanning ? <Spinner /> : null }
				<div>
					<h2 className="assetpilot-dashboard-scan__title">
						{ error
							? __( 'Homepage scan failed', 'assetpilot' )
							: __( 'Scanning your homepage', 'assetpilot' ) }
					</h2>
					<p className="assetpilot-dashboard-scan__lead">
						{ error
							? error
							: __(
								'AssetPilot is analyzing scripts and styles on your site. This first scan can take a minute on larger sites.',
								'assetpilot'
							) }
					</p>
				</div>
			</div>

			{ ! error && scanUrl && (
				<p className="assetpilot-dashboard-scan__url">
					<span className="assetpilot-dashboard-scan__url-label">
						{ __( 'Scan URL', 'assetpilot' ) }
					</span>
					<code>{ scanUrl }</code>
				</p>
			) }

			{ ! error && (
				<>
					<ProgressBar
						className="assetpilot-dashboard-scan__bar"
						value={ progress }
						color={ scanning ? '#2271b1' : '#00a32a' }
					/>
					<ol className="assetpilot-dashboard-scan__steps">
						{ steps.map( ( step, index ) => {
							const done = index < activeStep;
							const active = scanning && index === activeStep;
							return (
								<li
									key={ step.id }
									className={ [
										'assetpilot-dashboard-scan__step',
										done ? 'is-done' : '',
										active ? 'is-active' : '',
									]
										.filter( Boolean )
										.join( ' ' ) }
								>
									<span className="assetpilot-dashboard-scan__step-icon" aria-hidden>
										{ done ? '✓' : active ? '…' : index + 1 }
									</span>
									<span>{ step.label }</span>
								</li>
							);
						} ) }
					</ol>
				</>
			) }
		</div>
	);
}
