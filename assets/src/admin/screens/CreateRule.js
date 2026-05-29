/**
 * Create / Edit Rule — step-based wizard.
 */
import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import {
	Button,
	SelectControl,
	TextControl,
	CheckboxControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	createRule,
	updateRule,
	validateRule,
	bulkCreateRules,
	fetchPageContext,
	getApiErrorMessage,
} from '../api';
import {
	getBulkRuleDraft,
	clearBulkRuleDraft,
	isBulkRuleMode,
	getAssetsExplorerBulkUrl,
} from '../bulkRuleSession';
import WizardBreadcrumb from '../components/WizardBreadcrumb';
import { assetsExplorerUrl, rulesListUrl } from '../navigationUtils';
import AssetsExplorer from './AssetsExplorer';
import ValidationWarnings from '../components/ValidationWarnings';
import RuleImpactPreview from '../components/RuleImpactPreview';
import ConditionBuilder from '../components/ConditionBuilder';
import { defaultConditions } from '../components/conditionUtils';
import {
	getActionOptionsForType,
	getActionOptionsForAssets,
	resolveActionForType,
} from '../actionOptions';
import { isUrlBasedRule, ruleTargetUrl } from '../utils/ruleAssetUtils';

const WIZARD_STEPS = [
	{ num: 1, label: __( 'Asset', 'assetpilot' ) },
	{ num: 2, label: __( 'Action', 'assetpilot' ) },
	{ num: 3, label: __( 'Conditions', 'assetpilot' ) },
	{ num: 4, label: __( 'Review', 'assetpilot' ) },
];

const VALID_ACTIONS = [ 'disable', 'defer', 'async', 'preload', 'fetchpriority' ];

const getInitialAction = ( editRule ) => {
	if ( editRule?.action_type ) {
		return editRule.action_type;
	}
	const fromUrl = new URLSearchParams( window.location.search ).get( 'action' );
	return VALID_ACTIONS.includes( fromUrl ) ? fromUrl : 'disable';
};

const getInitialConditions = ( editRule, scanUrl ) => {
	if ( editRule?.condition_group ) {
		return editRule.condition_group;
	}

	const conditions = defaultConditions();
	const params = new URLSearchParams( window.location.search );
	const scanned =
		scanUrl ||
		params.get( 'scan_url' ) ||
		params.get( 'page_url' ) ||
		'';

	return conditions;
};

const bulkAssetsFromRule = ( rule ) => {
	const config = rule?.action_config;
	if ( ! config?.bulk_group || ! Array.isArray( config.bulk_assets ) ) {
		return [];
	}
	return config.bulk_assets
		.filter( ( row ) => row?.handle )
		.map( ( row ) => ( {
			handle: row.handle,
			type: row.type || 'script',
			src: row.src || '',
		} ) );
};

const getEditUrlAsset = ( rule ) => {
	if ( ! rule || rule?.action_config?.bulk_group ) {
		return null;
	}
	const url = ruleTargetUrl( rule );
	const handle = url || rule.asset_handle;
	if ( ! isUrlBasedRule( rule ) || ! handle ) {
		return null;
	}
	return {
		handle,
		type: rule.asset_type || 'image',
		src: url || '',
	};
};

const bulkTypeSummary = ( assets ) => {
	const types = [ ...new Set( assets.map( ( a ) => a.type ).filter( Boolean ) ) ];
	if ( 1 === types.length ) {
		return types[ 0 ] === 'script'
			? __( 'All selected assets are scripts.', 'assetpilot' )
			: __( 'All selected assets are styles.', 'assetpilot' );
	}
	return __( 'Mixed scripts and styles — only actions that work for both are shown.', 'assetpilot' );
};

export default function CreateRule( { editRule, onSaved, preselected } ) {
	const bulkMode = isBulkRuleMode();
	const bulkDraft = ! editRule && bulkMode ? getBulkRuleDraft() : null;
	const editBulkAssets = bulkAssetsFromRule( editRule );
	const isBulkEdit = editBulkAssets.length > 0;
	const isBulk = bulkMode && !! bulkDraft?.assets?.length && ! editRule;
	const showsBulkAssets = isBulk || isBulkEdit;

	const editUrlAsset = getEditUrlAsset( editRule );
	const wantsCustomAsset =
		! editRule &&
		! showsBulkAssets &&
		new URLSearchParams( window.location.search ).get( 'custom_asset' ) === '1';

	const initialAsset = showsBulkAssets
		? editBulkAssets[ 0 ] || bulkDraft?.assets?.[ 0 ] || null
		: editUrlAsset ||
			( editRule ? { handle: editRule.asset_handle, type: editRule.asset_type } : preselected );

	const scanUrlContext =
		bulkDraft?.scanUrl ||
		new URLSearchParams( window.location.search ).get( 'scan_url' ) ||
		new URLSearchParams( window.location.search ).get( 'page_url' ) ||
		window.assetpilotAdmin?.homeUrl ||
		'';

	const [ bulkAssets ] = useState( () => bulkDraft?.assets || editBulkAssets );
	const bulkMissing = bulkMode && ! editRule && ! bulkDraft;

	useEffect( () => {
		if ( editRule || bulkMode ) {
			return;
		}
		const params = new URLSearchParams( window.location.search );
		if ( params.get( 'handle' ) && params.get( 'type' ) ) {
			clearBulkRuleDraft();
		}
	}, [ editRule, bulkMode ] );

	useEffect( () => {
		if ( editRule?.condition_group || ! scanUrlContext?.trim() ) {
			return;
		}

		let cancelled = false;

		fetchPageContext( scanUrlContext.trim() )
			.then( ( context ) => {
				if ( cancelled || ! context?.conditions ) {
					return;
				}
				setConditions( {
					...defaultConditions(),
					...context.conditions,
				} );
				setConditionContextLabel( context.label || '' );
				setConditionBuilderKey( ( key ) => key + 1 );
			} )
			.catch( () => {
				if ( cancelled ) {
					return;
				}
				setConditions( {
					...defaultConditions(),
					scan_page_url: scanUrlContext.trim(),
				} );
				setConditionBuilderKey( ( key ) => key + 1 );
			} );

		return () => {
			cancelled = true;
		};
	}, [ editRule?.condition_group, scanUrlContext ] );

	const [ step, setStep ] = useState( () => {
		if ( showsBulkAssets ) {
			return 1;
		}
		return initialAsset ? 2 : 1;
	} );
	const [ asset, setAsset ] = useState( initialAsset || null );
	const [ customUrl, setCustomUrl ] = useState( () => editUrlAsset?.src || editUrlAsset?.handle || '' );
	const [ customType, setCustomType ] = useState( () => editUrlAsset?.type || 'image' );
	const [ action, setAction ] = useState( () => getInitialAction( editRule ) );
	const [ conditions, setConditions ] = useState( () => getInitialConditions( editRule, scanUrlContext ) );
	const [ conditionContextLabel, setConditionContextLabel ] = useState( '' );
	const [ conditionBuilderKey, setConditionBuilderKey ] = useState( 0 );
	const [ actionConfig, setActionConfig ] = useState(
		editRule?.action_config || {}
	);
	const [ priority, setPriority ] = useState( editRule?.priority ?? 10 );
	const [ enabled, setEnabled ] = useState( editRule?.enabled ?? true );
	const [ label, setLabel ] = useState( editRule?.label || '' );
	const [ notes, setNotes ] = useState( editRule?.notes || '' );
	const [ validation, setValidation ] = useState( null );
	const [ validating, setValidating ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ customAssetOpen, setCustomAssetOpen ] = useState(
		() => wantsCustomAsset || !! editUrlAsset || !! customUrl.trim()
	);
	const pageUrlContext = scanUrlContext;
	const [ validationError, setValidationError ] = useState( '' );
	const [ saveSummary, setSaveSummary ] = useState( null );

	const isUrlAsset = useMemo(
		() =>
			!! asset &&
			( !! asset.src ||
				isUrlBasedRule( {
					asset_handle: asset.handle,
					asset_type: asset.type,
					action_config: actionConfig,
				} ) ),
		[ asset, actionConfig ]
	);

	useEffect( () => {
		if ( ! saved || editRule?.id ) {
			return;
		}
		const url = new URL( window.location.href );
		if ( url.searchParams.get( 'bulk' ) === '1' ) {
			url.searchParams.delete( 'bulk' );
			window.history.replaceState( {}, '', url.toString() );
		}
	}, [ saved, editRule?.id ] );

	const currentStepLabel =
		WIZARD_STEPS.find( ( s ) => s.num === step )?.label || '';

	const wizardBreadcrumbItems = useMemo( () => {
		const assetsHref = assetsExplorerUrl( { scanUrl: pageUrlContext } );

		if ( editRule?.id && isBulkEdit ) {
			return [
				{ label: __( 'Rules', 'assetpilot' ), href: rulesListUrl() },
				{
					label: sprintf(
						/* translators: %d: asset count */
						__( 'Edit bulk rule (%d assets)', 'assetpilot' ),
						bulkAssets.length
					),
				},
				...( step > 1 ? [ { label: currentStepLabel } ] : [] ),
			];
		}

		if ( editRule?.id ) {
			return [
				{ label: __( 'Rules', 'assetpilot' ), href: rulesListUrl() },
				{
					label: sprintf(
						/* translators: %s: asset handle */
						__( 'Edit: %s', 'assetpilot' ),
						editRule.asset_handle
					),
				},
				...( step > 1 ? [ { label: currentStepLabel } ] : [] ),
			];
		}

		if ( showsBulkAssets ) {
			return [
				{ label: __( 'Assets', 'assetpilot' ), href: assetsHref },
				{
					label: sprintf(
						/* translators: %d: asset count */
						__( 'Bulk rule (%d assets)', 'assetpilot' ),
						bulkAssets.length
					),
				},
				...( step > 1 ? [ { label: currentStepLabel } ] : [] ),
			];
		}

		const middleLabel = asset?.handle
			? asset.handle
			: __( 'Create rule', 'assetpilot' );

		return [
			{ label: __( 'Assets', 'assetpilot' ), href: assetsHref },
			{ label: middleLabel },
			...( step > 1 ? [ { label: currentStepLabel } ] : [] ),
		];
	}, [
		editRule?.id,
		editRule?.asset_handle,
		showsBulkAssets,
		isBulkEdit,
		bulkAssets.length,
		step,
		currentStepLabel,
		pageUrlContext,
		asset?.handle,
	] );

	const handleCancel = () => {
		const dirty = step > 1 || label.trim() !== '' || notes.trim() !== '';
		if ( dirty && ! window.confirm( __( 'Discard unsaved changes?', 'assetpilot' ) ) ) {
			return;
		}
		if ( isBulk ) {
			clearBulkRuleDraft();
		}
		if ( editRule?.id && onSaved ) {
			onSaved();
			return;
		}
		window.location.href = assetsExplorerUrl( { scanUrl: pageUrlContext } );
	};

	const actionOptions = useMemo( () => {
		if ( showsBulkAssets ) {
			return getActionOptionsForAssets( bulkAssets );
		}
		return getActionOptionsForType( asset?.type || '' );
	}, [ showsBulkAssets, bulkAssets, asset?.type ] );

	useEffect( () => {
		if ( showsBulkAssets ) {
			if ( ! actionOptions.length ) {
				return;
			}
			if ( ! actionOptions.some( ( opt ) => opt.value === action ) ) {
				setAction( actionOptions[ 0 ].value );
			}
			return;
		}
		if ( ! asset?.type ) {
			return;
		}
		const resolved = resolveActionForType( action, asset.type );
		if ( resolved !== action ) {
			setAction( resolved );
		}
	}, [ showsBulkAssets, asset?.type, action, actionOptions ] );

	const resetWizard = () => {
		setStep( 1 );
		setAsset( null );
		setCustomUrl( '' );
		setAction( 'disable' );
		setConditions( defaultConditions() );
		setActionConfig( {} );
		setPriority( 10 );
		setEnabled( true );
		setLabel( '' );
		setNotes( '' );
		setSaved( false );
		setError( '' );
	};

	const buildPayload = useCallback( () => {
		const primary = showsBulkAssets ? bulkAssets[ 0 ] : asset;
		let handle = primary?.handle || '';
		if ( action === 'fetchpriority' && actionConfig.attachment_id ) {
			handle = String( actionConfig.attachment_id );
		}

		const config = {
			...actionConfig,
			src: primary?.src || actionConfig.href || actionConfig.src || '',
		};
		if ( scanUrlContext ) {
			config.scan_url = scanUrlContext;
		}
		if ( action === 'fetchpriority' && ! config.value ) {
			config.value = 'high';
		}
		if ( showsBulkAssets && bulkAssets.length > 0 ) {
			config.bulk_group  = true;
			config.bulk_assets = bulkAssets.map( ( row ) => ( {
				handle: row.handle,
				type: row.type,
				...( row.src ? { src: row.src } : {} ),
			} ) );
		}

		return {
			asset_handle: handle,
			asset_type: primary?.type,
			action_type: action,
			condition_group: conditions.global ? { global: true, scope: 'global' } : conditions,
			action_config: config,
			priority,
			enabled,
			label: label.trim(),
			notes: notes.trim(),
		};
	}, [
		showsBulkAssets,
		bulkAssets,
		asset,
		action,
		actionConfig,
		conditions,
		priority,
		enabled,
		label,
		notes,
		scanUrlContext,
	] );

	useEffect( () => {
		if ( showsBulkAssets ) {
			if ( ! bulkAssets.length ) {
				setValidation( null );
				setValidationError( '' );
				return;
			}
		} else if ( ! asset?.handle || ! asset?.type ) {
			setValidation( null );
			setValidationError( '' );
			return;
		}

		let cancelled = false;
		const timer = window.setTimeout( () => {
			setValidating( true );
			setValidationError( '' );

			validateRule( buildPayload(), editRule?.id || null, { scanUrl: scanUrlContext } )
				.then( ( res ) => {
					if ( ! cancelled ) {
						setValidation( res );
					}
				} )
				.catch( ( err ) => {
					if ( ! cancelled ) {
						setValidation( null );
						setValidationError(
							err?.message ||
								__( 'Conflict check failed. Save will still run server-side checks.', 'assetpilot' )
						);
					}
				} )
				.finally( () => {
					if ( ! cancelled ) {
						setValidating( false );
					}
				} );
		}, 400 );

		return () => {
			cancelled = true;
			window.clearTimeout( timer );
		};
	}, [
		showsBulkAssets,
		bulkAssets,
		asset,
		action,
		conditions,
		priority,
		enabled,
		actionConfig,
		buildPayload,
		editRule?.id,
		scanUrlContext,
	] );

	const handleSave = async ( confirmDanger = false ) => {
		if ( isBulk ) {
			if ( ! bulkAssets.length ) {
				setError( __( 'No assets selected for bulk rule.', 'assetpilot' ) );
				return;
			}
		} else if ( ! asset?.handle || ! asset?.type ) {
			setError( __( 'Please select an asset first.', 'assetpilot' ) );
			return;
		}

		setSaving( true );
		setError( '' );

		try {
			if ( isBulk ) {
				const result = await bulkCreateRules( {
					mode: 'grouped',
					assets: bulkAssets.map( ( a ) => ( {
						handle: a.handle,
						type: a.type,
						src: a.src || undefined,
					} ) ),
					action_type: action,
					condition_group: conditions.global
						? { global: true, scope: 'global' }
						: conditions,
					enabled,
					priority,
					label: label.trim(),
					notes: notes.trim(),
					scan_url: scanUrlContext || undefined,
					confirm_danger: confirmDanger,
				} );

				clearBulkRuleDraft();
				setSaved( true );
				const assetCount = result.assets || bulkAssets.length;
				setSaveSummary( {
					isBulk: true,
					grouped: result.mode === 'grouped',
					created: result.created || 0,
					assets: assetCount,
					failed: ( result.errors || [] ).length,
				} );

				if ( ( result.errors || [] ).length > 0 ) {
					setError(
						sprintf(
							/* translators: 1: asset count in rule, 2: failed */
							__( 'Bulk rule saved with %1$d assets. %2$d assets were skipped — see Rules list.', 'assetpilot' ),
							assetCount,
							result.errors.length
						)
					);
				}

				if ( editRule?.id && onSaved ) {
					window.setTimeout( () => onSaved(), 500 );
				}
				return;
			}

			const payload = {
				...buildPayload(),
				confirm_danger: confirmDanger,
				scan_url: scanUrlContext || undefined,
			};

			if ( editRule?.id ) {
				await updateRule( editRule.id, payload );
			} else {
				await createRule( payload );
			}

			setSaved( true );
			setSaveSummary( { isBulk: false, created: 1, failed: 0 } );

			if ( editRule?.id && onSaved ) {
				window.setTimeout( () => onSaved(), 500 );
			}
		} catch ( e ) {
			const code = e?.code || e?.data?.code;
			if ( code === 'assetpilot_validation_requires_confirm' && ! confirmDanger ) {
				const dangers = ( e?.data?.validation?.issues || validation?.issues || [] ).filter(
					( i ) => i.severity === 'danger'
				);
				const summary = dangers.map( ( d ) => d.message ).join( '\n' );
				const proceed = window.confirm(
					`${ summary }\n\n${ __( 'Save this rule anyway?', 'assetpilot' ) }`
				);
				if ( proceed ) {
					setSaving( false );
					return handleSave( true );
				}
				setValidation( e?.data?.validation || validation );
				setError( __( 'Save cancelled — resolve critical conflicts or confirm to proceed.', 'assetpilot' ) );
			} else {
				setError(
					getApiErrorMessage(
						e,
						editRule?.id
							? __( 'Failed to update rule.', 'assetpilot' )
							: __( 'Failed to save rule.', 'assetpilot' )
					)
				);
			}
		} finally {
			setSaving( false );
		}
	};

	const formatReviewScope = () => {
		if ( conditions.global ) {
			return __( 'Entire site', 'assetpilot' );
		}
		if ( conditionContextLabel ) {
			return conditionContextLabel;
		}
		if ( conditions.singular_type?.length ) {
			return sprintf(
				/* translators: %s: comma-separated post types */
				__( 'Singular — %s', 'assetpilot' ),
				conditions.singular_type.join( ', ' )
			);
		}
		if ( conditions.scan_page_url ) {
			return sprintf(
				/* translators: %s: URL */
				__( 'Scanned page: %s', 'assetpilot' ),
				conditions.scan_page_url
			);
		}
		return __( 'Conditional', 'assetpilot' );
	};

	if ( bulkMissing && ! saved ) {
		return (
			<div className="assetpilot-create-rule">
				<Notice className="assetpilot-notice" status="warning" isDismissible={ false }>
					{ __( 'Bulk selection expired. Select assets in Assets Explorer and choose “Configure bulk rule”.', 'assetpilot' ) }
				</Notice>
				<Button
					variant="primary"
					href={ window.assetpilotAdmin?.assetsPageUrl || 'admin.php?page=assetpilot-assets' }
				>
					{ __( 'Go to Assets Explorer', 'assetpilot' ) }
				</Button>
			</div>
		);
	}

	if ( saved && ! editRule?.id && saveSummary ) {
		return (
			<div className="assetpilot-create-rule">
				<Notice className="assetpilot-notice assetpilot-notice--post-save" status="success" isDismissible={ false }>
					<p className="assetpilot-notice__body">
						{ saveSummary.isBulk
							? saveSummary.grouped
								? sprintf(
										/* translators: %d: asset count in one bulk rule */
										__(
											'One bulk rule created for %d assets. Use Re-verify all on the Rules page when ready.',
											'assetpilot'
										),
										saveSummary.assets || saveSummary.created
								  )
								: sprintf(
										/* translators: %d: rules created */
										__( '%d rules created successfully.', 'assetpilot' ),
										saveSummary.created
								  )
							: __( 'Rule saved successfully.', 'assetpilot' ) }
					</p>
					<div className="assetpilot-post-save-actions">
						<Button variant="primary" href={ rulesListUrl( { assetpilot_saved: '1' } ) }>
							{ __( 'View rules', 'assetpilot' ) }
						</Button>
						<Button
							variant="secondary"
							href={ assetsExplorerUrl( { scanUrl: pageUrlContext } ) }
						>
							{ __( 'Create another on same page', 'assetpilot' ) }
						</Button>
					</div>
				</Notice>
			</div>
		);
	}

	return (
		<div className="assetpilot-create-rule">
			<WizardBreadcrumb items={ wizardBreadcrumbItems } />
			{ pageUrlContext && (
				<p className="assetpilot-wizard-context">
					<span className="assetpilot-wizard-context__label">
						{ __( 'Scanned page:', 'assetpilot' ) }
					</span>{ ' ' }
					<a
						className="assetpilot-wizard-context__url"
						href={ pageUrlContext }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ pageUrlContext }
					</a>
				</p>
			) }
			{ saved && editRule?.id && (
				<Notice status="success" isDismissible={ false }>
					{ __( 'Rule updated. Returning to rules list…', 'assetpilot' ) }
				</Notice>
			) }
			{ isBulk && (
				<Notice className="assetpilot-notice" status="info" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: asset count */
						__(
							'Bulk rule — one rule will apply to all %d selected assets with the settings below.',
							'assetpilot'
						),
						bulkAssets.length
					) }
				</Notice>
			) }
			{ isBulkEdit && (
				<Notice className="assetpilot-notice" status="info" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: asset count */
						__(
							'Editing bulk rule — %d assets share this action and conditions.',
							'assetpilot'
						),
						bulkAssets.length
					) }
				</Notice>
			) }
			{ ! editRule && conditionContextLabel && (
				<Notice className="assetpilot-notice" status="info" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: suggested scope label */
						__( 'Suggested scope from scanned page: %s', 'assetpilot' ),
						conditionContextLabel
					) }
				</Notice>
			) }
			<nav className="assetpilot-steps" aria-label={ __( 'Create rule steps', 'assetpilot' ) }>
				{ WIZARD_STEPS.map( ( { num, label } ) => (
					<span
						key={ num }
						className={ `assetpilot-step ${ step === num ? 'is-active' : '' } ${ step > num ? 'is-done' : '' }` }
					>
						<span className="assetpilot-step__number">{ num }</span>
						<span className="assetpilot-step__label">{ label }</span>
					</span>
				) ) }
			</nav>

			{ step === 1 && showsBulkAssets && (
				<div className="assetpilot-create-step">
					<h2 className="assetpilot-create-step__title">
						{ sprintf(
							/* translators: %d: asset count */
							__( 'Bulk rule assets (%d)', 'assetpilot' ),
							bulkAssets.length
						) }
					</h2>
					<p className="assetpilot-create-step__lead">
						{ isBulkEdit
							? __(
									'This rule applies to every asset listed below. You can change action, conditions, and settings on the next steps. To change which assets are included, duplicate this rule and create a new bulk selection.',
									'assetpilot'
							  )
							: __(
									'These assets were selected in Assets Explorer. The next steps set one shared action and conditions for all of them.',
									'assetpilot'
							  ) }
					</p>
					<ul className="assetpilot-bulk-asset-list assetpilot-bulk-asset-list--chips">
						{ bulkAssets.map( ( a ) => (
							<li key={ `${ a.type }:${ a.handle }` } className="assetpilot-bulk-asset-chip">
								<code className="assetpilot-handle">{ a.handle }</code>
								<span className={ `assetpilot-badge assetpilot-badge--${ a.type }` }>{ a.type }</span>
							</li>
						) ) }
					</ul>
					<div className="assetpilot-step-nav">
						{ isBulkEdit ? (
							<Button variant="secondary" href={ rulesListUrl() }>
								{ __( 'Back to Rules', 'assetpilot' ) }
							</Button>
						) : (
							<Button
								variant="secondary"
								href={ getAssetsExplorerBulkUrl( pageUrlContext ) }
							>
								{ __( 'Back to Assets Explorer', 'assetpilot' ) }
							</Button>
						) }
						<div className="assetpilot-step-nav__actions">
							<Button variant="primary" onClick={ () => setStep( 2 ) }>
								{ __( 'Next', 'assetpilot' ) }
							</Button>
						</div>
					</div>
				</div>
			) }

			{ step === 1 && ! showsBulkAssets && (
				<div className="assetpilot-create-step">
					<h2 className="assetpilot-create-step__title">{ __( 'Choose Asset', 'assetpilot' ) }</h2>
					<p className="assetpilot-create-step__lead">
						{ __(
							'Pick a script or style from the scan below, or add an image/font by URL (not in the WordPress enqueue registry).',
							'assetpilot'
						) }
					</p>
					<div className={ `assetpilot-custom-asset${ customAssetOpen ? ' assetpilot-custom-asset--open' : '' }` }>
						<button
							type="button"
							className="assetpilot-custom-asset__toggle"
							aria-expanded={ customAssetOpen }
							onClick={ () => setCustomAssetOpen( ! customAssetOpen ) }
						>
							<span className="assetpilot-custom-asset__toggle-label">
								{ __( 'Add by URL (image or font)', 'assetpilot' ) }
							</span>
							<span
								className={ `assetpilot-custom-asset__chevron${ customAssetOpen ? ' is-open' : '' }` }
								aria-hidden="true"
							/>
						</button>
						{ customAssetOpen && (
							<div className="assetpilot-custom-asset__body">
								<p className="assetpilot-custom-asset__intro">
									{ __( 'Add a rule for an image or font loaded outside the script/style registry.', 'assetpilot' ) }
								</p>
								<div className="assetpilot-custom-asset__fields">
									<div className="assetpilot-custom-asset__field">
										<label className="assetpilot-field-label" htmlFor="assetpilot-custom-url">
											{ __( 'Asset URL', 'assetpilot' ) }
										</label>
										<input
											id="assetpilot-custom-url"
											type="url"
											className="assetpilot-toolbar-field"
											value={ customUrl }
											onChange={ ( e ) => setCustomUrl( e.target.value ) }
											placeholder="https://example.com/font.woff2"
										/>
									</div>
									<div className="assetpilot-custom-asset__field assetpilot-custom-asset__field--type">
										<label className="assetpilot-field-label" htmlFor="assetpilot-custom-type">
											{ __( 'Type', 'assetpilot' ) }
										</label>
										<select
											id="assetpilot-custom-type"
											className="assetpilot-toolbar-field assetpilot-toolbar-field--select"
											value={ customType }
											onChange={ ( e ) => setCustomType( e.target.value ) }
										>
											<option value="image">{ __( 'Image', 'assetpilot' ) }</option>
											<option value="font">{ __( 'Font', 'assetpilot' ) }</option>
										</select>
									</div>
								</div>
								<div className="assetpilot-custom-asset__actions">
									<Button
										variant="primary"
										disabled={ ! customUrl.trim() }
										onClick={ () => {
											setAsset( { handle: customUrl.trim(), type: customType } );
											setAction( resolveActionForType( action, customType ) );
											setActionConfig( { href: customUrl.trim(), as: customType } );
											setStep( 2 );
										} }
									>
										{ __( 'Use custom asset', 'assetpilot' ) }
									</Button>
								</div>
							</div>
						) }
					</div>
					{ asset && (
						<p className="assetpilot-selected-asset">
							{ __( 'Selected:', 'assetpilot' ) }{ ' ' }
							{ isUrlAsset ? (
								<a href={ asset.src || asset.handle } target="_blank" rel="noopener noreferrer">
									<code>{ asset.src || asset.handle }</code>
								</a>
							) : (
								<code>{ asset.handle }</code>
							) }{ ' ' }
							<span className={ `assetpilot-badge assetpilot-badge--${ asset.type }` }>{ asset.type }</span>
						</p>
					) }
				</div>
			) }

			{ step === 1 && ! showsBulkAssets && (
				<AssetsExplorer
					onSelectAsset={ ( a ) => {
						setAsset( { handle: a.handle, type: a.type, src: a.src } );
						setAction( resolveActionForType( action, a.type ) );
						setStep( 2 );
					} }
				/>
			) }

			{ step === 2 && (
				<div className="assetpilot-create-step">
					<h2 className="assetpilot-create-step__title">{ __( 'Choose Action', 'assetpilot' ) }</h2>
					{ showsBulkAssets ? (
						<>
							<p className="assetpilot-create-step__meta">
								{ sprintf(
									/* translators: %d: count */
									__( '%d assets', 'assetpilot' ),
									bulkAssets.length
								) }
							</p>
							<p className="assetpilot-create-step__types">{ bulkTypeSummary( bulkAssets ) }</p>
						</>
					) : (
						<p className="assetpilot-create-step__meta">
							<code>{ asset?.handle }</code>{ ' ' }
							<span className={ `assetpilot-badge assetpilot-badge--${ asset?.type || 'script' }` }>
								{ asset?.type }
							</span>
						</p>
					) }
					<div className="assetpilot-form-fields">
					<SelectControl
						label={ __( 'Action', 'assetpilot' ) }
						help={
							showsBulkAssets
								? bulkTypeSummary( bulkAssets )
								: asset?.type === 'style'
									? __( 'Defer and async apply to scripts only.', 'assetpilot' )
									: undefined
						}
						value={ action }
						options={ actionOptions }
						onChange={ setAction }
					/>
					{ action === 'preload' && (
						<>
							<TextControl
								label={ __( 'Asset URL (optional)', 'assetpilot' ) }
								value={ actionConfig.href || '' }
								onChange={ ( href ) => setActionConfig( { ...actionConfig, href } ) }
							/>
							<SelectControl
								label={ __( 'As', 'assetpilot' ) }
								value={ actionConfig.as || asset?.type || 'script' }
								options={ [
									{ label: 'script', value: 'script' },
									{ label: 'style', value: 'style' },
									{ label: 'font', value: 'font' },
									{ label: 'image', value: 'image' },
								] }
								onChange={ ( as ) => setActionConfig( { ...actionConfig, as } ) }
							/>
							<CheckboxControl
								label={ __( 'Crossorigin (fonts)', 'assetpilot' ) }
								checked={ !! actionConfig.crossorigin }
								onChange={ ( crossorigin ) => setActionConfig( { ...actionConfig, crossorigin } ) }
							/>
						</>
					) }
					{ action === 'fetchpriority' && (
						<>
							<SelectControl
								label={ __( 'Priority', 'assetpilot' ) }
								value={ actionConfig.value || 'high' }
								options={ [
									{ label: 'high', value: 'high' },
									{ label: 'low', value: 'low' },
								] }
								onChange={ ( value ) => setActionConfig( { ...actionConfig, value } ) }
							/>
							{ asset?.type === 'image' && (
								<TextControl
									label={ __( 'Attachment ID (optional)', 'assetpilot' ) }
									help={ __( 'Use the media library attachment ID for hero images.', 'assetpilot' ) }
									value={ actionConfig.attachment_id ? String( actionConfig.attachment_id ) : '' }
									onChange={ ( val ) =>
										setActionConfig( {
											...actionConfig,
											attachment_id: val ? parseInt( val, 10 ) : undefined,
										} )
									}
								/>
							) }
						</>
					) }
					</div>
					<ValidationWarnings validation={ validation } />
					{ validating && <p className="assetpilot-validation-loading">{ __( 'Checking conflicts…', 'assetpilot' ) }</p> }
					{ validationError && (
						<Notice className="assetpilot-notice" status="warning" isDismissible={ false }>
							{ validationError }
						</Notice>
					) }
					<div className="assetpilot-step-nav">
						<Button variant="secondary" onClick={ () => setStep( 1 ) }>
							{ __( 'Back', 'assetpilot' ) }
						</Button>
						<Button variant="link" isDestructive onClick={ handleCancel }>
							{ __( 'Cancel', 'assetpilot' ) }
						</Button>
						<div className="assetpilot-step-nav__actions">
							<Button
								variant="primary"
								onClick={ () => setStep( 3 ) }
								disabled={ ! showsBulkAssets && ! asset }
							>
								{ __( 'Next', 'assetpilot' ) }
							</Button>
						</div>
					</div>
				</div>
			) }

			{ step === 3 && (
				<div className="assetpilot-create-step">
					<h2 className="assetpilot-create-step__title">
						{ __( 'Where should this rule apply?', 'assetpilot' ) }
					</h2>
					<ConditionBuilder
						key={
							editRule?.id
								? `edit-${ editRule.id }`
								: showsBulkAssets
									? `bulk-${ conditionBuilderKey }`
									: `new-${ conditionBuilderKey }`
						}
						conditions={ conditions }
						onChange={ setConditions }
						defaultScanUrl={ scanUrlContext }
					/>
					<div className="assetpilot-step-nav">
						<Button variant="secondary" onClick={ () => setStep( 2 ) }>
							{ __( 'Back', 'assetpilot' ) }
						</Button>
						<Button variant="link" isDestructive onClick={ handleCancel }>
							{ __( 'Cancel', 'assetpilot' ) }
						</Button>
						<div className="assetpilot-step-nav__actions">
							<Button variant="primary" onClick={ () => setStep( 4 ) }>
								{ __( 'Next', 'assetpilot' ) }
							</Button>
						</div>
					</div>
				</div>
			) }

			{ step === 4 && (
				<div className="assetpilot-create-step">
					<h2 className="assetpilot-create-step__title">{ __( 'Review & Save', 'assetpilot' ) }</h2>

					<dl className="assetpilot-review">
						<div className="assetpilot-review__row">
							<dt>{ showsBulkAssets ? __( 'Assets', 'assetpilot' ) : __( 'Asset', 'assetpilot' ) }</dt>
							<dd>
								{ showsBulkAssets ? (
									<ul className="assetpilot-bulk-asset-list assetpilot-bulk-asset-list--compact">
										{ bulkAssets.map( ( a ) => (
											<li key={ `${ a.type }:${ a.handle }` }>
												<code className="assetpilot-handle">{ a.handle }</code>
												<span className={ `assetpilot-badge assetpilot-badge--${ a.type }` }>
													{ a.type }
												</span>
											</li>
										) ) }
									</ul>
								) : isUrlAsset ? (
									<>
										<a
											href={ asset?.src || asset?.handle }
											target="_blank"
											rel="noopener noreferrer"
											className="assetpilot-review__url"
										>
											<code>{ asset?.src || asset?.handle }</code>
										</a>{ ' ' }
										<span className={ `assetpilot-badge assetpilot-badge--${ asset?.type || 'image' }` }>
											{ asset?.type }
										</span>
										<span className="assetpilot-badge assetpilot-badge--url">{ __( 'URL', 'assetpilot' ) }</span>
									</>
								) : (
									<>
										<code>{ asset?.handle }</code>{ ' ' }
										<span className={ `assetpilot-badge assetpilot-badge--${ asset?.type || 'script' }` }>
											{ asset?.type }
										</span>
									</>
								) }
							</dd>
						</div>
						<div className="assetpilot-review__row">
							<dt>{ __( 'Action', 'assetpilot' ) }</dt>
							<dd>{ action }</dd>
						</div>
						<div className="assetpilot-review__row">
							<dt>{ __( 'Scope', 'assetpilot' ) }</dt>
							<dd>{ formatReviewScope() }</dd>
						</div>
					</dl>

					<div className="assetpilot-review-meta">
						<TextControl
							label={ __( 'Rule label (optional)', 'assetpilot' ) }
							help={ __( 'A friendly name, e.g. “Homepage hero optimization”.', 'assetpilot' ) }
							value={ label }
							onChange={ setLabel }
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __( 'Internal notes (optional)', 'assetpilot' ) }
							value={ notes }
							onChange={ setNotes }
							__nextHasNoMarginBottom
						/>
					</div>

					<div className="assetpilot-review-settings">
						<div className="assetpilot-review-settings__field assetpilot-review-settings__field--priority">
							<TextControl
								label={ __( 'Rule priority', 'assetpilot' ) }
								help={ __( 'Lower numbers run first.', 'assetpilot' ) }
								type="number"
								value={ String( priority ) }
								onChange={ ( val ) => setPriority( parseInt( val, 10 ) || 10 ) }
								__nextHasNoMarginBottom
							/>
						</div>
						<div className="assetpilot-review-settings__field assetpilot-review-settings__field--enabled">
							<CheckboxControl
								label={ __( 'Rule enabled', 'assetpilot' ) }
								checked={ enabled }
								onChange={ setEnabled }
								__nextHasNoMarginBottom
							/>
						</div>
					</div>

					<RuleImpactPreview
						impactPreview={ validation?.impact_preview }
						loading={ validating && step === 4 }
					/>

					<ValidationWarnings validation={ validation } className="assetpilot-validation--review" />
					{ validating && <p className="assetpilot-validation-loading">{ __( 'Checking conflicts…', 'assetpilot' ) }</p> }
					{ validationError && (
						<Notice className="assetpilot-notice" status="warning" isDismissible={ false }>
							{ validationError }
						</Notice>
					) }
					{ error && (
						<Notice className="assetpilot-notice assetpilot-notice--api-error" status="error" isDismissible={ false }>
							<p className="assetpilot-notice__title">
								{ editRule?.id
									? __( 'Could not update rule', 'assetpilot' )
									: __( 'Could not save rule', 'assetpilot' ) }
							</p>
							<p className="assetpilot-notice__body">{ error }</p>
						</Notice>
					) }
					<div className="assetpilot-step-nav">
						<Button variant="secondary" onClick={ () => setStep( 3 ) } disabled={ saving || saved }>
							{ __( 'Back', 'assetpilot' ) }
						</Button>
						<Button variant="link" isDestructive onClick={ handleCancel } disabled={ saving }>
							{ __( 'Cancel', 'assetpilot' ) }
						</Button>
						<div className="assetpilot-step-nav__actions">
							<Button
								variant="primary"
								onClick={ () => handleSave( false ) }
								disabled={ saving || saved || validating }
							>
								{ saving ? (
									<Spinner />
								) : saved ? (
									__( 'Saved', 'assetpilot' )
								) : isBulk ? (
									sprintf(
										/* translators: %d: asset count */
										__( 'Save bulk rule (%d assets)', 'assetpilot' ),
										bulkAssets.length
									)
								) : (
									__( 'Save Rule', 'assetpilot' )
								) }
							</Button>
						</div>
					</div>
				</div>
			) }
		</div>
	);
}
