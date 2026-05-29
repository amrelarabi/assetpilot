/**
 * Page Analyzer screen.
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { Button, TextControl, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { analyzePage } from '../api';
import { clearBulkRuleDraft } from '../bulkRuleSession';

const guessTypeFromUrl = ( fileUrl ) => {
	const lower = ( fileUrl || '' ).toLowerCase();
	if ( /\.(woff2?|ttf|otf|eot)(\?|$)/.test( lower ) ) {
		return 'font';
	}
	if ( /\.(png|jpe?g|gif|webp|svg|avif)(\?|$)/.test( lower ) ) {
		return 'image';
	}
	if ( /\.css(\?|$)/.test( lower ) ) {
		return 'style';
	}
	return 'script';
};

const goToCreateRule = ( handle, type, scanUrl = '' ) => {
	if ( ! handle ) {
		return;
	}

	clearBulkRuleDraft();
	const base =
		window.assetpilotAdmin?.createPageUrl ||
		`${ window.assetpilotAdmin?.adminUrl || '/wp-admin/' }admin.php?page=assetpilot-create`;
	const url = new URL( base, window.location.origin );
	url.searchParams.delete( 'bulk' );
	url.searchParams.set( 'handle', handle );
	url.searchParams.set( 'type', type );
	if ( scanUrl ) {
		url.searchParams.set( 'scan_url', scanUrl );
	}
	window.location.href = url.toString();
};

const getPresetAnalyzeUrl = () => {
	const preset = new URLSearchParams( window.location.search ).get( 'analyze_url' );
	return preset ? decodeURIComponent( preset ) : '';
};

export default function PageAnalyzer( { embedded = false } ) {
	const presetUrl = getPresetAnalyzeUrl();
	const [ url, setUrl ] = useState( presetUrl || window.assetpilotAdmin?.homeUrl || '' );
	const [ result, setResult ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const autoAnalyzeDone = useRef( false );

	const handleAnalyze = async () => {
		const target = url.trim();
		if ( ! target ) {
			return;
		}

		setLoading( true );
		setError( '' );
		setResult( null );

		try {
			const data = await analyzePage( target );
			setResult( data );
		} catch ( e ) {
			setError( e.message || __( 'Analysis failed.', 'assetpilot' ) );
		} finally {
			setLoading( false );
		}
	};

	useEffect( () => {
		if ( ! presetUrl || autoAnalyzeDone.current ) {
			return;
		}
		autoAnalyzeDone.current = true;
		handleAnalyze();
		// eslint-disable-next-line react-hooks/exhaustive-deps -- run once when opened from frontend admin bar.
	}, [] );

	const scriptList = result?.scripts || [];
	const styleList = result?.styles || [];
	const blockingCount = result?.render_blocking?.length ?? 0;

	return (
		<div className={ `assetpilot-analyzer${ embedded ? ' assetpilot-analyzer--embedded' : '' }` }>
			{ embedded && (
				<p className="assetpilot-analyzer__intro">
					{ __(
						'Analyze HTML-loaded scripts and styles on any URL. For WordPress enqueue handles, use the Assets Explorer tab.',
						'assetpilot'
					) }
				</p>
			) }
			<div className="assetpilot-card assetpilot-analyzer-scan">
				<div className="assetpilot-analyzer-scan__main">
					<div className="assetpilot-analyzer-scan__field">
						<TextControl
							label={ __( 'Page URL', 'assetpilot' ) }
							value={ url }
							onChange={ setUrl }
							placeholder={ window.assetpilotAdmin?.homeUrl || 'https://example.com/' }
							__nextHasNoMarginBottom
						/>
					</div>
					<div className="assetpilot-analyzer-scan__actions">
						<Button
							variant="primary"
							onClick={ handleAnalyze }
							disabled={ ! url.trim() || loading }
						>
							{ loading ? __( 'Analyzing…', 'assetpilot' ) : __( 'Analyze', 'assetpilot' ) }
						</Button>
					</div>
				</div>
				<p className="assetpilot-analyzer-scan__help">
					{ __( 'Enter a URL from this site to analyze scripts and styles loaded on that page.', 'assetpilot' ) }
				</p>
			</div>

			{ error && (
				<Notice className="assetpilot-notice" status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ loading && (
				<div className="assetpilot-loading-panel">
					<Spinner />
					<p>{ __( 'Rendering page and collecting assets…', 'assetpilot' ) }</p>
				</div>
			) }

			{ result && ! loading && (
				<div className="assetpilot-analyzer-results">
					<div className="assetpilot-summary">
						<div className="assetpilot-summary__stat">
							<span className="assetpilot-summary__value">{ result.total_scripts ?? scriptList.length }</span>
							<span className="assetpilot-summary__label">{ __( 'Scripts', 'assetpilot' ) }</span>
						</div>
						<div className="assetpilot-summary__stat">
							<span className="assetpilot-summary__value">{ result.total_styles ?? styleList.length }</span>
							<span className="assetpilot-summary__label">{ __( 'Styles', 'assetpilot' ) }</span>
						</div>
						<div className="assetpilot-summary__stat">
							<span className="assetpilot-summary__value">{ blockingCount }</span>
							<span className="assetpilot-summary__label">{ __( 'Render-blocking styles', 'assetpilot' ) }</span>
						</div>
					</div>

					{ result.duplicates?.length > 0 && (
						<section className="assetpilot-analyzer-section assetpilot-card">
							<h3 className="assetpilot-analyzer-section__title">
								{ __( 'Libraries with multiple files', 'assetpilot' ) }
							</h3>
							<p className="assetpilot-analyzer-section__intro">
								{ __(
									'Several asset URLs on this page match the same library name (e.g. Elementor CSS + JS). That is normal for page builders—not the same file loaded twice.',
									'assetpilot'
								) }
							</p>
							<ul className="assetpilot-analyzer-libraries">
								{ result.duplicates.map( ( dup ) => (
									<li key={ dup.library } className="assetpilot-analyzer-libraries__item">
										<details className="assetpilot-analyzer-library">
											<summary className="assetpilot-analyzer-library__summary">
												<span className="assetpilot-badge assetpilot-badge--plugin">{ dup.library }</span>
												<span className="assetpilot-analyzer-library__count">
													{ dup.count }{ ' ' }
													{ dup.count === 1
														? __( 'file', 'assetpilot' )
														: __( 'files', 'assetpilot' ) }
												</span>
											</summary>
											{ ( dup.urls || [] ).length > 0 && (
												<ul className="assetpilot-analyzer-library__urls">
													{ dup.urls.map( ( fileUrl, i ) => (
														<li key={ i } className="assetpilot-analyzer-library__url-row">
															<code>{ fileUrl }</code>
															<Button
																variant="link"
																onClick={ () =>
																	goToCreateRule( fileUrl, guessTypeFromUrl( fileUrl ) )
																}
															>
																{ __( 'Create rule', 'assetpilot' ) }
															</Button>
														</li>
													) ) }
												</ul>
											) }
										</details>
									</li>
								) ) }
							</ul>
						</section>
					) }

					<div className="assetpilot-analyzer-columns">
						<section className="assetpilot-analyzer-section assetpilot-card">
							<h3 className="assetpilot-analyzer-section__title">
								{ __( 'Scripts', 'assetpilot' ) }
								<span className="assetpilot-analyzer-section__count">{ scriptList.length }</span>
							</h3>
							{ scriptList.length === 0 ? (
								<p className="assetpilot-analyzer-section__empty">{ __( 'No scripts found.', 'assetpilot' ) }</p>
							) : (
								<ul className="assetpilot-analyzer-asset-list">
									{ scriptList.map( ( s, i ) => (
										<li key={ `${ s.handle }-${ i }` } className="assetpilot-analyzer-asset-list__item">
											<div className="assetpilot-analyzer-asset-list__info">
												<code className="assetpilot-handle">{ s.handle || '—' }</code>
												<span className="assetpilot-analyzer-asset-list__url">{ s.src || '—' }</span>
											</div>
											<Button
												variant="link"
												onClick={ () =>
													goToCreateRule(
														s.handle || s.src,
														'script'
													)
												}
												disabled={ ! s.handle && ! s.src }
											>
												{ __( 'Create rule', 'assetpilot' ) }
											</Button>
										</li>
									) ) }
								</ul>
							) }
						</section>

						<section className="assetpilot-analyzer-section assetpilot-card">
							<h3 className="assetpilot-analyzer-section__title">
								{ __( 'Styles', 'assetpilot' ) }
								<span className="assetpilot-analyzer-section__count">{ styleList.length }</span>
							</h3>
							{ styleList.length === 0 ? (
								<p className="assetpilot-analyzer-section__empty">{ __( 'No styles found.', 'assetpilot' ) }</p>
							) : (
								<ul className="assetpilot-analyzer-asset-list">
									{ styleList.map( ( s, i ) => (
										<li key={ `${ s.handle }-${ i }` } className="assetpilot-analyzer-asset-list__item">
											<div className="assetpilot-analyzer-asset-list__info">
												<code className="assetpilot-handle">{ s.handle || '—' }</code>
												<span className="assetpilot-analyzer-asset-list__url">{ s.href || '—' }</span>
											</div>
											<Button
												variant="link"
												onClick={ () =>
													goToCreateRule(
														s.handle || s.href,
														'style'
													)
												}
												disabled={ ! s.handle && ! s.href }
											>
												{ __( 'Create rule', 'assetpilot' ) }
											</Button>
										</li>
									) ) }
								</ul>
							) }
						</section>
					</div>
				</div>
			) }
		</div>
	);
}
